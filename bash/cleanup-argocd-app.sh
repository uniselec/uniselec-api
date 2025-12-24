#!/bin/bash

#===============================================================================
# Descrição: Remove Application do ArgoCD
# Uso: ./cleanup-argocd-app.sh <app-name>
# argocd app list ou kubectl get applicationset -A

# Sintaxe
# ./cleanup-argocd-app.sh <app-name> [opções]

# Remover application completa
# ./cleanup-argocd-app.sh selecoes-stg

# Remover mas manter namespace
# ./cleanup-argocd-app.sh uniselec-admin-stg --keep-namespace

# Ver o que seria feito (dry-run)
# ./cleanup-argocd-app.sh selecoes-dev --dry-run

# Ver ajuda
# ./cleanup-argocd-app.sh --help
#===============================================================================

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configurações
ARGOCD_NAMESPACE="argocd"
ARGOCD_SERVER="${ARGOCD_SERVER:-argocd.unilab.edu.br}"
TIMEOUT=60

#===============================================================================
# Funções auxiliares
#===============================================================================

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_dependencies() {
    local missing_deps=0

    if ! command -v kubectl &>/dev/null; then
        log_error "kubectl não está instalado"
        missing_deps=1
    fi

    if ! command -v argocd &>/dev/null; then
        log_warn "argocd CLI não está instalado (opcional)"
    fi

    if ! command -v jq &>/dev/null; then
        log_error "jq não está instalado (necessário para remover finalizers de namespace)"
        missing_deps=1
    fi

    if [ $missing_deps -eq 1 ]; then
        log_error "Dependências faltando. Instale e tente novamente."
        exit 1
    fi
}

verify_app_exists() {
    local app_name=$1

    if ! kubectl get application "$app_name" -n "$ARGOCD_NAMESPACE" &>/dev/null; then
        log_error "Application '$app_name' não encontrada no ArgoCD"
        return 1
    fi
    return 0
}

get_app_info() {
    local app_name=$1

    log_info "Coletando informações da Application '$app_name'..."

    # Obter namespace destino
    APP_NAMESPACE=$(kubectl get application "$app_name" -n "$ARGOCD_NAMESPACE" \
        -o jsonpath='{.spec.destination.namespace}' 2>/dev/null)

    # Obter cluster destino
    APP_CLUSTER=$(kubectl get application "$app_name" -n "$ARGOCD_NAMESPACE" \
        -o jsonpath='{.spec.destination.server}' 2>/dev/null)

    # Obter status
    APP_SYNC_STATUS=$(kubectl get application "$app_name" -n "$ARGOCD_NAMESPACE" \
        -o jsonpath='{.status.sync.status}' 2>/dev/null)

    APP_HEALTH_STATUS=$(kubectl get application "$app_name" -n "$ARGOCD_NAMESPACE" \
        -o jsonpath='{.status.health.status}' 2>/dev/null)

    log_info "  Namespace: $APP_NAMESPACE"
    log_info "  Cluster: $APP_CLUSTER"
    log_info "  Sync Status: $APP_SYNC_STATUS"
    log_info "  Health Status: $APP_HEALTH_STATUS"
}

remove_application() {
    local app_name=$1

    log_info "Removendo Application '$app_name'..."

    # Tentar via ArgoCD CLI primeiro (se disponível)
    if command -v argocd &>/dev/null; then
        log_info "Tentando remover via ArgoCD CLI..."
        if argocd app delete "$app_name" \
            --cascade=true \
            --server "$ARGOCD_SERVER" \
            --grpc-web \
            --insecure \
            --yes 2>/dev/null; then
            log_success "Application removida via ArgoCD CLI"
            sleep 5
            return 0
        else
            log_warn "Falha ao remover via ArgoCD CLI, tentando kubectl..."
        fi
    fi

    # Remover finalizers da Application
    log_info "Removendo finalizers da Application..."
    kubectl patch application "$app_name" -n "$ARGOCD_NAMESPACE" \
        -p '{"metadata":{"finalizers":[]}}' \
        --type=merge 2>/dev/null || true

    # Deletar Application
    log_info "Deletando Application..."
    kubectl delete application "$app_name" -n "$ARGOCD_NAMESPACE" \
        --force \
        --grace-period=0 2>/dev/null || true

    # Aguardar remoção
    local count=0
    while kubectl get application "$app_name" -n "$ARGOCD_NAMESPACE" &>/dev/null; do
        if [ $count -ge $TIMEOUT ]; then
            log_error "Timeout ao aguardar remoção da Application"
            return 1
        fi
        sleep 2
        count=$((count + 2))
    done

    log_success "Application removida"
}

