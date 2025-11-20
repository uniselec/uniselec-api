#!/bin/bash
# ============================================================
# Script para criar Sealed Secrets do projeto UniSelec
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-01-19
# Versão: 1.0.3
# ============================================================

set -e

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Diretórios
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
KUSTOMIZE_DIR="${PROJECT_ROOT}/kustomize"
CERT_FILE="${PROJECT_ROOT}/public-key-cert.pem"

log_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# ============================================================
# Função: Verificar dependências
# ============================================================
check_dependencies() {
    log_step "Verificando dependências..."

    if ! command -v kubectl &> /dev/null; then
        log_error "kubectl não encontrado. Instale: https://kubernetes.io/docs/tasks/tools/"
        exit 1
    fi

    if ! command -v kubeseal &> /dev/null; then
        log_error "kubeseal não encontrado. Instale: https://github.com/bitnami-labs/sealed-secrets"
        exit 1
    fi

    log_info "Dependências OK ✓"
}

# ============================================================
# Função: Gerar senha forte
# ============================================================
generate_password() {
    local length=${1:-32}
    openssl rand -base64 $length | tr -d "=+/" | cut -c1-24
}

# ============================================================
# Função: Obter certificado público
# ============================================================
get_public_cert() {
    log_step "Obtendo certificado público do Sealed Secrets..."

    if [ -f "${CERT_FILE}" ]; then
        log_warn "Certificado público já existe. Reutilizando..."
        return 0
    fi

    kubeseal --fetch-cert > "${CERT_FILE}"

    if [ ! -f "${CERT_FILE}" ]; then
        log_error "Falha ao obter certificado público"
        exit 1
    fi

    log_info "Certificado público obtido: ${CERT_FILE}"
}

# ============================================================
# Função: Criar Sealed Secret para Docker Registry
# ============================================================
create_regcred_sealed_secret() {
    log_step "Criando Sealed Secret do Docker Registry (cluster-wide)..."

    echo ""
    log_info "=== Credenciais do Docker Registry ==="

    read -p "Digite o Docker Registry Server (padrão: dti-registro.unilab.edu.br): " DOCKER_SERVER
    DOCKER_SERVER=${DOCKER_SERVER:-dti-registro.unilab.edu.br}

    read -p "Digite o Docker Registry Username: " DOCKER_USERNAME
    if [ -z "$DOCKER_USERNAME" ]; then
        log_error "Username é obrigatório!"
        return 1
    fi

    read -sp "Digite o Docker Registry Password: " DOCKER_PASSWORD
    echo ""
    if [ -z "$DOCKER_PASSWORD" ]; then
        log_error "Password é obrigatório!"
        return 1
    fi

    read -p "Digite o Docker Registry Email (padrão: devops@unilab.edu.br): " DOCKER_EMAIL
    DOCKER_EMAIL=${DOCKER_EMAIL:-devops@unilab.edu.br}

    # Criar .dockerconfigjson
    DOCKER_AUTH=$(echo -n "${DOCKER_USERNAME}:${DOCKER_PASSWORD}" | base64 -w 0)
    DOCKER_CONFIG_JSON=$(cat <<EOF
{
  "auths": {
    "${DOCKER_SERVER}": {
      "username": "${DOCKER_USERNAME}",
      "password": "${DOCKER_PASSWORD}",
      "email": "${DOCKER_EMAIL}",
      "auth": "${DOCKER_AUTH}"
    }
  }
}
EOF
)

    # Criar Secret temporário em memória e selar diretamente
    local OUTPUT_FILE="${KUSTOMIZE_DIR}/base/sealed-secret-regcred.yaml"

    cat << EOF | kubeseal --format=yaml --cert="${CERT_FILE}" > "$OUTPUT_FILE"
apiVersion: v1
kind: Secret
metadata:
  name: regcred
  annotations:
    sealedsecrets.bitnami.com/cluster-wide: "true"
type: kubernetes.io/dockerconfigjson
data:
  .dockerconfigjson: $(echo -n "$DOCKER_CONFIG_JSON" | base64 -w 0)
EOF

    log_info "Sealed secret criado: $OUTPUT_FILE"

    # Salvar credenciais
    local PASS_FILE="${PROJECT_ROOT}/passwords-regcred.txt"
    cat > "$PASS_FILE" << EOF
# ============================================================
# CREDENCIAIS DO DOCKER REGISTRY (CLUSTER-WIDE)
# Geradas em: $(date)
# ============================================================

DOCKER_SERVER=${DOCKER_SERVER}
DOCKER_USERNAME=${DOCKER_USERNAME}
DOCKER_PASSWORD=${DOCKER_PASSWORD}
DOCKER_EMAIL=${DOCKER_EMAIL}
EOF

    chmod 600 "$PASS_FILE"
    log_warn "Credenciais salvas em: $PASS_FILE"
}

