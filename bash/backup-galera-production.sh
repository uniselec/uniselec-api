#!/bin/bash
# ============================================================
# BACKUP MANUAL - MariaDB Galera Cluster (PRODUÇÃO)
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-11-30
# Versão: 1.0.0
# ============================================================
#
# Script para backup manual de cluster MariaDB Galera em produção
#
# IMPORTANTE: Este script executa backup SEM downtime
#
# Baseado em:
# - MariaDB Backup Documentation: https://mariadb.com/kb/en/mariabackup/
# - Galera Cluster Best Practices
# - Script de teste validado: test-backup-restore-complete.sh
#
# ============================================================
# INSTRUÇÕES DE USO - BACKUP MANUAL
# ============================================================
# 1 Definir senha
# export MYSQL_ROOT_PASSWORD="password_aqui"
# 2 Definir namespace
# export NAMESPACE="uniselec-api-prd"
# 3 Executar backup
# bash backup-galera-production.sh
# ============================================================

set -euo pipefail

# ============================================================
# CONFIGURAÇÕES
# ============================================================

# Namespace do cluster
NAMESPACE="${NAMESPACE:-uniselec-api-prd}"

# Credenciais (via environment variables ou secret)
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-Password123}"

# Diretório de backup com timestamp
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
BACKUP_DIR="/backup/manual-${TIMESTAMP}"

# Pod onde será executado o backup (SEMPRE usar secundário, NUNCA o primário)
BACKUP_POD="mariadb-1"  # Nó secundário para minimizar impacto

# Configurações de compressão e paralelização
PARALLEL_THREADS=2
COMPRESS_THREADS=2

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============================================================
# FUNÇÕES AUXILIARES
# ============================================================

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

# Função para verificar se pod está saudável
check_pod_health() {
    local pod="$1"

    log_info "Verificando saúde do pod $pod..."

    # Verificar se pod existe e está rodando
    if ! kubectl get pod "$pod" -n "$NAMESPACE" &>/dev/null; then
        log_error "Pod $pod não encontrado!"
        return 1
    fi

    local status=$(kubectl get pod "$pod" -n "$NAMESPACE" -o jsonpath='{.status.phase}')
    if [ "$status" != "Running" ]; then
        log_error "Pod $pod não está rodando (status: $status)"
        return 1
    fi

    # Verificar se MariaDB está respondendo
    if ! kubectl exec -n "$NAMESPACE" "$pod" -- \
        mariadb-admin ping -u root -p"$MYSQL_ROOT_PASSWORD" --silent 2>/dev/null; then
        log_error "MariaDB no pod $pod não está respondendo"
        return 1
    fi

    # Verificar estado do Galera
    local state=$(kubectl exec -n "$NAMESPACE" "$pod" -- \
        mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e \
        "SHOW STATUS LIKE 'wsrep_local_state_comment'" 2>/dev/null | awk '{print $2}')

    local cluster_size=$(kubectl exec -n "$NAMESPACE" "$pod" -- \
        mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e \
        "SHOW STATUS LIKE 'wsrep_cluster_size'" 2>/dev/null | awk '{print $2}')

    if [ "$state" != "Synced" ]; then
        log_error "Pod $pod não está sincronizado (estado: $state)"
        return 1
    fi

    if [ "$cluster_size" != "3" ]; then
        log_warning "Cluster size é $cluster_size (esperado: 3)"
    fi

    log_success "Pod $pod está saudável (estado: $state, cluster_size: $cluster_size)"
    return 0
}

# ============================================================
# VALIDAÇÕES PRÉ-BACKUP
# ============================================================

section "VALIDAÇÕES PRÉ-BACKUP"

# Validar variável de senha
if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
    log_error "MYSQL_ROOT_PASSWORD não definida!"
    log_error "Execute: export MYSQL_ROOT_PASSWORD=<sua-senha>"
    exit 1
fi

# Validar que estamos fazendo backup em nó secundário
if [ "$BACKUP_POD" = "mariadb-0" ]; then
    log_error "NUNCA execute backup no mariadb-0 (nó primário) em produção!"
    log_error "Use mariadb-1 ou mariadb-2 para minimizar impacto"
    exit 1
fi

# Verificar saúde do pod de backup
check_pod_health "$BACKUP_POD" || {
    log_error "Pod $BACKUP_POD não está saudável. Abortando backup."
    exit 1
}

# Verificar espaço disponível
log_info "Verificando espaço disponível no volume de backup..."
AVAILABLE_SPACE=$(kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- \
    df -BG /backup | tail -1 | awk '{print $4}' | sed 's/G//')

if [ "$AVAILABLE_SPACE" -lt 50 ]; then
    log_error "Espaço insuficiente: ${AVAILABLE_SPACE}G disponível (mínimo: 50G)"
    log_error "Limpe backups antigos antes de continuar"
    exit 1
fi

log_success "Espaço disponível: ${AVAILABLE_SPACE}G"

# Listar backups existentes
log_info "Backups existentes:"
kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c '
    if [ -d /backup ]; then
        ls -lh /backup | grep "^d" | tail -5 || echo "Nenhum backup anterior encontrado"
    fi