remove_applicationset() {
    local app_name=$1
    local appset_name="${app_name}-as"

    # Verificar se ApplicationSet existe
    if ! kubectl get applicationset "$appset_name" -n "$ARGOCD_NAMESPACE" &>/dev/null; then
        log_info "ApplicationSet '$appset_name' não encontrado (OK)"
        return 0
    fi

    log_info "Removendo ApplicationSet '$appset_name'..."

    # Remover finalizers
    kubectl patch applicationset "$appset_name" -n "$ARGOCD_NAMESPACE" \
        -p '{"metadata":{"finalizers":[]}}' \
        --type=merge 2>/dev/null || true

    # Deletar ApplicationSet
    kubectl delete applicationset "$appset_name" -n "$ARGOCD_NAMESPACE" \
        --force \
        --grace-period=0 2>/dev/null || true

    # Aguardar remoção
    local count=0
    while kubectl get applicationset "$appset_name" -n "$ARGOCD_NAMESPACE" &>/dev/null; do
        if [ $count -ge $TIMEOUT ]; then
            log_error "Timeout ao aguardar remoção do ApplicationSet"
            return 1
        fi
        sleep 2
        count=$((count + 2))
    done

    log_success "ApplicationSet removido"
}

remove_namespace() {
    local namespace=$1

    if [ -z "$namespace" ]; then
        log_warn "Namespace não identificado, pulando remoção"
        return 0
    fi

    # Verificar se namespace existe
    if ! kubectl get namespace "$namespace" &>/dev/null; then
        log_info "Namespace '$namespace' não encontrado (OK)"
        return 0
    fi

    log_info "Removendo namespace '$namespace'..."

    # Deletar namespace
    kubectl delete namespace "$namespace" --force --grace-period=0 2>/dev/null || true

    # Aguardar e forçar se necessário
    sleep 5

    if kubectl get namespace "$namespace" &>/dev/null; then
        log_warn "Namespace em estado Terminating, forçando remoção de finalizers..."

        # Remover finalizers do namespace
        kubectl get namespace "$namespace" -o json | \
            jq '.spec.finalizers = []' | \
            kubectl replace --raw "/api/v1/namespaces/$namespace/finalize" -f - 2>/dev/null || true

        sleep 3

        # Verificar novamente
        if kubectl get namespace "$namespace" &>/dev/null; then
            log_error "Namespace '$namespace' ainda existe (pode estar em Terminating)"
            log_info "Execute manualmente: kubectl get namespace $namespace -o yaml"
            return 1
        fi
    fi

    log_success "Namespace removido"
}

verify_cleanup() {
    local app_name=$1
    local namespace=$2
    local errors=0

    echo ""
    log_info "Verificando limpeza..."
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    # Verificar Application
    if kubectl get application "$app_name" -n "$ARGOCD_NAMESPACE" &>/dev/null; then
        log_error "Application '$app_name' ainda existe"
        errors=$((errors + 1))
    else
        log_success "Application '$app_name' removida"
    fi

    # Verificar ApplicationSet
    if kubectl get applicationset "${app_name}-as" -n "$ARGOCD_NAMESPACE" &>/dev/null; then
        log_error "ApplicationSet '${app_name}-as' ainda existe"
        errors=$((errors + 1))
    else
        log_success "ApplicationSet '${app_name}-as' removido"
    fi

    # Verificar Namespace
    if [ -n "$namespace" ]; then
        if kubectl get namespace "$namespace" &>/dev/null; then
            log_error "Namespace '$namespace' ainda existe"
            errors=$((errors + 1))
        else
            log_success "Namespace '$namespace' removido"
        fi
    fi

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    if [ $errors -eq 0 ]; then
        log_success "Limpeza completa! Nenhum fragmento encontrado."
        return 0
    else
        log_error "Limpeza incompleta. $errors item(ns) ainda existem."
        return 1
    fi
}