# ============================================================
# Função: Criar Sealed Secret Base do MariaDB
# ============================================================
create_mariadb_base_sealed_secret() {
    log_step "Criando Sealed Secret base do MariaDB (cluster-wide)..."

    # Solicitar senhas
    echo ""
    read -sp "Digite a senha do MYSQL_ROOT_PASSWORD (ou Enter para gerar): " MYSQL_ROOT_PASS
    echo ""
    if [ -z "$MYSQL_ROOT_PASS" ]; then
        MYSQL_ROOT_PASS=$(generate_password)
        log_info "MYSQL_ROOT_PASSWORD gerada: $MYSQL_ROOT_PASS"
    fi

    read -sp "Digite a senha do MYSQL_PASSWORD (ou Enter para gerar): " MYSQL_PASS
    echo ""
    if [ -z "$MYSQL_PASS" ]; then
        MYSQL_PASS=$(generate_password)
        log_info "MYSQL_PASSWORD gerada: $MYSQL_PASS"
    fi

    read -sp "Digite a senha do SST_PASSWORD (ou Enter para gerar): " SST_PASS
    echo ""
    if [ -z "$SST_PASS" ]; then
        SST_PASS=$(generate_password)
        log_info "SST_PASSWORD gerada: $SST_PASS"
    fi

    # Criar Sealed Secret diretamente
    local OUTPUT_FILE="${KUSTOMIZE_DIR}/base/mariadb/sealed-secret-mariadb.yaml"

    cat << EOF | kubeseal --format=yaml --cert="${CERT_FILE}" > "$OUTPUT_FILE"
apiVersion: v1
kind: Secret
metadata:
  name: mariadb-secret-env
  annotations:
    sealedsecrets.bitnami.com/cluster-wide: "true"
type: Opaque
stringData:
  MYSQL_ROOT_PASSWORD: "${MYSQL_ROOT_PASS}"
  MYSQL_PASSWORD: "${MYSQL_PASS}"
  SST_PASSWORD: "${SST_PASS}"
  SST_USER: "sstuser"
  MARIADB_REPLICATION_PASSWORD: "${MYSQL_ROOT_PASS}"
EOF

    log_info "Sealed secret criado: $OUTPUT_FILE"

    # Salvar senhas em arquivo seguro
    local PASS_FILE="${PROJECT_ROOT}/passwords-mariadb-base.txt"
    cat > "$PASS_FILE" << EOF
# ============================================================
# SENHAS DO MARIADB BASE (CLUSTER-WIDE)
# Geradas em: $(date)
# ============================================================
# ATENÇÃO: Guarde este arquivo em local seguro!
# ============================================================

MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASS}
MYSQL_PASSWORD=${MYSQL_PASS}
SST_PASSWORD=${SST_PASS}
SST_USER=sstuser
MARIADB_REPLICATION_PASSWORD=${MYSQL_ROOT_PASS}
EOF

    chmod 600 "$PASS_FILE"
    log_warn "Senhas salvas em: $PASS_FILE"
}

