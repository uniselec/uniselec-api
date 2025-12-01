#!/bin/bash
# ============================================================
# Script para criar Sealed Secrets do projeto UniSelec
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-01-19
# Versão: 1.0.0
# ============================================================

set -e

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # Sem Color

# Diretórios
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
KUSTOMIZE_DIR="${PROJECT_ROOT}/kustomize"
CERT_FILE="${PROJECT_ROOT}/public-key-cert.pem"

# Funções de log
log_info()    { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[ERROR]${NC} $1"; }
log_step()    { echo -e "${BLUE}[STEP]${NC} $1"; }

# ============================================================
# Função: Verificar dependências do ambiente
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
# Função: Obter certificado público do controlador Sealed Secrets
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
# Função: Criar e selar Secret para Docker Registry
# ============================================================
create_regcred_sealed_secret() {
    log_step "Criando Sealed Secret do Docker Registry (cluster-wide)..."
    echo ""
    echo -e "${GREEN}[INFO]${NC} === Credenciais do Docker Registry ==="

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

    TEMP_FILE=$(mktemp)
    cat > "${TEMP_FILE}" << EOF
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

    echo -e "${GREEN}[INFO]${NC} Secret temporário criado: ${TEMP_FILE}"

    local OUTPUT_FILE="${KUSTOMIZE_DIR}/base/sealed-secret-regcred.yaml"
    kubeseal --format=yaml --cert="${CERT_FILE}" < "$TEMP_FILE" > "$OUTPUT_FILE"
    echo -e "${GREEN}[INFO]${NC} Sealed secret criado: $OUTPUT_FILE"
    rm -f "$TEMP_FILE"
    echo -e "${GREEN}[INFO]${NC} Arquivo temporário removido"

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
    echo -e "${YELLOW}[WARN]${NC} Credenciais salvas em: $PASS_FILE"
}

# ============================================================
# Função: Criar e selar Secret do MariaDB por ambiente
# ============================================================
create_mariadb_env_sealed_secret() {
    local ENVIRONMENT=$1
    local NAMESPACE=$2

    log_step "Criando Sealed Secret do MariaDB para $ENVIRONMENT..."

    # Solicitar senhas
    echo ""
    echo -e "${GREEN}[INFO]${NC} === Senhas para $ENVIRONMENT ==="

    read -sp "Digite DB_PASSWORD (root) (ou Enter para gerar): " DB_PASS
    echo ""
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(generate_password)
        echo -e "${GREEN}[INFO]${NC} DB_PASSWORD gerada: $DB_PASS"
    fi

    read -sp "Digite MYSQL_ROOT_PASSWORD (ou Enter usar DB_PASSWORD): " MYSQL_ROOT_PASS
    echo ""
    if [ -z "$MYSQL_ROOT_PASS" ]; then
        MYSQL_ROOT_PASS="$DB_PASS"
        echo -e "${GREEN}[INFO]${NC} MYSQL_ROOT_PASSWORD = DB_PASSWORD"
    fi

    read -sp "Digite MYSQL_PASSWORD (user app) (ou Enter para gerar): " MYSQL_PASS
    echo ""
    if [ -z "$MYSQL_PASS" ]; then
        MYSQL_PASS=$(generate_password)
        echo -e "${GREEN}[INFO]${NC} MYSQL_PASSWORD gerada: $MYSQL_PASS"
    fi

    read -sp "Digite SST_PASSWORD (ou Enter para gerar): " SST_PASS
    echo ""
    if [ -z "$SST_PASS" ]; then
        SST_PASS=$(generate_password)
        echo -e "${GREEN}[INFO]${NC} SST_PASSWORD gerada: $SST_PASS"
    fi

    read -sp "Digite MARIADB_REPLICATION_PASSWORD (ou Enter para gerar): " REPL_PASS
    echo ""
    if [ -z "$REPL_PASS" ]; then
        REPL_PASS=$(generate_password)
        echo -e "${GREEN}[INFO]${NC} MARIADB_REPLICATION_PASSWORD gerada: $REPL_PASS"
    fi

    TEMP_FILE=$(mktemp)
    cat > "${TEMP_FILE}" << EOF
apiVersion: v1
kind: Secret
metadata:
  name: mariadb-secret-env
  namespace: ${NAMESPACE}
  annotations:
    sealedsecrets.bitnami.com/namespace-wide: "true"
type: Opaque
stringData:
  DB_USERNAME: "root"
  DB_PASSWORD: "${DB_PASS}"
  MYSQL_ROOT_PASSWORD: "${MYSQL_ROOT_PASS}"
  MYSQL_PASSWORD: "${MYSQL_PASS}"
  SST_USER: "sstuser"
  SST_PASSWORD: "${SST_PASS}"
  MARIADB_REPLICATION_PASSWORD: "${REPL_PASS}"
EOF

    log_info "Secret temporário criado: ${TEMP_FILE}"

    local OUTPUT_DIR="${KUSTOMIZE_DIR}/overlays/${ENVIRONMENT}"
    local OUTPUT_FILE="${OUTPUT_DIR}/sealed-secret-mariadb-credentials.yaml"
    kubeseal --format=yaml --cert="${CERT_FILE}" < "$TEMP_FILE" > "$OUTPUT_FILE"
    log_info "Sealed secret criado: $OUTPUT_FILE"
    rm -f "$TEMP_FILE"
    log_info "Arquivo temporário removido"

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
SST_USER=sstuser
SST_PASSWORD=${SST_PASS}
MARIADB_REPLICATION_PASSWORD=${REPL_PASS}
EOF
    chmod 600 "$PASS_FILE"
    echo -e "${YELLOW}[WARN]${NC} Senhas salvas em: $PASS_FILE"
}

# ============================================================
# Função: Criar e selar Secret do Laravel
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
        echo -e "${GREEN}[INFO]${NC} APP_KEY gerada: $APP_KEY"
    fi

    TEMP_FILE=$(mktemp)
    cat > "${TEMP_FILE}" << EOF
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

    log_info "Secret temporário criado: ${TEMP_FILE}"

    local OUTPUT_DIR="${KUSTOMIZE_DIR}/overlays/${ENVIRONMENT}"
    local OUTPUT_FILE="${OUTPUT_DIR}/sealed-secret-laravel-secrets.yaml"
    kubeseal --format=yaml --cert="${CERT_FILE}" < "$TEMP_FILE" > "$OUTPUT_FILE"
    log_info "Sealed secret criado: $OUTPUT_FILE"
    rm -f "$TEMP_FILE"
    log_info "Arquivo temporário removido"

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
    echo -e "${YELLOW}[WARN]${NC} APP_KEY salva em: $PASS_FILE"
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
    echo "2. Criar Sealed Secrets para STAGING"
    echo "3. Criar Sealed Secrets para PRODUCTION"
    echo "4. Criar TODOS os Sealed Secrets (staging + production)"
    echo "5. Apenas obter certificado público"
    echo "0. Sair"
    echo "=========================================="
}