'

# ============================================================
# CONFIRMAÇÃO DO USUÁRIO
# ============================================================

section "CONFIRMAÇÃO"

echo ""
log_warning "Você está prestes a executar um backup manual de PRODUÇÃO"
echo ""
echo "  Namespace:      $NAMESPACE"
echo "  Pod de Backup:  $BACKUP_POD"
echo "  Backup Dir:     $BACKUP_DIR"
echo "  Timestamp:      $TIMESTAMP"
echo "  Compressão:     Habilitada (--stream=mbstream)"
echo "  Paralelização:  $PARALLEL_THREADS threads"
echo ""
log_warning "O backup será executado SEM downtime (non-blocking)"
log_info "Tempo estimado: 5-15 minutos (dependendo do tamanho do banco)"
echo ""

read -p "Deseja continuar? (yes/no): " -r
if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
    log_info "Backup cancelado pelo usuário"
    exit 0
fi

# ============================================================
# EXECUÇÃO DO BACKUP
# ============================================================

section "EXECUÇÃO DO BACKUP"

log_info "Criando diretório de backup: $BACKUP_DIR"
kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- mkdir -p "$BACKUP_DIR"

# Salvar metadados do cluster ANTES do backup
log_info "Salvando metadados do cluster Galera..."
kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
cat > $BACKUP_DIR/cluster-info.txt <<'CLUSTER_INFO'
Backup Timestamp: $TIMESTAMP
Backup Pod: $BACKUP_POD
Namespace: $NAMESPACE

=== CLUSTER STATUS ===
CLUSTER_INFO

mariadb -uroot -p'$MYSQL_ROOT_PASSWORD' -e \"
SELECT NOW() as backup_time;
SHOW STATUS LIKE 'wsrep%';
\" >> $BACKUP_DIR/cluster-info.txt 2>/dev/null

mariadb -uroot -p'$MYSQL_ROOT_PASSWORD' -e \"
SELECT table_schema,
       ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.TABLES
WHERE table_schema NOT IN ('information_schema', 'mysql', 'performance_schema', 'sys')
GROUP BY table_schema;
\" >> $BACKUP_DIR/cluster-info.txt 2>/dev/null
"

log_success "Metadados salvos"

# Executar mariadb-backup
log_info "Executando mariadb-backup (streaming + compressão)..."
log_info "IMPORTANTE: Este processo NÃO bloqueia o banco de dados"

BACKUP_START=$(date +%s)

kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
set -euo pipefail

echo '[BACKUP] Iniciando mariadb-backup...'

# Executar backup com streaming (economia de espaço)
mariadb-backup \
  --backup \
  --user=root \
  --password='$MYSQL_ROOT_PASSWORD' \
  --target-dir=$BACKUP_DIR \
  --stream=mbstream \
  --parallel=$PARALLEL_THREADS > $BACKUP_DIR/backup.mbstream

if [ \$? -eq 0 ]; then
    echo '[BACKUP] Backup stream completado'

    # Calcular checksum para validação
    md5sum $BACKUP_DIR/backup.mbstream > $BACKUP_DIR/backup.mbstream.md5

    # Registrar tamanho do backup
    ls -lh $BACKUP_DIR/backup.mbstream | awk '{print \$5}' > $BACKUP_DIR/backup.size

    echo '[BACKUP] Checksum calculado'
else
    echo '[BACKUP] ERRO: Backup falhou!'
    exit 1
fi
" 2>&1 | tee /tmp/backup-${TIMESTAMP}.log

BACKUP_STATUS=$?
BACKUP_END=$(date +%s)
BACKUP_DURATION=$((BACKUP_END - BACKUP_START))

if [ $BACKUP_STATUS -ne 0 ]; then
    log_error "Backup falhou! Verifique os logs acima"
    exit 1
fi

log_success "Backup completado em ${BACKUP_DURATION} segundos"

# ============================================================
# PREPARAÇÃO DO BACKUP
# ============================================================

section "PREPARAÇÃO DO BACKUP"

log_info "Preparando backup para restore futuro..."
log_info "Este passo garante consistência dos dados"

kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
set -euo pipefail

echo '[PREPARE] Extraindo backup stream...'
cd $BACKUP_DIR
mbstream -x < backup.mbstream

echo '[PREPARE] Executando mariadb-backup --prepare...'
mariadb-backup \
  --prepare \
  --target-dir=$BACKUP_DIR

if [ \$? -eq 0 ]; then
    echo '[PREPARE] Backup preparado'

    # Validar que xtrabackup_checkpoints existe
    if [ -f $BACKUP_DIR/xtrabackup_checkpoints ]; then
        echo '[PREPARE] xtrabackup_checkpoints encontrado'
        cat $BACKUP_DIR/xtrabackup_checkpoints
    else
        echo '[PREPARE] ERRO: xtrabackup_checkpoints não encontrado!'
        exit 1
    fi
else
    echo '[PREPARE] ERRO: Preparação falhou!'
    exit 1