# ============================================================
# Função: Criar Sealed Secret do MariaDB por ambiente
# ============================================================
create_mariadb_env_sealed_secret() {
    local ENVIRONMENT=$1
    local NAMESPACE=$2

    log_step "Criando Sealed Secret do MariaDB para $ENVIRONMENT..."

    # Solicitar senhas
    echo ""
    log_info "=== Senhas para $ENVIRONMENT ==="

    read -sp "Digite DB_PASSWORD (root) (ou Enter para gerar): " DB_PASS
    echo ""
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(generate_password)
        log_info "DB_PASSWORD gerada: $DB_PASS"
    fi

    read -sp "Digite MYSQL_ROOT_PASSWORD (ou Enter usar DB_PASSWORD): " MYSQL_ROOT_PASS
    echo ""
    if [ -z "$MYSQL_ROOT_PASS" ]; then
        MYSQL_ROOT_PASS="$DB_PASS"
        log_info "MYSQL_ROOT_PASSWORD = DB_PASSWORD"
    fi

    read -sp "Digite MYSQL_PASSWORD (user app) (ou Enter para gerar): " MYSQL_PASS
    echo ""
    if [ -z "$MYSQL_PASS" ]; then
        MYSQL_PASS=$(generate_password)
        log_info "MYSQL_PASSWORD gerada: $MYSQL_PASS"
    fi

    read -p "Digite MYSQL_USER (padrão: uniselec_${ENVIRONMENT}_user): " MYSQL_USER
    if [ -z "$MYSQL_USER" ]; then
        case $ENVIRONMENT in
            staging)
                MYSQL_USER="uniselec_stag_user"
                ;;
            production)
                MYSQL_USER="uniselec_prod_user"
                ;;
        esac
        log_info "MYSQL_USER: $MYSQL_USER"
    fi

    read -sp "Digite SST_PASSWORD (ou Enter para gerar): " SST_PASS
    echo ""
    if [ -z "$SST_PASS" ]; then
        SST_PASS=$(generate_password)
        log_info "SST_PASSWORD gerada: $SST_PASS"
    fi

    # Criar Sealed Secret diretamente
    local OUTPUT_DIR="${KUSTOMIZE_DIR}/overlays/${ENVIRONMENT}"
    local OUTPUT_FILE="${OUTPUT_DIR}/sealed-secret-mariadb-credentials.yaml"

    cat << EOF | kubeseal --format=yaml --cert="${CERT_FILE}" > "$OUTPUT_FILE"
apiVersion: v1
kind: Secret
metadata:
  name: mariadb-credentials
  namespace: ${NAMESPACE}
  annotations:
    sealedsecrets.bitnami.com/namespace-wide: "true"
type: Opaque
stringData:
  DB_USERNAME: "root"
  DB_PASSWORD: "${DB_PASS}"
  MYSQL_ROOT_PASSWORD: "${MYSQL_ROOT_PASS}"
  MYSQL_PASSWORD: "${MYSQL_PASS}"
  MYSQL_USER: "${MYSQL_USER}"
  SST_PASSWORD: "${SST_PASS}"
EOF

    log_info "Sealed secret criado: $OUTPUT_FILE"

    # Salvar senhas
    local PASS_FILE="${PROJECT_ROOT}/passwords-mariadb-${ENVIRONMENT}.txt"
    cat > "$PASS_FILE" << EOF
# ============================================================
# SENHAS DO MARIADB - ${ENVIRONMENT^^}
# Namespace: ${NAMESPACE}
# Geradas em: $(date)
# ============================================================

DB_USERNAME=root
DB_PASSWORD=${DB_PASS}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASS}
MYSQL_PASSWORD=${MYSQL_PASS}
MYSQL_USER=${MYSQL_USER}
SST_PASSWORD=${SST_PASS}
EOF

    chmod 600 "$PASS_FILE"
    log_warn "Senhas salvas em: $PASS_FILE"
}