# ============================================================
# Main
# ============================================================
main() {
    echo -e "${GREEN}[INFO]${NC} === Criador de Sealed Secrets - UniSelec ==="
    check_dependencies

    while true; do
        show_menu
        read -p "Escolha uma opção: " choice

        case $choice in
            1)
                get_public_cert
                create_regcred_sealed_secret
                echo -e "${GREEN}[INFO]${NC} Sealed Secret REGCRED criado!"
                ;;
            2)
                get_public_cert
                create_mariadb_env_sealed_secret "staging" "uniselec-api-stg"
                create_laravel_sealed_secret "staging" "uniselec-api-stg"
                echo -e "${GREEN}[INFO]${NC} Sealed Secrets STAGING criados!"
                ;;
            3)
                get_public_cert
                create_mariadb_env_sealed_secret "production" "uniselec-api-prd"
                create_laravel_sealed_secret "production" "uniselec-api-prd"
                echo -e "${GREEN}[INFO]${NC} Sealed Secrets PRODUCTION criados!"
                ;;
            4)
                get_public_cert
                create_regcred_sealed_secret
                create_mariadb_env_sealed_secret "staging" "uniselec-api-stg"
                create_laravel_sealed_secret "staging" "uniselec-api-stg"
                create_mariadb_env_sealed_secret "production" "uniselec-api-prd"
                create_laravel_sealed_secret "production" "uniselec-api-prd"
                echo -e "${GREEN}[INFO]${NC} TODOS os Sealed Secrets criados!"
                ;;
            5)
                get_public_cert
                ;;
            0)
                echo -e "${GREEN}[INFO]${NC} Saindo..."
                exit 0
                ;;
            *)
                echo -e "${RED}[ERROR]${NC} Opção inválida"
                ;;
        esac

        echo ""
        read -p "Pressione Enter para continuar..."
    done
}

# Executar
cd "$PROJECT_ROOT"
main