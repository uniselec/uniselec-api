#!/bin/bash

set -euo pipefail

NAMESPACE="${NAMESPACE:-uniselec-api-prd}"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-Password123}"

BACKUP_DIR="${1:-}"

LARAVEL_DEPLOYMENT="uniselec-api"

PRIMARY_POD="mariadb-0"
SECONDARY_POD="mariadb-1"
TERTIARY_POD="mariadb-2"

POD_DELETE_TIMEOUT=300
POD_READY_TIMEOUT=600
SST_TIMEOUT=900

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'


log_info() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} [INFO] $1"
}

log_success() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} [SUCCESS] $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} [WARNING] $1"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} [ERROR] $1"
}

section() {
    echo ""
    echo "================================================================"
    echo -e "${BLUE}$1${NC}"
    echo "================================================================"
}

check_cluster_health() {
    log_info "Verificando saúde do cluster Galera..."

    local all_healthy=true

    for pod in $PRIMARY_POD $SECONDARY_POD $TERTIARY_POD; do
        local state=$(kubectl exec -n "$NAMESPACE" "$pod" -- \
            mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e \
            "SHOW STATUS LIKE 'wsrep_local_state_comment'" 2>/dev/null | awk '{print $2}')

        local size=$(kubectl exec -n "$NAMESPACE" "$pod" -- \
            mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e \
            "SHOW STATUS LIKE 'wsrep_cluster_size'" 2>/dev/null | awk '{print $2}')

        local ready=$(kubectl exec -n "$NAMESPACE" "$pod" -- \
            mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e \
            "SHOW STATUS LIKE 'wsrep_ready'" 2>/dev/null | awk '{print $2}')

        if [ "$state" = "Synced" ] && [ "$size" = "3" ] && [ "$ready" = "ON" ]; then
            log_success "$pod: $state (cluster_size=$size, wsrep_ready=$ready)"
        else
            log_error "$pod: $state (cluster_size=$size, wsrep_ready=$ready) - NÃO saudável!"
            all_healthy=false
        fi
    done

    if $all_healthy; then
        return 0
    else
        return 1
    fi
}

wait_for_sync() {
    local pod="$1"
    local max_wait="${2:-300}"
    local elapsed=0

    log_info "Aguardando $pod sincronizar (timeout: ${max_wait}s)..."

    while [ $elapsed -lt $max_wait ]; do
        local state=$(kubectl exec -n "$NAMESPACE" "$pod" -- \
            mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e \
            "SHOW STATUS LIKE 'wsrep_local_state_comment'" 2>/dev/null | awk '{print $2}')

        local ready=$(kubectl exec -n "$NAMESPACE" "$pod" -- \
            mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e \
            "SHOW STATUS LIKE 'wsrep_ready'" 2>/dev/null | awk '{print $2}')

        if [ "$state" = "Synced" ] && [ "$ready" = "ON" ]; then
            log_success "$pod está Synced (${elapsed}s)"
            return 0
        fi

        echo -n "."
        sleep 5
        elapsed=$((elapsed + 5))
    done

    echo ""
    log_error "$pod NÃO sincronizou após ${max_wait}s"
    return 1
}


section "VALIDAÇÕES PRÉ-RESTORE"

if [ -z "$BACKUP_DIR" ]; then
    log_error "Uso: $0 <backup-dir>"
    log_error "Exemplo: $0 /backup/manual-20251130-140530"
    echo ""
    log_info "Para listar backups disponíveis:"
    echo "  kubectl exec -n $NAMESPACE mariadb-0 -- ls -lh /backup"
    exit 1
fi

if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
    log_error "MYSQL_ROOT_PASSWORD não definida!"
    log_error "Execute: export MYSQL_ROOT_PASSWORD=<sua-senha>"
    exit 1
fi

log_info "Validando backup em: $BACKUP_DIR"

BACKUP_SOURCE_POD=""
if kubectl exec -n "$NAMESPACE" "$SECONDARY_POD" -- test -d "$BACKUP_DIR" 2>/dev/null; then
    BACKUP_SOURCE_POD="$SECONDARY_POD"
    log_info "Backup encontrado em: $SECONDARY_POD"
elif kubectl exec -n "$NAMESPACE" "$PRIMARY_POD" -- test -d "$BACKUP_DIR" 2>/dev/null; then
    BACKUP_SOURCE_POD="$PRIMARY_POD"
    log_info "Backup encontrado em: $PRIMARY_POD"
