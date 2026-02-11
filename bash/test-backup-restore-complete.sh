#!/bin/bash
# ============================================================
# TESTE DE BACKUP E RESTORE - MariaDB 10.11.15-jammy
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-11-29
# Versão: 1.0.0
# ============================================================
#
# Este script testa o ciclo completo de backup/restore:
# 1. Criação de dados de teste
# 2. Backup manual
# 3. Simulação de perda de dados
# 4. Restore com bootstrap Galera
# 5. Validação de integridade
#
# ============================================================

set -euo pipefail

# ============================================================
# CONFIGURACOES
# ============================================================

NAMESPACE="${NAMESPACE:-uniselec-api-prd}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-Password123}"
BACKUP_DIR="/backup/galera-test-$(date +%Y%m%d-%H%M%S)"
TEST_DB="backup_test_db"
TEST_TABLE="backup_test_table"
TEST_ROWS=1000

# Pods do cluster Galera
PRIMARY_POD="mariadb-0"
BACKUP_POD="mariadb-0"      # Pod onde será feito o backup
SECONDARY_POD="mariadb-1"   # Segundo nó
TERTIARY_POD="mariadb-2"    # Terceiro nó


# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============================================================
# FUNCOES AUXILIARES
# ============================================================

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

section() {
    echo ""
    echo "================================================================"
    echo -e "${BLUE}$1${NC}"
    echo "================================================================"
}

sql_exec() {
    local pod="$1"
    local sql="$2"
    kubectl exec -n "$NAMESPACE" "$pod" -- \
        mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "$sql" 2>/dev/null
}

check_cluster_health() {
    log_info "Verificando saude do cluster Galera..."

    for pod in $PRIMARY_POD $SECONDARY_POD $TERTIARY_POD; do
        local state=$(sql_exec "$pod" "SHOW STATUS LIKE 'wsrep_local_state_comment'" | awk '{print $2}')
        local size=$(sql_exec "$pod" "SHOW STATUS LIKE 'wsrep_cluster_size'" | awk '{print $2}')
        local ready=$(sql_exec "$pod" "SHOW STATUS LIKE 'wsrep_ready'" | awk '{print $2}')
        local connected=$(sql_exec "$pod" "SHOW STATUS LIKE 'wsrep_connected'" | awk '{print $2}')

        if [ "$state" = "Synced" ] && [ "$size" = "3" ] && [ "$ready" = "ON" ] && [ "$connected" = "ON" ]; then
            log_success "$pod: $state (cluster_size=$size)"
        else
            log_error "$pod: $state (cluster_size=$size) - NAO esta pronto!"
            return 1
        fi
    done
}

wait_for_sync() {
    local pod="$1"
    local max_wait=300
    local elapsed=0

    log_info "Aguardando $pod sincronizar..."

    while [ $elapsed -lt $max_wait ]; do
        local state=$(sql_exec "$pod" "SHOW STATUS LIKE 'wsrep_local_state_comment'" | awk '{print $2}')
        local ready=$(sql_exec "$pod" "SHOW STATUS LIKE 'wsrep_ready'" | awk '{print $2}')
        local connected=$(sql_exec "$pod" "SHOW STATUS LIKE 'wsrep_connected'" | awk '{print $2}')

        if [ "$state" = "Synced" ] && [ "$ready" = "ON" ] && [ "$connected" = "ON" ]; then
            log_success "$pod esta Synced (${elapsed}s)"
            return 0
        fi

        sleep 5
        elapsed=$((elapsed + 5))
        echo -n "."
    done

    log_error "$pod NAO sincronizou apos ${max_wait}s"
    return 1
}

# ============================================================
# FASE 1: PREPARACAO E VALIDACAO INICIAL
# ============================================================

section "FASE 1: PREPARACAO E VALIDACAO INICIAL"

log_info "Namespace: $NAMESPACE"
log_info "Primary Pod: $PRIMARY_POD"
log_info "Backup Pod: $BACKUP_POD"
log_info "Backup Dir: $BACKUP_DIR"

log_info "Verificando pods..."
kubectl get pods -n "$NAMESPACE" -l app=mariadb -o wide

check_cluster_health || {
    log_error "Cluster nao esta saudavel. Abortando teste."
    exit 1
}

log_success "Cluster esta saudavel e pronto para testes!"

# ============================================================
# FASE 2: CRIACAO DE DADOS DE TESTE
# ============================================================

section "FASE 2: CRIACAO DE DADOS DE TESTE"