# ============================================================
# Função: Criar Sealed Secret do Laravel
# ============================================================
create_laravel_sealed_secret() {
    local ENVIRONMENT=$1
    local NAMESPACE=$2

    log_step "Criando Sealed Secret do Laravel para $ENVIRONMENT..."

    echo ""
    read -p "Digite APP_KEY do Laravel (ou Enter para gerar): " APP_KEY
    if [ -z "$APP_KEY" ]; then
        # Gerar APP_KEY no formato Laravel
        APP_KEY="base64:$(openssl rand -base64 32)"
        log_info "APP_KEY gerada: $APP_KEY"
    fi

    # Criar Sealed Secret diretamente
    local OUTPUT_DIR="${KUSTOMIZE_DIR}/overlays/${ENVIRONMENT}"
    local OUTPUT_FILE="${OUTPUT_DIR}/sealed-secret-laravel-secrets.yaml"

    cat << EOF | kubeseal --format=yaml --cert="${CERT_FILE}" > "$OUTPUT_FILE"
apiVersion: v1
kind: Secret
metadata:
  name: laravel-secrets
  namespace: ${NAMESPACE}
  annotations:
    sealedsecrets.bitnami.com/namespace-wide: "true"
type: Opaque
stringData:
  APP_KEY: "${APP_KEY}"
EOF

    log_info "Sealed secret criado: $OUTPUT_FILE"

    # Salvar APP_KEY
    local PASS_FILE="${PROJECT_ROOT}/passwords-laravel-${ENVIRONMENT}.txt"
    cat > "$PASS_FILE" << EOF
# ============================================================
# APP_KEY DO LARAVEL - ${ENVIRONMENT^^}
# Namespace: ${NAMESPACE}
# Gerada em: $(date)
# ============================================================

APP_KEY=${APP_KEY}
EOF

    chmod 600 "$PASS_FILE"
    log_warn "APP_KEY salva em: $PASS_FILE"
}

# ============================================================
# Menu principal
# ============================================================
show_menu() {
    echo ""
    echo "=========================================="
    echo "  Criar Sealed Secrets - UniSelec"
    echo "=========================================="
    echo "1. Criar Sealed Secret REGCRED (cluster-wide)"
    echo "2. Criar Sealed Secret BASE do MariaDB (cluster-wide)"
    echo "3. Criar Sealed Secrets para STAGING"
    echo "4. Criar Sealed Secrets para PRODUCTION"
    echo "5. Criar TODOS os Sealed Secrets (base + ambientes)"
    echo "6. Apenas obter certificado público"
    echo "0. Sair"
    echo "=========================================="
}

# ============================================================
# Main
# ============================================================
main() {
    log_info "=== Criador de Sealed Secrets - UniSelec ==="

    check_dependencies

    while true; do
        show_menu
        read -p "Escolha uma opção: " choice

        case $choice in
            1)
                get_public_cert
                create_regcred_sealed_secret
                log_info "✅ Sealed Secret REGCRED criado com sucesso!"
                ;;
            2)
                get_public_cert
                create_mariadb_base_sealed_secret
                log_info "✅ Sealed Secret BASE do MariaDB criado com sucesso!"
                ;;
            3)
                get_public_cert
                create_mariadb_env_sealed_secret "staging" "uniselec-api-stg"
                create_laravel_sealed_secret "staging" "uniselec-api-stg"
                log_info "✅ Sealed Secrets STAGING criados com sucesso!"
                ;;
            4)
                get_public_cert
                create_mariadb_env_sealed_secret "production" "uniselec-api"
                create_laravel_sealed_secret "production" "uniselec-api"
                log_info "✅ Sealed Secrets PRODUCTION criados com sucesso!"
                ;;
            5)
                get_public_cert
                create_regcred_sealed_secret
                create_mariadb_base_sealed_secret
                create_mariadb_env_sealed_secret "staging" "uniselec-api-stg"
                create_laravel_sealed_secret "staging" "uniselec-api-stg"
                create_mariadb_env_sealed_secret "production" "uniselec-api"
                create_laravel_sealed_secret "production" "uniselec-api"
                log_info "✅ TODOS os Sealed Secrets criados com sucesso!"
                ;;
            6)
                get_public_cert
                ;;
            0)
                log_info "Saindo..."
                exit 0
                ;;
            *)
                log_error "Opção inválida"
                ;;
        esac

        echo ""
        read -p "Pressione Enter para continuar..."
    done
}

# Executar
cd "$PROJECT_ROOT"
main