fi
"

if [ $? -ne 0 ]; then
    log_error "Preparação do backup falhou!"
    exit 1
fi

log_success "Backup preparado e pronto para restore"

# ============================================================
# VALIDAÇÃO DO BACKUP
# ============================================================

section "VALIDAÇÃO DO BACKUP"

log_info "Validando integridade do backup..."

# Validar checksum
kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
cd $BACKUP_DIR
if md5sum -c backup.mbstream.md5; then
    echo '[VALIDATE] Checksum MD5: OK'
else
    echo '[VALIDATE] ERRO: Checksum inválido!'
    exit 1
fi
"

if [ $? -ne 0 ]; then
    log_error "Validação de checksum falhou!"
    exit 1
fi

# Validar arquivos críticos
VALIDATION_RESULT=$(kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- bash -c "
cd $BACKUP_DIR

# Arquivos obrigatórios
required_files=(
    'xtrabackup_checkpoints'
    'xtrabackup_info'
    'xtrabackup_binlog_info'
    'ibdata1'
)

missing_files=()
for file in \"\${required_files[@]}\"; do
    if [ ! -f \"\$file\" ]; then
        missing_files+=(\"\$file\")
    fi
done

if [ \${#missing_files[@]} -eq 0 ]; then
    echo 'OK'
else
    echo \"MISSING: \${missing_files[*]}\"
    exit 1
fi
")

if [[ "$VALIDATION_RESULT" != "OK" ]]; then
    log_error "Validação falhou: $VALIDATION_RESULT"
    exit 1
fi

log_success "Backup validado"

# ============================================================
# INFORMAÇÕES DO BACKUP
# ============================================================

section "INFORMAÇÕES DO BACKUP"

BACKUP_SIZE=$(kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- cat "$BACKUP_DIR/backup.size")

echo ""
log_success "BACKUP COMPLETADO"
echo ""
echo "  Backup ID:      $TIMESTAMP"
echo "  Localização:    $BACKUP_DIR"
echo "  Pod:            $BACKUP_POD"
echo "  Tamanho:        $BACKUP_SIZE"
echo "  Duração:        ${BACKUP_DURATION}s"
echo "  Checksum MD5:   Validado"
echo ""

# Salvar informações do backup em arquivo local
cat > "backup-${TIMESTAMP}.info" <<INFO
Backup Manual - MariaDB Galera Cluster
========================================

Timestamp:      $TIMESTAMP
Namespace:      $NAMESPACE
Pod:            $BACKUP_POD
Backup Dir:     $BACKUP_DIR
Tamanho:        $BACKUP_SIZE
Duração:        ${BACKUP_DURATION}s
Status:         SUCCESS

Restore Command:
----------------
bash restore-galera-production.sh $BACKUP_DIR

Backup Location (within pod):
------------------------------
kubectl exec -n $NAMESPACE $BACKUP_POD -- ls -lh $BACKUP_DIR

Verificar Backup:
-----------------
kubectl exec -n $NAMESPACE $BACKUP_POD -- cat $BACKUP_DIR/xtrabackup_checkpoints
INFO

log_info "Informações salvas em: backup-${TIMESTAMP}.info"

# ============================================================
# LIMPEZA DE BACKUPS ANTIGOS (OPCIONAL)
# ============================================================

section "LIMPEZA DE BACKUPS ANTIGOS"

log_info "Verificando backups com mais de 7 dias..."

OLD_BACKUPS=$(kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- \
    find /backup -maxdepth 1 -name "manual-*" -type d -mtime +7 2>/dev/null | wc -l)

if [ "$OLD_BACKUPS" -gt 0 ]; then
    echo ""
    log_warning "Encontrados $OLD_BACKUPS backup(s) com mais de 7 dias"
    echo ""

    read -p "Deseja remover backups antigos? (yes/no): " -r
    if [[ $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        log_info "Removendo backups antigos..."
        kubectl exec -n "$NAMESPACE" "$BACKUP_POD" -- \
            find /backup -maxdepth 1 -name "manual-*" -type d -mtime +7 -exec rm -rf {} \;
        log_success "Backups antigos removidos"
    else
        log_info "Mantendo backups antigos"
    fi
else
    log_info "Nenhum backup antigo encontrado (> 7 dias)"
fi

# ============================================================
# RECOMENDAÇÕES FINAIS
# ============================================================

section "RECOMENDAÇÕES"

echo ""
log_info "PRÓXIMOS PASSOS RECOMENDADOS:"
echo ""
echo "  1. Testar o backup em ambiente de staging"
echo "  2. Documentar este backup no sistema de tickets"
echo "  3. Notificar equipe sobre backup disponível"
echo "  4. Verificar espaço disponível periodicamente"
echo ""
log_info "Para listar todos os backups disponíveis:"
echo "  kubectl exec -n $NAMESPACE $BACKUP_POD -- ls -lh /backup"
echo ""
log_info "Para restaurar este backup:"
echo "  bash restore-galera-production.sh $BACKUP_DIR"
echo ""

log_success "Backup finalizado!"