log_info "Criando banco de dados de teste: $TEST_DB"
sql_exec "$PRIMARY_POD" "CREATE DATABASE IF NOT EXISTS $TEST_DB;"

log_info "Criando tabela de teste: $TEST_TABLE"
sql_exec "$PRIMARY_POD" "
CREATE TABLE IF NOT EXISTS $TEST_DB.$TEST_TABLE (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL,
    data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checksum VARCHAR(32),
    INDEX idx_uuid (uuid),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;
"

log_info "Populando tabela com $TEST_ROWS registros em lotes..."

batch_size=100
batches=$((TEST_ROWS / batch_size))

for ((batch=1; batch<=batches; batch++)); do
    sql="INSERT INTO $TEST_DB.$TEST_TABLE (uuid, data, checksum) VALUES "

    values=""
    for ((i=1; i<=batch_size; i++)); do
        idx=$(((batch-1)*batch_size + i))
        uuid=$(cat /proc/sys/kernel/random/uuid 2>/dev/null || echo "$(date +%s)-$idx")
        data="Test data row $idx - $(date +%s)"
        checksum=$(echo -n "${uuid}${data}" | md5sum | awk '{print $1}')

        values+="('$uuid', '$data', '$checksum')"
        [ $i -lt $batch_size ] && values+=","
    done

    if ! sql_exec "$PRIMARY_POD" "${sql}${values};"; then
        log_error "Falha ao inserir lote $batch"
        exit 1
    fi

    [ $((batch % 10)) -eq 0 ] && echo -n "."
done
echo ""
log_success "Insercao concluida: $TEST_ROWS registros"

log_info "Criando indices adicionais..."
sql_exec "$PRIMARY_POD" "
ALTER TABLE $TEST_DB.$TEST_TABLE
ADD INDEX idx_checksum (checksum);
"

log_info "Calculando checksum global da tabela..."
ORIGINAL_CHECKSUM=$(sql_exec "$PRIMARY_POD" "
SELECT MD5(GROUP_CONCAT(CONCAT(id, uuid, data, checksum) ORDER BY id)) as global_checksum
FROM $TEST_DB.$TEST_TABLE;
")

log_success "Dados criados. Checksum global: $ORIGINAL_CHECKSUM"

log_info "Verificando replicacao nos nos secundarios..."
sleep 5

for pod in $SECONDARY_POD $TERTIARY_POD; do
    count=$(sql_exec "$pod" "SELECT COUNT(*) FROM $TEST_DB.$TEST_TABLE;")
    if [ "$count" = "$TEST_ROWS" ]; then
        log_success "$pod: $count registros (replicado corretamente)"
    else
        log_error "$pod: $count registros (esperado: $TEST_ROWS)"
    fi
done

# ============================================================
# FASE 3: BACKUP
# ============================================================

section "FASE 3: EXECUCAO DO BACKUP"

log_info "Executando backup no pod $BACKUP_POD"
log_info "Best Practice: Usando volume /backup dedicado"

kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
  mkdir -p $BACKUP_DIR
"

log_info "Executando mariadb-backup..."
kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
  mariadb-backup \
    --backup \
    --user=root \
    --password='$MYSQL_ROOT_PASSWORD' \
    --target-dir=$BACKUP_DIR \
    --stream=mbstream \
    --parallel=2 > $BACKUP_DIR/backup.mbstream && \
  echo 'Backup completado' && \
  ls -lh $BACKUP_DIR/backup.mbstream
" || { log_error "Backup falhou"; exit 1; }

backup_size=$(kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- sh -c "wc -c < '$BACKUP_DIR/backup.mbstream'")
if [ "$backup_size" -lt 1000000 ]; then
    log_error "Backup muito pequeno: $backup_size bytes"
    exit 1
fi
log_success "Backup validado: $backup_size bytes"

# ============================================================
# FASE 4: PREPARACAO DO BACKUP
# ============================================================

section "FASE 4: PREPARACAO DO BACKUP"

log_info "Executando mariadb-backup --prepare..."

kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
cd $BACKUP_DIR && \
mbstream -x < backup.mbstream && \
mariadb-backup \
  --prepare \
  --target-dir=$BACKUP_DIR
"

if [ $? -eq 0 ]; then
    log_success "Backup preparado e pronto para restore!"
else
    log_error "Falha na preparacao do backup!"
    exit 1
fi

# ============================================================
# FASE 5: SIMULACAO DE PERDA DE DADOS
# ============================================================

section "FASE 5: SIMULACAO DE PERDA DE DADOS"

log_warning "Simulando perda de dados..."

sql_exec "$PRIMARY_POD" "DELETE FROM $TEST_DB.$TEST_TABLE ORDER BY id LIMIT $((TEST_ROWS / 2));"

corrupted_count=$(sql_exec "$PRIMARY_POD" "SELECT COUNT(*) FROM $TEST_DB.$TEST_TABLE;")
log_warning "Registros apos corrupcao: $corrupted_count (original: $TEST_ROWS)"

sleep 5

for pod in $SECONDARY_POD $TERTIARY_POD; do
    count=$(sql_exec "$pod" "SELECT COUNT(*) FROM $TEST_DB.$TEST_TABLE;")
    log_warning "$pod: $count registros (corrupcao replicada)"
done

# ============================================================
# FASE 6: RESTORE
# ============================================================

section "FASE 6: EXECUCAO DO RESTORE"

log_info "ATENCAO: O restore requer parar o cluster!"
log_warning "Em producao, isso causaria downtime!"

log_info "Parando StatefulSet..."
kubectl scale statefulset mariadb -n "$NAMESPACE" --replicas=0

log_info "Aguardando pods serem terminados..."
kubectl wait --for=delete pod/$PRIMARY_POD -n "$NAMESPACE" --timeout=300s || true
kubectl wait --for=delete pod/$SECONDARY_POD -n "$NAMESPACE" --timeout=300s || true
kubectl wait --for=delete pod/$TERTIARY_POD -n "$NAMESPACE" --timeout=300s || true

log_info "Executando restore no PVC do $PRIMARY_POD..."

RESTORE_JOB_NAME="mariadb-restore-test-$(date +%s)"

cat <<EOF | kubectl apply -f -
apiVersion: batch/v1
kind: Job
metadata:
  name: $RESTORE_JOB_NAME
  namespace: $NAMESPACE
  labels:
    app: mariadb
    component: restore-test
spec:
  template:
    spec:
      serviceAccountName: mariadb-backup
      containers:
      - name: restore-test
        image: mariadb:10.11.15-jammy
        command:
        - /bin/bash
        - -c
        - |
          set -euo pipefail

          echo "=== INICIANDO RESTORE DE TESTE ==="
          echo "Timestamp: \$(date)"
          echo "Source: $BACKUP_DIR"

          # Verificar se backup existe
          if [ ! -f "$BACKUP_DIR/backup.mbstream" ]; then
            echo "ERROR: Arquivo de backup não encontrado!"
            echo "Conteudo do volume /backup:"
            ls -la /backup/ || echo "Volume /backup nao montado"
            exit 1
          fi

          echo "Limpando datadir antigo..."
          find /var/lib/mysql -mindepth 1 -delete

          echo "Executando mariadb-backup --copy-back..."
          mariadb-backup \\
            --copy-back \\
            --target-dir=$BACKUP_DIR \\
            --datadir=/var/lib/mysql

          echo "Corrigindo permissoes..."
          chown -R 999:999 /var/lib/mysql

          echo "SUCCESS: Restore completado!"
          ls -lh /var/lib/mysql/
          exit 0
        env:
        - name: BACKUP_DIR
          value: "$BACKUP_DIR"
        securityContext:
          runAsUser: 0
        volumeMounts:
        - name: mysql-data
          mountPath: /var/lib/mysql
        - name: backup-storage
          mountPath: /backup
        resources:
          requests:
            memory: "1Gi"
            cpu: "500m"
      restartPolicy: Never
      volumes:
      - name: mysql-data
        persistentVolumeClaim:
          claimName: database-mariadb-0
      - name: backup-storage
        persistentVolumeClaim:
          claimName: backup-mariadb-0
EOF

log_info "Aguardando conclusao do restore (timeout: 900s)..."

if kubectl wait --for=condition=complete --timeout=900s -n "$NAMESPACE" job/$RESTORE_JOB_NAME; then
    log_success "Restore job completado!"
    kubectl logs -n "$NAMESPACE" job/$RESTORE_JOB_NAME
else
    log_error "Restore job falhou!"
    kubectl logs -n "$NAMESPACE" job/$RESTORE_JOB_NAME
    exit 1
fi

log_success "Restore completado!"

# ============================================================
# FASE 6.5: PREPARAR BOOTSTRAP DO GALERA CLUSTER
# ============================================================

section "FASE 6.5: PREPARACAO BOOTSTRAP GALERA"

log_info "CRITICO: Configurando grastate.dat para bootstrap seguro..."
log_info "Isso permite que mariadb-0 inicie como nó primário do cluster"

BOOTSTRAP_JOB="fix-grastate-$(date +%s)"

cat <<EOF | kubectl apply -f -
apiVersion: batch/v1
kind: Job
metadata:
  name: $BOOTSTRAP_JOB
  namespace: $NAMESPACE
  labels:
    app: mariadb
    component: bootstrap-fix
spec:
  template:
    spec:
      containers:
      - name: fix-grastate
        image: busybox
        command:
        - sh
        - -c
        - |
          echo "Criando grastate.dat para bootstrap seguro..."
          cat > /data/grastate.dat <<GRASTATE
          # WSREP saved state
          version: 2.1
          uuid:    00000000-0000-0000-0000-000000000000
          seqno:   -1
          safe_to_bootstrap: 1
          GRASTATE
          echo "Conteudo de grastate.dat:"
          cat /data/grastate.dat
          echo "Bootstrap preparado!"
        volumeMounts:
        - name: data
          mountPath: /data
      restartPolicy: Never
      volumes:
      - name: data
        persistentVolumeClaim:
          claimName: database-mariadb-0
EOF

log_info "Aguardando conclusao do bootstrap fix..."
if kubectl wait --for=condition=complete job/$BOOTSTRAP_JOB -n "$NAMESPACE" --timeout=60s; then
    log_success "Bootstrap preparado!"
    kubectl logs -n "$NAMESPACE" job/$BOOTSTRAP_JOB
    kubectl delete job $BOOTSTRAP_JOB -n "$NAMESPACE"
else
    log_error "Falha ao preparar bootstrap!"
    kubectl logs -n "$NAMESPACE" job/$BOOTSTRAP_JOB
    exit 1
fi

# ============================================================
# FASE 7: REINICIAR CLUSTER GALERA
# ============================================================

section "FASE 7: REINICIANDO CLUSTER GALERA"

log_info "Iniciando mariadb-0 como bootstrap node..."
kubectl scale statefulset mariadb -n "$NAMESPACE" --replicas=1

log_info "Aguardando mariadb-0 iniciar como bootstrap..."
kubectl wait --for=condition=ready pod/$PRIMARY_POD -n "$NAMESPACE" --timeout=600s || {
    log_error "$PRIMARY_POD nao iniciou!"
    kubectl logs -n "$NAMESPACE" $PRIMARY_POD --tail=100
    exit 1
}

wait_for_sync "$PRIMARY_POD" || {
    log_error "$PRIMARY_POD nao sincronizou!"
    exit 1
}

log_success "mariadb-0 iniciado como nó primário do cluster!"

log_info "Iniciando nós secundários (mariadb-1 e mariadb-2)..."
log_info "Eles farão SST (State Snapshot Transfer) do mariadb-0..."
kubectl scale statefulset mariadb -n "$NAMESPACE" --replicas=3

log_info "Aguardando mariadb-1 fazer SST..."
kubectl wait --for=condition=ready pod/$SECONDARY_POD -n "$NAMESPACE" --timeout=600s || {
    log_warning "$SECONDARY_POD pode estar fazendo SST (isso pode demorar)..."
    sleep 30
}
wait_for_sync "$SECONDARY_POD" || log_warning "$SECONDARY_POD ainda sincronizando..."

log_info "Aguardando mariadb-2 fazer SST..."
kubectl wait --for=condition=ready pod/$TERTIARY_POD -n "$NAMESPACE" --timeout=600s || {
    log_warning "$TERTIARY_POD pode estar fazendo SST (isso pode demorar)..."
    sleep 30
}
wait_for_sync "$TERTIARY_POD" || log_warning "$TERTIARY_POD ainda sincronizando..."

log_info "Aguardando estabilização do cluster (30s)..."
sleep 30

check_cluster_health || {
    log_warning "Cluster ainda estabilizando. Tentando novamente em 60s..."
    sleep 60
    check_cluster_health || {
        log_error "Cluster nao esta saudavel apos restore!"
        log_info "Verificando logs dos pods..."
        for pod in $PRIMARY_POD $SECONDARY_POD $TERTIARY_POD; do
            echo "=== Logs de $pod ==="
            kubectl logs -n "$NAMESPACE" $pod --tail=50
        done
        exit 1
    }
}

log_success "Cluster Galera reiniciado e saudavel!"


# ============================================================
# FASE 8: VALIDACAO DE INTEGRIDADE
# ============================================================

section "FASE 8: VALIDACAO DE INTEGRIDADE"

log_info "Contando registros apos restore..."

restored_count=$(sql_exec "$PRIMARY_POD" "SELECT COUNT(*) FROM $TEST_DB.$TEST_TABLE;")

if [ "$restored_count" = "$TEST_ROWS" ]; then
    log_success "Contagem de registros: $restored_count (CORRETO)"
else
    log_error "Contagem de registros: $restored_count (esperado: $TEST_ROWS)"
fi

log_info "Calculando checksum global apos restore..."
RESTORED_CHECKSUM=$(sql_exec "$PRIMARY_POD" "
SELECT MD5(GROUP_CONCAT(CONCAT(id, uuid, data, checksum) ORDER BY id)) as global_checksum
FROM $TEST_DB.$TEST_TABLE;
")

if [ "$RESTORED_CHECKSUM" = "$ORIGINAL_CHECKSUM" ]; then
    log_success "Checksum: MATCH! ($RESTORED_CHECKSUM)"
    log_success "Integridade dos dados: 100% PRESERVADA!"
else
    log_error "Checksum: MISMATCH!"
    log_error "  Original:  $ORIGINAL_CHECKSUM"
    log_error "  Restored:  $RESTORED_CHECKSUM"
fi

log_info "Verificando replicacao nos nos secundarios..."
sleep 15

for pod in $SECONDARY_POD $TERTIARY_POD; do
    count=$(sql_exec "$pod" "SELECT COUNT(*) FROM $TEST_DB.$TEST_TABLE;")
    checksum=$(sql_exec "$pod" "
    SELECT MD5(GROUP_CONCAT(CONCAT(id, uuid, data, checksum) ORDER BY id)) as global_checksum
    FROM $TEST_DB.$TEST_TABLE;
    ")

    if [ "$count" = "$TEST_ROWS" ] && [ "$checksum" = "$ORIGINAL_CHECKSUM" ]; then
        log_success "$pod: $count registros, checksum OK"
    else
        log_error "$pod: $count registros, checksum: $checksum"
    fi
done

# ============================================================
# FASE 9: LIMPEZA
# ============================================================

section "FASE 9: LIMPEZA"

read -p "Deseja limpar os dados de teste? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    log_info "Removendo banco de teste..."
    sql_exec "$PRIMARY_POD" "DROP DATABASE IF EXISTS $TEST_DB;"

    log_info "Removendo diretorio de backup..."
    kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- rm -rf "$BACKUP_DIR"

    log_info "Removendo jobs de teste..."
    kubectl delete jobs -n "$NAMESPACE" -l component=restore-test 2>/dev/null || true
    kubectl delete jobs -n "$NAMESPACE" -l component=bootstrap-fix 2>/dev/null || true

    log_success "Limpeza concluida!"
else
    log_info "Mantendo dados de teste."
    log_info "Banco: $TEST_DB"
    log_info "Backup: $BACKUP_DIR"
fi

# ============================================================
# FASE 10: RELATORIO FINAL
# ============================================================

section "FASE 10: RELATORIO FINAL"

echo ""
echo "================================================================"
echo "           TESTE DE BACKUP/RESTORE - RELATORIO FINAL           "
echo "================================================================"
echo ""
echo "Namespace:              $NAMESPACE"
echo "Banco de Teste:         $TEST_DB"
echo "Registros Originais:    $TEST_ROWS"
echo "Registros Restaurados:  $restored_count"
echo "Checksum Original:      $ORIGINAL_CHECKSUM"
echo "Checksum Restaurado:    $RESTORED_CHECKSUM"
echo ""
echo "Cluster Galera:"
echo "  - Primary (Bootstrap): $PRIMARY_POD"
echo "  - Secondary (SST):     $SECONDARY_POD"
echo "  - Tertiary (SST):      $TERTIARY_POD"
echo ""

if [ "$restored_count" = "$TEST_ROWS" ] && [ "$RESTORED_CHECKSUM" = "$ORIGINAL_CHECKSUM" ]; then
    echo -e "${GREEN}SUCCESS: TESTE PASSOU!${NC}"
    echo -e "${GREEN}SUCCESS: Backup e Restore estao funcionando corretamente!${NC}"
    echo -e "${GREEN}SUCCESS: Integridade dos dados: 100% preservada${NC}"
    echo -e "${GREEN}SUCCESS: Cluster Galera sincronizado em todos os 3 nos${NC}"
    echo ""
    exit 0
else
    echo -e "${RED}ERROR: TESTE FALHOU!${NC}"
    echo -e "${RED}ERROR: Ha problemas com backup/restore!${NC}"
    echo ""
    exit 1
fi