elif kubectl exec -n "$NAMESPACE" "$TERTIARY_POD" -- test -d "$BACKUP_DIR" 2>/dev/null; then
    BACKUP_SOURCE_POD="$TERTIARY_POD"
    log_info "Backup encontrado em: $TERTIARY_POD"
else
    log_error "Diretório de backup não encontrado: $BACKUP_DIR"
    log_error "Procurado em: mariadb-0, mariadb-1, mariadb-2"
    echo ""
    log_info "Para listar backups disponíveis:"
    echo "  kubectl exec -n $NAMESPACE mariadb-1 -- ls -lh /backup"
    exit 1
fi

log_info "Verificando integridade do backup..."
VALIDATION_ERRORS=$(kubectl exec -n "$NAMESPACE" "$BACKUP_SOURCE_POD" -- bash -c "
cd $BACKUP_DIR

required_files=(
    'xtrabackup_checkpoints'
    'xtrabackup_info'
    'ibdata1'
)

errors=0
for file in \"\${required_files[@]}\"; do
    if [ ! -f \"\$file\" ]; then
        echo \"ERRO: Arquivo \$file não encontrado\"
        errors=\$((errors + 1))
    fi
done

exit \$errors
" 2>&1)

if [ $? -ne 0 ]; then
    log_error "Backup inválido ou corrompido:"
    echo "$VALIDATION_ERRORS"
    exit 1
fi

log_success "Backup válido"

log_info "Informações do backup:"
kubectl exec -n "$NAMESPACE" "$BACKUP_SOURCE_POD" -- cat "$BACKUP_DIR/xtrabackup_checkpoints" || true
kubectl exec -n "$NAMESPACE" "$BACKUP_SOURCE_POD" -- cat "$BACKUP_DIR/cluster-info.txt" 2>/dev/null || true


section "⚠️  AVISO CRÍTICO DE DOWNTIME ⚠️"

echo ""
log_warning "VOCÊ ESTÁ PRESTES A EXECUTAR UM RESTORE EM PRODUÇÃO"
echo ""
echo "  ⚠️  DOWNTIME TOTAL: 15-30 minutos estimados"
echo "  ⚠️  APLICAÇÃO SERÁ PARADA: $LARAVEL_DEPLOYMENT"
echo "  ⚠️  CLUSTER MARIADB SERÁ PARADO: 3 nós"
echo "  ⚠️  DADOS ATUAIS SERÃO SOBRESCRITOS"
echo ""
echo "  Namespace:      $NAMESPACE"
echo "  Backup Dir:     $BACKUP_DIR"
echo "  Restore em:     $PRIMARY_POD"
echo ""
log_warning "PROCEDIMENTO:"
echo "  1. Parar aplicação Laravel"
echo "  2. Fazer backup de segurança dos dados atuais"
echo "  3. Parar cluster MariaDB (3 nós)"
echo "  4. Restaurar dados do backup"
echo "  5. Configurar bootstrap do Galera"
echo "  6. Iniciar mariadb-0 (bootstrap)"
echo "  7. Iniciar mariadb-1 e mariadb-2 (SST)"
echo "  8. Validar cluster"
echo "  9. Reiniciar aplicação"
echo ""
log_warning "Este processo � IRREVERSÍVEL sem um backup"
echo ""

read -p "Você tem certeza ABSOLUTA? Digite 'RESTORE PRODUCTION' para continuar: " -r
if [[ $REPLY != "RESTORE PRODUCTION" ]]; then
    log_info "Restore cancelado pelo usuário"
    exit 0
fi

echo ""
log_warning "ÚLTIMA CONFIRMAÇÃO"
read -p "Confirmar restore em PRODUÇÃO? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    log_info "Restore cancelado"
    exit 0
fi


section "FASE 1: PARANDO APLICAÇÃO"

log_info "Escalando $LARAVEL_DEPLOYMENT para 0 r�plicas..."
kubectl scale deployment "$LARAVEL_DEPLOYMENT" -n "$NAMESPACE" --replicas=0

log_info "Aguardando pods da aplicação terminarem..."
kubectl wait --for=delete pod -l app="$LARAVEL_DEPLOYMENT" -n "$NAMESPACE" --timeout=120s || true

log_success "Aplicação parada"


section "FASE 2: BACKUP DE SEGURANÇA"

SAFETY_BACKUP_DIR="/backup/pre-restore-safety-$(date +%Y%m%d-%H%M%S)"

log_warning "CRÍTICO: Criando backup de segurança dos dados atuais"
log_info "Destino: $SAFETY_BACKUP_DIR"
log_info "Isso permite rollback em caso de problemas"

kubectl exec -n "$NAMESPACE" "$PRIMARY_POD" -- bash -c "
set -euo pipefail

mkdir -p $SAFETY_BACKUP_DIR

echo 'Copiando dados atuais para backup de segurança...'
if [ -d /var/lib/mysql ] && [ \"\$(ls -A /var/lib/mysql 2>/dev/null)\" ]; then
    cp /var/lib/mysql/grastate.dat $SAFETY_BACKUP_DIR/ 2>/dev/null || true
    cp /var/lib/mysql/ibdata1 $SAFETY_BACKUP_DIR/ 2>/dev/null || true
    cp /var/lib/mysql/xtrabackup_* $SAFETY_BACKUP_DIR/ 2>/dev/null || true

    echo 'Backup de segurança criado em: $(date)' > $SAFETY_BACKUP_DIR/info.txt
    echo 'Dados salvos antes de restore de: $BACKUP_DIR' >> $SAFETY_BACKUP_DIR/info.txt

    echo 'Backup de segurança completado'
else
    echo 'AVISO: Datadir vazio ou não encontrado'
fi
"

log_success "Backup de segurança criado em: $SAFETY_BACKUP_DIR"


section "FASE 3: PARANDO CLUSTER MARIADB"

log_info "Escalando StatefulSet mariadb para 0 r�plicas..."
kubectl scale statefulset mariadb -n "$NAMESPACE" --replicas=0

log_info "Aguardando pods do MariaDB terminarem..."
kubectl wait --for=delete pod/$PRIMARY_POD -n "$NAMESPACE" --timeout=${POD_DELETE_TIMEOUT}s || true
kubectl wait --for=delete pod/$SECONDARY_POD -n "$NAMESPACE" --timeout=${POD_DELETE_TIMEOUT}s || true
kubectl wait --for=delete pod/$TERTIARY_POD -n "$NAMESPACE" --timeout=${POD_DELETE_TIMEOUT}s || true

for pod in $PRIMARY_POD $SECONDARY_POD $TERTIARY_POD; do
    if kubectl get pod "$pod" -n "$NAMESPACE" &>/dev/null; then
        log_warning "$pod ainda existe, forçando delete..."
        kubectl delete pod "$pod" -n "$NAMESPACE" --force --grace-period=0 || true
    fi
done

sleep 10

log_success "Cluster MariaDB parado"


section "FASE 4: EXECUTANDO RESTORE"

log_info "Criando Job de restore no PVC do $PRIMARY_POD..."

RESTORE_JOB_NAME="mariadb-restore-production-$(date +%s)"

cat <<EOF | kubectl apply -f -
apiVersion: batch/v1
kind: Job
metadata:
  name: $RESTORE_JOB_NAME
  namespace: $NAMESPACE
  labels:
    app: mariadb
    component: restore-production
spec:
  template:
    spec:
      serviceAccountName: mariadb-backup
      containers:
      - name: restore-production
        image: mariadb:10.11.15-jammy
        command:
        - /bin/bash
        - -c
        - |
          set -euo pipefail

          echo "=== RESTORE DE PRODUÇÃO ==="
          echo "Timestamp: \$(date)"
          echo "Source: $BACKUP_DIR"
          echo "Target: /var/lib/mysql"

          if [ ! -f "$BACKUP_DIR/xtrabackup_checkpoints" ]; then
            echo "ERRO: Backup inválido - xtrabackup_checkpoints não encontrado"
            exit 1
          fi

          echo "Limpando datadir atual..."
          find /var/lib/mysql -mindepth 1 -delete

          echo "Executando mariadb-backup --copy-back..."
          mariadb-backup \
            --copy-back \
            --target-dir=$BACKUP_DIR \
            --datadir=/var/lib/mysql

          if [ \$? -ne 0 ]; then
            echo "ERRO: mariadb-backup --copy-back falhou!"
            exit 1
          fi

          echo "Corrigindo permissões..."
          chown -R 999:999 /var/lib/mysql

          echo "Validando restore..."
          if [ -f /var/lib/mysql/ibdata1 ] && [ -d /var/lib/mysql/mysql ]; then
            echo "SUCCESS: Restore completado!"
            ls -lh /var/lib/mysql/ | head -20
            exit 0
          else
            echo "ERRO: Restore incompleto!"
            exit 1
          fi
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
            memory: "2Gi"
            cpu: "1"
          limits:
            memory: "4Gi"
            cpu: "2"
      restartPolicy: Never
      volumes:
      - name: mysql-data
        persistentVolumeClaim:
          claimName: database-mariadb-0
      - name: backup-storage
        persistentVolumeClaim:
          claimName: backup-$BACKUP_SOURCE_POD
EOF

log_info "Aguardando conclusão do restore (timeout: ${SST_TIMEOUT}s)..."

if kubectl wait --for=condition=complete --timeout=${SST_TIMEOUT}s -n "$NAMESPACE" job/$RESTORE_JOB_NAME; then
    log_success "Restore job completado!"
    echo ""
    log_info "Logs do restore:"
    kubectl logs -n "$NAMESPACE" job/$RESTORE_JOB_NAME
    echo ""
else
    log_error "Restore job falhou ou timeout!"
    kubectl logs -n "$NAMESPACE" job/$RESTORE_JOB_NAME
    exit 1
fi

log_success "Restore completado!"


section "FASE 5: CONFIGURANDO BOOTSTRAP GALERA"

log_warning "CRÍTICO: Configurando grastate.dat para bootstrap seguro"
log_info "Isso permite que mariadb-0 inicie como nó primário"

BOOTSTRAP_JOB="galera-bootstrap-$(date +%s)"

cat <<EOF | kubectl apply -f -
apiVersion: batch/v1
kind: Job
metadata:
  name: $BOOTSTRAP_JOB
  namespace: $NAMESPACE
  labels:
    app: mariadb
    component: bootstrap
spec:
  template:
    spec:
      containers:
      - name: bootstrap
        image: busybox
        command:
        - sh
        - -c
        - |
          echo "Configurando grastate.dat para bootstrap..."
          cat > /data/grastate.dat <<'GRASTATE'
          version: 2.1
          uuid:    00000000-0000-0000-0000-000000000000
          seqno:   -1
          safe_to_bootstrap: 1
          GRASTATE

          echo "Conteúdo de grastate.dat:"
          cat /data/grastate.dat

          echo "Bootstrap configurado!"
        volumeMounts:
        - name: data
          mountPath: /data
      restartPolicy: Never
      volumes:
      - name: data
        persistentVolumeClaim:
          claimName: database-mariadb-0
EOF

log_info "Aguardando configuração do bootstrap..."
if kubectl wait --for=condition=complete job/$BOOTSTRAP_JOB -n "$NAMESPACE" --timeout=60s; then
    log_success "Bootstrap configurado!"
    kubectl logs -n "$NAMESPACE" job/$BOOTSTRAP_JOB
    kubectl delete job $BOOTSTRAP_JOB -n "$NAMESPACE"
else
    log_error "Falha ao configurar bootstrap!"
    kubectl logs -n "$NAMESPACE" job/$BOOTSTRAP_JOB
    exit 1
fi


section "FASE 6: INICIANDO MARIADB-0 (BOOTSTRAP)"

log_info "Iniciando mariadb-0 como nó primário do cluster..."
kubectl scale statefulset mariadb -n "$NAMESPACE" --replicas=1

log_info "Aguardando mariadb-0 ficar pronto (timeout: ${POD_READY_TIMEOUT}s)..."
if kubectl wait --for=condition=ready pod/$PRIMARY_POD -n "$NAMESPACE" --timeout=${POD_READY_TIMEOUT}s; then
    log_success "mariadb-0 iniciou!"
else
    log_error "mariadb-0 não iniciou no timeout!"
    kubectl logs -n "$NAMESPACE" $PRIMARY_POD --tail=100
    exit 1
fi

log_info "Aguardando mariadb-0 sincronizar..."
wait_for_sync "$PRIMARY_POD" 300 || {
    log_error "mariadb-0 não sincronizou!"
    kubectl logs -n "$NAMESPACE" $PRIMARY_POD --tail=50
    exit 1
}

log_success "mariadb-0 está Synced e pronto!"


section "FASE 7: INICIANDO MARIADB-1 E MARIADB-2 (SST)"

log_info "Iniciando nós secundários..."
log_info "Eles farão SST (State Snapshot Transfer) do mariadb-0"
kubectl scale statefulset mariadb -n "$NAMESPACE" --replicas=3

log_info "Aguardando mariadb-1 fazer SST (timeout: ${SST_TIMEOUT}s)..."
kubectl wait --for=condition=ready pod/$SECONDARY_POD -n "$NAMESPACE" --timeout=${SST_TIMEOUT}s || {
    log_warning "$SECONDARY_POD pode estar fazendo SST (processo lento)..."
    sleep 60
}

wait_for_sync "$SECONDARY_POD" 600 || {
    log_warning "$SECONDARY_POD ainda sincronizando, continuando..."
}

log_info "Aguardando mariadb-2 fazer SST (timeout: ${SST_TIMEOUT}s)..."
kubectl wait --for=condition=ready pod/$TERTIARY_POD -n "$NAMESPACE" --timeout=${SST_TIMEOUT}s || {
    log_warning "$TERTIARY_POD pode estar fazendo SST (processo lento)..."
    sleep 60
}

wait_for_sync "$TERTIARY_POD" 600 || {
    log_warning "$TERTIARY_POD ainda sincronizando, continuando..."
}

log_info "Aguardando estabilização do cluster (60s)..."
sleep 60


section "FASE 8: VALIDAÇÃO DO CLUSTER"

log_info "Verificando saúde final do cluster..."

if check_cluster_health; then
    log_success "Cluster Galera está saudável!"
else
    log_error "Cluster NÃO está completamente saudável"
    log_warning "Isso pode ser temporário durante SST"
    log_warning "Aguardando mais 2 minutos..."
    sleep 120

    if check_cluster_health; then
        log_success "Cluster agora está saudável!"
    else
        log_error "Cluster ainda não está saudável"
        log_error "Verifique os logs dos pods manualmente"

        for pod in $PRIMARY_POD $SECONDARY_POD $TERTIARY_POD; do
            echo ""
            echo "=== Logs de $pod ==="
            kubectl logs -n "$NAMESPACE" $pod --tail=30
        done

        exit 1
    fi
fi

log_info "Validando acesso aos dados restaurados..."
DATABASES=$(kubectl exec -n "$NAMESPACE" "$PRIMARY_POD" -- \
    mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SHOW DATABASES" 2>/dev/null | grep -v -E "^(information_schema|mysql|performance_schema|sys)$")

log_success "Databases encontrados:"
echo "$DATABASES"


section "FASE 9: REINICIANDO APLICAÇÃO"

log_info "Escalando $LARAVEL_DEPLOYMENT de volta..."
kubectl scale deployment "$LARAVEL_DEPLOYMENT" -n "$NAMESPACE" --replicas=1

log_info "Aguardando aplicação iniciar..."
kubectl wait --for=condition=available deployment/$LARAVEL_DEPLOYMENT -n "$NAMESPACE" --timeout=300s || {
    log_warning "Timeout aguardando aplicação"
}

log_success "Aplicação reiniciada!"


section "FASE 10: LIMPEZA"

log_info "Removendo Job de restore..."
kubectl delete job $RESTORE_JOB_NAME -n "$NAMESPACE" || true


section "RELATÓRIO FINAL DO RESTORE"

echo ""
log_success "RESTORE COMPLETADO!"
echo ""
echo "  Backup Restaurado:  $BACKUP_DIR"
echo "  Namespace:          $NAMESPACE"
echo "  Cluster MariaDB:    3 nós Synced"
echo "  Aplicação:          $LARAVEL_DEPLOYMENT (1 r�plica)"
echo ""
log_info "BACKUP DE SEGURANÇA (para rollback):"
echo "  $SAFETY_BACKUP_DIR"
echo ""
log_info "VALIDAÇÕES FINAIS RECOMENDADAS:"
echo "  1. Testar login na aplicação"
echo "  2. Verificar dados críticos no banco"
echo "  3. Monitorar logs da aplicação"
echo "  4. Verificar m�tricas de performance"
echo ""
log_info "Para verificar status do cluster:"
echo "  kubectl exec -n $NAMESPACE mariadb-0 -- mariadb -uroot -p... -e \"SHOW STATUS LIKE 'wsrep%'\""
echo ""
log_success "Restore finalizado!"