show_usage() {
    cat << EOF
Uso: $0 <app-name> [opções]

Opções:
  --keep-namespace    Mantém o namespace (não deleta recursos do cluster)
  --dry-run           Mostra o que seria feito sem executar
  -h, --help          Mostra esta mensagem de ajuda

Exemplos:
  $0 selecoes-stg
  $0 uniselec-admin-stg --keep-namespace
  $0 selecoes-dev --dry-run

Descrição:
  Remove completamente uma Application do ArgoCD incluindo:
  - Application
  - ApplicationSet (se existir)
  - Namespace e recursos do cluster (a menos que --keep-namespace seja usado)

EOF
}

#===============================================================================
# Main
#===============================================================================

main() {
    local app_name=""
    local keep_namespace=false
    local dry_run=false

    # Parse argumentos
    while [[ $# -gt 0 ]]; do
        case $1 in
            -h|--help)
                show_usage
                exit 0
                ;;
            --keep-namespace)
                keep_namespace=true
                shift
                ;;
            --dry-run)
                dry_run=true
                shift
                ;;
            -*)
                log_error "Opção desconhecida: $1"
                show_usage
                exit 1
                ;;
            *)
                app_name="$1"
                shift
                ;;
        esac
    done

    # Validar argumentos
    if [ -z "$app_name" ]; then
        log_error "Nome da Application não fornecido"
        show_usage
        exit 1
    fi

    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  ArgoCD Application Cleanup"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    log_info "Application: $app_name"
    log_info "Keep Namespace: $keep_namespace"
    log_info "Dry Run: $dry_run"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""

    # Verificar dependências
    check_dependencies

    # Verificar se Application existe
    if ! verify_app_exists "$app_name"; then
        exit 1
    fi

    # Obter informações da Application
    get_app_info "$app_name"

    if [ "$dry_run" = true ]; then
        echo ""
        log_info "DRY RUN - As seguintes ações seriam executadas:"
        echo "  1. Remover Application: $app_name"
        echo "  2. Remover ApplicationSet: ${app_name}-as (se existir)"
        if [ "$keep_namespace" = false ]; then
            echo "  3. Remover Namespace: $APP_NAMESPACE"
        else
            echo "  3. Manter Namespace: $APP_NAMESPACE"
        fi
        exit 0
    fi

    # Confirmação
    echo ""
    log_warn "ATENÇÃO: Esta operação irá remover:"
    echo "  - Application: $app_name"
    echo "  - ApplicationSet: ${app_name}-as (se existir)"
    if [ "$keep_namespace" = false ]; then
        echo "  - Namespace e todos os recursos: $APP_NAMESPACE"
    fi
    echo ""
    read -p "Deseja continuar? (sim/não): " -r
    echo ""

    if [[ ! $REPLY =~ ^[Ss][Ii][Mm]$ ]]; then
        log_info "Operação cancelada pelo usuário"
        exit 0
    fi

    # Executar remoção
    echo ""
    log_info "Iniciando remoção..."
    echo ""

    # 1. Remover Application
    if ! remove_application "$app_name"; then
        log_error "Falha ao remover Application"
        exit 1
    fi

    # 2. Remover ApplicationSet
    remove_applicationset "$app_name"

    # 3. Remover Namespace (se não --keep-namespace)
    if [ "$keep_namespace" = false ]; then
        remove_namespace "$APP_NAMESPACE"
    else
        log_info "Mantendo namespace '$APP_NAMESPACE' conforme solicitado"
    fi

    # 4. Verificar limpeza
    echo ""
    verify_cleanup "$app_name" "$APP_NAMESPACE"
}

# Executar
main "$@"
