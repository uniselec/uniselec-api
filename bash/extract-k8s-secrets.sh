#!/bin/bash
# ============================================================
# Script: extract-k8s-secrets.sh
# Descricao: Gerencia o ciclo completo de SealedSecrets dos projetos
#            UNISELEC (admin, api, selecoes):
#              - Extrai Secrets decriptografados do cluster K8s e
#                grava secret-*.yaml ao lado de cada sealed-secret-*.yaml
#              - Re-sela secret-*.yaml editados, regenerando os
#                sealed-secret-*.yaml prontos para commit no GitOps
#              - Migra SealedSecrets entre clusters usando cert externo
#                (--cert), sem necessidade de acesso ao cluster destino
# ============================================================
# Pre-requisitos:
#   - kubectl configurado e com acesso ao cluster
#   - python3 + PyYAML (pip install pyyaml)
#   - kubeseal (para --method offline, --reseal ou --cert)
#
# Uso:
#   chmod +x extract-k8s-secrets.sh
#   ./extract-k8s-secrets.sh [OPCOES]
#
# Opcoes:
#   --method <kubectl|offline>   Metodo de extracao (padrao: kubectl)
#   --env    <all|dev|stg|prd>   Filtro de ambiente  (padrao: all)
#   --reseal                     Re-sela secret-*.yaml => sealed-secret-*.yaml
#   --cert   <arquivo.pem>       Cert publico externo para selar (pula fetch do cluster)
#   --dry-run                    Exibe o mapeamento sem gravar nada
#   --base-dir <caminho>         Diretorio raiz dos projetos UNISELEC
#   -h, --help                   Exibe esta ajuda
#
# Metodos:
#   kubectl  (padrao) -- busca o Secret ja decriptografado
#                        diretamente do cluster via kubectl.
#   offline           -- exporta a chave privada RSA do controller
#                        e decriptografa os SealedSecrets localmente.
#
# Fluxos:
#
#   A) Extracao padrao (cluster acessivel via kubectl):
#      1. Extrair:  ./extract-k8s-secrets.sh --env prd
#      2. Editar os secret-*.yaml gerados conforme necessario
#      3. Revisar:  ./extract-k8s-secrets.sh --reseal --env prd --dry-run
#      4. Re-selar: ./extract-k8s-secrets.sh --reseal --env prd
#      5. Commit:   git add sealed-secret-*.yaml && git commit
#
#   B) Extracao offline (sem acesso direto ao cluster):
#      1. Exportar chave privada e decriptografar localmente:
#           ./extract-k8s-secrets.sh --method offline --env prd
#      2. Editar os secret-*.yaml gerados conforme necessario
#      3. Revisar:  ./extract-k8s-secrets.sh --reseal --env prd --dry-run
#      4. Re-selar: ./extract-k8s-secrets.sh --reseal --env prd
#      5. Commit:   git add sealed-secret-*.yaml && git commit
#
#   C) Migracao entre clusters (cert do cluster legado -> cert do cluster novo):
#      Cenario: re-selar todos os SealedSecrets com o certificado publico
#      do novo cluster, sem precisar de acesso kubectl ao cluster destino.
#      Arquivos de cert: bash/tmp/sc-pub-cert-red.pem (legado)
#                        bash/tmp/sc-pub-cert-for.pem (novo cluster)
#
#      1. Decriptografar do cluster legado (offline com chave privada):
#           ./extract-k8s-secrets.sh --method offline --env prd
#         Ou via kubectl se ainda tiver acesso ao legado:
#           ./extract-k8s-secrets.sh --env prd
#      2. Revisar (dry-run com cert do novo cluster):
#           ./extract-k8s-secrets.sh --reseal --cert ./tmp/sc-pub-cert-for.pem --env prd --dry-run
#      3. Re-selar com cert do novo cluster:
#           ./extract-k8s-secrets.sh --reseal --cert ./tmp/sc-pub-cert-for.pem --env prd
#      4. Commit:   git add sealed-secret-*.yaml && git commit
#
# Saida -- cada secret-*.yaml fica NO MESMO DIRETORIO do
# sealed-secret-*.yaml correspondente:
#
#   uniselec-admin/kustomize/base/
#     sealed-secret-regcred.yaml   (encriptado, git)
#     secret-regcred.yaml          (texto claro, gitignore)
#
#   uniselec-api/kustomize/overlays/staging/
#     sealed-secret-laravel-secrets.yaml     (git)
#     sealed-secret-mariadb-credentials.yaml (git)
#     secret-laravel-secrets.yaml            (gitignore)
#     secret-mariadb-secret-env.yaml         (gitignore)  <- nome vem do metadata.name
#
#   uniselec-api/kustomize/overlays/production/
#     sealed-secret-laravel-secrets.yaml     (git)
#     sealed-secret-mariadb-credentials.yaml (git)
#     secret-laravel-secrets.yaml            (gitignore)
#     secret-mariadb-secret-env.yaml         (gitignore)  <- nome vem do metadata.name
#
#   uniselec-selecoes/kustomize/base/
#     sealed-secret-regcred.yaml   (encriptado, git)
#     secret-regcred.yaml          (texto claro, gitignore)
#
#   ATENCAO: Nunca faca commit dos arquivos secret-*.yaml!
# ============================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log_info()  { echo -e "${GREEN}[INFO]${NC}  $1"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC}  $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1" >&2; }
log_step()  { echo -e "\n${BLUE}${BOLD}[STEP]${NC}  $1"; }
log_ok()    { echo -e "${GREEN}${BOLD}  OK${NC}  $1"; }
log_skip()  { echo -e "${CYAN}  --${NC}  $1 ${CYAN}(ignorado)${NC}"; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PRIVATE_KEY_FILE="$(mktemp /tmp/sealed-secrets-key-XXXXXX.pem)"
PUBLIC_CERT_FILE="$(mktemp /tmp/sealed-secrets-cert-XXXXXX.pem)"
BASE_DIR=""

trap 'rm -f "${PRIVATE_KEY_FILE}" "${PUBLIC_CERT_FILE}"' EXIT

detect_base_dir() {
  local candidate
  candidate="$SCRIPT_DIR"
  local level=0
  while [[ "$candidate" != "/" && $level -lt 5 ]]; do
    candidate="$(dirname "$candidate")"
    (( level++ )) || true
    if [[ -d "${candidate}/uniselec-api" ]]; then
      echo "$candidate"; return
    fi
  done
  echo "$(dirname "$SCRIPT_DIR")"
}

# Namespaces por ambiente
declare -A PROJECT_NS_PRD=(
  ["uniselec-admin"]="uniselec-admin-prd"
  ["uniselec-api"]="uniselec-api-prd"
  ["uniselec-selecoes"]="selecoes-prd"
)
declare -A PROJECT_NS_STG=(
  ["uniselec-admin"]="uniselec-admin-stg"
  ["uniselec-api"]="uniselec-api-stg"
  ["uniselec-selecoes"]="selecoes-stg"
)
declare -A PROJECT_NS_DEV=(
  ["uniselec-admin"]="uniselec-admin-dev"
  ["uniselec-api"]="uniselec-api-dev"
  ["uniselec-selecoes"]="selecoes-dev"
)

# Defaults
METHOD="kubectl"
ENV_FILTER="all"
DRY_RUN=false
RESEAL=false
CERT_FILE=""

# help/usage
usage() {
  echo ""
  echo -e "${BOLD}Uso:${NC} $(basename "$0") [--method <kubectl|offline>] [--env <all|dev|stg|prd>] [--reseal] [--cert <arquivo.pem>] [--dry-run]"
  echo ""
  echo "  --method    kubectl (padrao) ou offline"
  echo "  --env       all (padrao) | dev | stg | prd"
  echo "  --reseal    Re-sela secret-*.yaml => sealed-secret-*.yaml (requer kubeseal)"
  echo "  --cert      Certificado publico .pem externo para selar (pula fetch do cluster)"
  echo "  --dry-run   Exibe mapeamento sem gravar nada"
  echo "  --base-dir  Diretorio raiz dos projetos UNISELEC"
  echo ""
  echo -e "${BOLD}Exemplos:${NC}"
  echo "  $(basename "$0")"
  echo "  $(basename "$0") --dry-run"
  echo "  $(basename "$0") --env prd"
  echo "  $(basename "$0") --method offline --env prd"
  echo "  $(basename "$0") --reseal --env prd"
  echo "  $(basename "$0") --reseal --env prd --dry-run"
  echo "  $(basename "$0") --base-dir /home/user/dti/uniselec --dry-run"
  echo ""
  echo -e "${BOLD}Migracao entre clusters (--cert):${NC}"
  echo "  $(basename "$0") --reseal --cert ./tmp/sc-pub-cert-for.pem --env prd --dry-run"
  echo "  $(basename "$0") --reseal --cert ./tmp/sc-pub-cert-for.pem --env prd"
  echo ""
  exit 0
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --method)
      [[ -z "${2:-}" ]] && { log_error "--method requer um valor: kubectl ou offline"; usage; }
      METHOD="$2"; shift 2 ;;
    --env)
      [[ -z "${2:-}" ]] && { log_error "--env requer um valor: all, dev, stg ou prd"; usage; }
      ENV_FILTER="$2"; shift 2 ;;
    --dry-run)   DRY_RUN=true; shift ;;
    --reseal)    RESEAL=true; shift ;;
    --cert)
      [[ -z "${2:-}" ]] && { log_error "--cert requer o caminho para o arquivo .pem"; usage; }
      CERT_FILE="$2"; shift 2 ;;
    --base-dir)
      [[ -z "${2:-}" ]] && { log_error "--base-dir requer um caminho"; usage; }
      BASE_DIR="$2"; shift 2 ;;
    -h|--help)   usage ;;
    *) log_error "Argumento desconhecido: $1"; usage ;;
  esac
done

if [[ -z "$BASE_DIR" ]]; then
  BASE_DIR="$(detect_base_dir)"
else
  BASE_DIR="$(cd "$BASE_DIR" && pwd)"
fi

if [[ ! -d "${BASE_DIR}/uniselec-api" ]]; then
  echo -e "\033[0;31m[ERROR]\033[0m Nao foi possivel localizar o projeto uniselec-api."
  echo -e "        BASE_DIR: ${BASE_DIR}"
  echo -e "        Use --base-dir <caminho> para definir o diretorio raiz explicitamente."
  echo -e "        Exemplo: --base-dir /home/jefponte/dti/uniselec"
  exit 1
fi

declare -A PROJECT_KUSTOMIZE_DIR=(
  ["uniselec-admin"]="${BASE_DIR}/uniselec-admin/kustomize"
  ["uniselec-api"]="${BASE_DIR}/uniselec-api/kustomize"
  ["uniselec-selecoes"]="${BASE_DIR}/uniselec-selecoes/kustomize"
)

[[ "$METHOD" != "kubectl" && "$METHOD" != "offline" ]] && {
  log_error "Metodo invalido: $METHOD. Use kubectl ou offline."; exit 1
}
[[ "$ENV_FILTER" != "all" && "$ENV_FILTER" != "dev" && \
   "$ENV_FILTER" != "stg" && "$ENV_FILTER" != "prd" ]] && {
  log_error "Ambiente invalido: $ENV_FILTER. Use all, dev, stg ou prd."; exit 1
}

check_dependencies() {
  log_step "Verificando dependencias..."
  local missing=0
  command -v kubectl  &>/dev/null || { log_error "kubectl nao encontrado."; missing=1; }
  command -v python3  &>/dev/null || { log_error "python3 nao encontrado."; missing=1; }
  python3 -c "import yaml" 2>/dev/null || {
    log_error "PyYAML nao instalado. Execute: pip install pyyaml"
    missing=1
  }
  if [[ "$METHOD" == "offline" || "$RESEAL" == "true" || -n "$CERT_FILE" ]]; then
    command -v kubeseal &>/dev/null || { log_error "kubeseal nao encontrado (necessario para --method offline, --reseal e --cert)."; missing=1; }
  fi
  if [[ -n "$CERT_FILE" ]]; then
    [[ -f "$CERT_FILE" ]] || { log_error "Arquivo de certificado nao encontrado: ${CERT_FILE}"; missing=1; }
    [[ "$RESEAL" == "true" ]] || { log_error "--cert requer --reseal para ter efeito."; missing=1; }
  fi
  [[ $missing -ne 0 ]] && { log_error "Instale as dependencias e tente novamente."; exit 1; }
  log_ok "Dependencias verificadas"
}

check_cluster() {
  log_step "Verificando conexao com o cluster K8s..."
  if ! kubectl cluster-info &>/dev/null; then
    log_error "Sem acesso ao cluster. Verifique KUBECONFIG ou VPN."
    exit 1
  fi
  local ctx
  ctx=$(kubectl config current-context 2>/dev/null || echo "desconhecido")
  log_ok "Cluster acessivel -- contexto: ${BOLD}${ctx}${NC}"
}

# ============================================================
# METODO OFFLINE: Exportar chave privada RSA do controller
#
# A chave e um Secret TLS em kube-system com label:
#   sealedsecrets.bitnami.com/sealed-secrets-key=active
#
# Equivalente manual:
#   kubectl get secret -n kube-system \
#     -l sealedsecrets.bitnami.com/sealed-secrets-key=active \
#     -o jsonpath='{.items[-1].data.tls\.key}' \
#     | base64 --decode > sealed-secrets-private-key.pem
# ============================================================
export_private_key() {
  log_step "Exportando chave privada RSA do Sealed Secrets controller..."

  local CONTROLLER_NS
  CONTROLLER_NS=$(kubectl get pods -A -l name=sealed-secrets-controller \
    -o jsonpath='{.items[0].metadata.namespace}' 2>/dev/null || echo "kube-system")

  log_info "Namespace do controller: ${CONTROLLER_NS}"

  local KEY_SECRETS
  KEY_SECRETS=$(kubectl get secret -n "${CONTROLLER_NS}" \
    -l sealedsecrets.bitnami.com/sealed-secrets-key \
    -o jsonpath='{.items[*].metadata.name}' 2>/dev/null || true)

  if [[ -z "$KEY_SECRETS" ]]; then
    log_error "Nenhuma chave privada encontrada em ${CONTROLLER_NS}."
    exit 1
  fi

  log_info "Chaves encontradas: ${KEY_SECRETS}"

  kubectl get secret -n "${CONTROLLER_NS}" \
    -l sealedsecrets.bitnami.com/sealed-secrets-key=active \
    -o jsonpath='{.items[-1].data.tls\.key}' \
    | base64 --decode > "${PRIVATE_KEY_FILE}"

  [[ ! -s "${PRIVATE_KEY_FILE}" ]] && {
    log_error "Falha ao exportar a chave privada."; exit 1
  }

  chmod 600 "${PRIVATE_KEY_FILE}"
  log_ok "Chave privada exportada: ${PRIVATE_KEY_FILE}"
  log_warn "ATENCAO: Este arquivo decripta TODOS os SealedSecrets do cluster!"
}

clean_secret_yaml() {
  local INPUT="$1"
  local OUTPUT="$2"
  python3 -c "
import yaml, sys
with open(sys.argv[1]) as f:
    doc = yaml.safe_load(f)
meta = doc.get('metadata', {})
for field in ['resourceVersion','uid','creationTimestamp','generation','selfLink','managedFields']:
    meta.pop(field, None)
doc.pop('status', None)
with open(sys.argv[2], 'w') as f:
    yaml.dump(doc, f, default_flow_style=False, allow_unicode=True)
" "${INPUT}" "${OUTPUT}"
}

parse_sealed_secret() {
  local SEALED_FILE="$1"
  python3 -c "
import yaml, sys
with open(sys.argv[1]) as f:
    doc = yaml.safe_load(f)
meta = doc.get('metadata', {})
ann  = meta.get('annotations', {})
name = meta.get('name', '')
ns   = meta.get('namespace', '') or 'CLUSTER_WIDE'
cw   = ann.get('sealedsecrets.bitnami.com/cluster-wide', 'false')
print(name, ns, cw)
" "${SEALED_FILE}"
}

# ============================================================
# METODO KUBECTL: buscar Secret ja decriptografado no cluster
# ============================================================
fetch_secret_kubectl() {
  local SECRET_NAME="$1"
  local NAMESPACE="$2"
  local OUTPUT_FILE="$3"

  local TEMP_FILE
  TEMP_FILE=$(mktemp /tmp/k8s-secret-XXXXXX.yaml)

  if ! kubectl get secret "${SECRET_NAME}" -n "${NAMESPACE}" \
      -o yaml > "${TEMP_FILE}" 2>/dev/null; then
    log_warn "Secret '${SECRET_NAME}' nao encontrado em '${NAMESPACE}'"
    rm -f "${TEMP_FILE}"
    return 1
  fi

  clean_secret_yaml "${TEMP_FILE}" "${OUTPUT_FILE}"
  rm -f "${TEMP_FILE}"
  chmod 600 "${OUTPUT_FILE}"
  return 0
}

# ============================================================
# METODO OFFLINE: decriptografar SealedSecret localmente
# ============================================================
fetch_secret_offline() {
  local SEALED_SECRET_FILE="$1"
  local OUTPUT_FILE="$2"

  local TEMP_FILE
  TEMP_FILE=$(mktemp /tmp/k8s-secret-XXXXXX.yaml)

  if ! kubeseal --recovery-unseal \
      --recovery-private-key "${PRIVATE_KEY_FILE}" \
      --format yaml \
      < "${SEALED_SECRET_FILE}" > "${TEMP_FILE}" 2>/dev/null; then
    log_warn "Falha ao decriptografar: ${SEALED_SECRET_FILE}"
    rm -f "${TEMP_FILE}"
    return 1
  fi

  clean_secret_yaml "${TEMP_FILE}" "${OUTPUT_FILE}"
  rm -f "${TEMP_FILE}"
  chmod 600 "${OUTPUT_FILE}"
  return 0
}

# ============================================================
# RESEAL: Buscar certificado publico do controller para selar
#
# O cert publico e usado para ENCRIPTAR (selar) novas secrets.
# Diferente da chave privada (que decripta), o cert publico e
# seguro de usar offline e pode ser distribuido livremente.
#
# Equivalente manual:
#   kubeseal --fetch-cert \
#     --controller-name=sealed-secrets-controller \
#     --controller-namespace=kube-system > pub-cert.pem
# ============================================================
fetch_public_cert() {
  log_step "Obtendo certificado publico do Sealed Secrets controller..."

  local CONTROLLER_NS
  CONTROLLER_NS=$(kubectl get pods -A -l name=sealed-secrets-controller \
    -o jsonpath='{.items[0].metadata.namespace}' 2>/dev/null || echo "kube-system")

  log_info "Namespace do controller: ${CONTROLLER_NS}"

  if ! kubeseal --fetch-cert \
      --controller-name=sealed-secrets-controller \
      --controller-namespace="${CONTROLLER_NS}" \
      > "${PUBLIC_CERT_FILE}" 2>/dev/null; then
    log_error "Falha ao obter certificado publico do controller."
    exit 1
  fi

  [[ ! -s "${PUBLIC_CERT_FILE}" ]] && {
    log_error "Certificado publico vazio. Verifique se o controller esta rodando."; exit 1
  }

  log_ok "Certificado publico obtido (${PUBLIC_CERT_FILE})"
}

# ============================================================
# RESEAL: Selar um secret-*.yaml => sealed-secret-*.yaml
# Sobrescreve o SealedSecret existente com o novo conteudo.
# ============================================================
seal_secret() {
  local SECRET_FILE="$1"
  local SEALED_OUTPUT="$2"

  local TEMP_FILE
  TEMP_FILE=$(mktemp /tmp/k8s-sealed-XXXXXX.yaml)

  if ! kubeseal --cert "${PUBLIC_CERT_FILE}" --format yaml \
      < "${SECRET_FILE}" > "${TEMP_FILE}" 2>/dev/null; then
    log_warn "Falha ao selar: ${SECRET_FILE##"${BASE_DIR}/"}"
    rm -f "${TEMP_FILE}"
    return 1
  fi

  mv "${TEMP_FILE}" "${SEALED_OUTPUT}"
  return 0
}

# ============================================================
# RESEAL: Re-selar todos os secret-*.yaml de um projeto.
# Para cada sealed-secret-*.yaml encontrado, verifica se existe
# o secret-*.yaml correspondente e re-sela sobrescrevendo.
# ============================================================
process_project_reseal() {
  local PROJECT="$1"
  local KUSTOMIZE_DIR="${PROJECT_KUSTOMIZE_DIR[$PROJECT]}"

  echo -e "\n${CYAN}${BOLD}--- Projeto: ${PROJECT} ---${NC}"

  if [[ ! -d "$KUSTOMIZE_DIR" ]]; then
    log_warn "Diretorio nao encontrado: ${KUSTOMIZE_DIR}"
    return
  fi

  mapfile -t SEALED_FILES < <(find "${KUSTOMIZE_DIR}" -name "sealed-secret-*.yaml" | sort)

  if [[ ${#SEALED_FILES[@]} -eq 0 ]]; then
    log_warn "Nenhum SealedSecret encontrado em ${KUSTOMIZE_DIR}"
    return
  fi

  local COUNT_OK=0
  local COUNT_SKIP=0
  local COUNT_ERR=0

  for SEALED_FILE in "${SEALED_FILES[@]}"; do

    if ! env_matches_filter "${SEALED_FILE}"; then
      log_skip "${SEALED_FILE##"${BASE_DIR}/"}"
      (( COUNT_SKIP++ )) || true
      continue
    fi

    read -r SECRET_NAME _NS _CW < <(parse_sealed_secret "${SEALED_FILE}")

    local SEALED_DIR
    SEALED_DIR="$(dirname "${SEALED_FILE}")"
    local SECRET_FILE="${SEALED_DIR}/secret-${SECRET_NAME}.yaml"

    if [[ ! -f "$SECRET_FILE" ]]; then
      log_warn "secret-${SECRET_NAME}.yaml ausente -- execute primeiro sem --reseal para obter do cluster"
      (( COUNT_ERR++ )) || true
      continue
    fi

    local SEALED_REL="${SEALED_FILE##"${BASE_DIR}/"}"
    local SECRET_REL="${SECRET_FILE##"${BASE_DIR}/"}"

    if [[ "$DRY_RUN" == "true" ]]; then
      printf "  ${GREEN}%-60s${NC} => ${CYAN}%s${NC}\n" "${SECRET_REL}" "$(basename "${SEALED_FILE}")"
      log_info "    sobrescreve: ${SEALED_REL}"
      (( COUNT_OK++ )) || true
      continue
    fi

    log_info "Selando: ${SECRET_REL} => ${SEALED_REL}"

    if seal_secret "${SECRET_FILE}" "${SEALED_FILE}"; then
      log_ok "Selado: ${SEALED_REL}"
      (( COUNT_OK++ )) || true
    else
      (( COUNT_ERR++ )) || true
    fi

  done

  echo -e "  ${GREEN}OK: ${COUNT_OK}${NC}  |  ${CYAN}Ignorados: ${COUNT_SKIP}${NC}  |  ${RED}Erros: ${COUNT_ERR}${NC}"
}

env_matches_filter() {
  local PATH_STR="$1"

  # base/ vale para qualquer ambiente
  [[ "$PATH_STR" == *"/base/"* ]] && return 0

  # sem filtro: tudo passa
  [[ "$ENV_FILTER" == "all" ]] && return 0

  case "$ENV_FILTER" in
    dev) [[ "$PATH_STR" == *"/development/"* || "$PATH_STR" == *"/dev/"* ]] && return 0 ;;
    stg) [[ "$PATH_STR" == *"/staging/"*     || "$PATH_STR" == *"/stg/"* ]] && return 0 ;;
    prd) [[ "$PATH_STR" == *"/production/"*  || "$PATH_STR" == *"/prd/"* ]] && return 0 ;;
  esac

  return 1
}

process_project() {
  local PROJECT="$1"
  local KUSTOMIZE_DIR="${PROJECT_KUSTOMIZE_DIR[$PROJECT]}"

  echo -e "\n${CYAN}${BOLD}--- Projeto: ${PROJECT} ---${NC}"

  if [[ ! -d "$KUSTOMIZE_DIR" ]]; then
    log_warn "Diretorio nao encontrado (projeto nao clonado?): ${KUSTOMIZE_DIR}"
    return
  fi

  mapfile -t SEALED_FILES < <(find "${KUSTOMIZE_DIR}" -name "sealed-secret-*.yaml" | sort)

  if [[ ${#SEALED_FILES[@]} -eq 0 ]]; then
    log_warn "Nenhum SealedSecret encontrado em ${KUSTOMIZE_DIR}"
    return
  fi

  local COUNT_OK=0
  local COUNT_SKIP=0
  local COUNT_ERR=0

  for SEALED_FILE in "${SEALED_FILES[@]}"; do

    if ! env_matches_filter "${SEALED_FILE}"; then
      log_skip "${SEALED_FILE##"${BASE_DIR}/"}"
      (( COUNT_SKIP++ )) || true
      continue
    fi

    # Extrair name/namespace
    read -r SECRET_NAME NAMESPACE IS_CLUSTER_WIDE < <(
      parse_sealed_secret "${SEALED_FILE}"
    )

    if [[ "$NAMESPACE" == "CLUSTER_WIDE" || "$IS_CLUSTER_WIDE" == "true" ]]; then
      case "$ENV_FILTER" in
        stg) NAMESPACE="${PROJECT_NS_STG[$PROJECT]}" ;;
        dev) NAMESPACE="${PROJECT_NS_DEV[$PROJECT]}" ;;
        *)   NAMESPACE="${PROJECT_NS_PRD[$PROJECT]}" ;;  # prd e all -> producao
      esac
    fi

    local SEALED_DIR
    SEALED_DIR="$(dirname "${SEALED_FILE}")"
    local OUTPUT_FILE="${SEALED_DIR}/secret-${SECRET_NAME}.yaml"

    # Dry-run: apenas exibe o mapeamento
    if [[ "$DRY_RUN" == "true" ]]; then
      local SEALED_REL="${SEALED_FILE##"${BASE_DIR}/"}"
      local OUTPUT_BASE
      OUTPUT_BASE="$(basename "${OUTPUT_FILE}")"
      local DIR_REL="${SEALED_DIR##"${BASE_DIR}/"}"
      printf "  ${CYAN}%-68s${NC} => ${GREEN}%s${NC}\n" "${SEALED_REL}" "${OUTPUT_BASE}"
      log_info "    namespace: ${NAMESPACE} | dir: ${DIR_REL}"
      (( COUNT_OK++ )) || true
      continue
    fi

    log_info "Processando: ${SEALED_FILE##"${BASE_DIR}/"}"
    log_info "  Secret: ${SECRET_NAME} | Namespace: ${NAMESPACE}"

    local OK=0
    if [[ "$METHOD" == "kubectl" ]]; then
      fetch_secret_kubectl "${SECRET_NAME}" "${NAMESPACE}" "${OUTPUT_FILE}" && OK=1
    else
      fetch_secret_offline "${SEALED_FILE}" "${OUTPUT_FILE}" && OK=1
    fi

    if [[ $OK -eq 1 ]]; then
      log_ok "Gravado: ${OUTPUT_FILE##"${BASE_DIR}/"}"
      (( COUNT_OK++ )) || true
    else
      (( COUNT_ERR++ )) || true
    fi

  done

  echo -e "  ${GREEN}OK: ${COUNT_OK}${NC}  |  ${CYAN}Ignorados: ${COUNT_SKIP}${NC}  |  ${RED}Erros: ${COUNT_ERR}${NC}"
}

show_report() {
  log_step "Relatorio -- pares sealed-secret <-> secret gerado:"
  echo ""

  local FOUND=0

  while IFS= read -r SEALED; do
    local SEALED_REL="${SEALED##"${BASE_DIR}/"}"
    local SEALED_DIR
    SEALED_DIR="$(dirname "${SEALED}")"
    local NAME
    NAME=$(python3 -c "
import yaml, sys
with open(sys.argv[1]) as f:
    d = yaml.safe_load(f)
print(d['metadata']['name'])
" "${SEALED}" 2>/dev/null || echo "unknown")

    local SECRET_FILE="${SEALED_DIR}/secret-${NAME}.yaml"

    if [[ -f "$SECRET_FILE" ]]; then
      local SECRET_REL="${SECRET_FILE##"${BASE_DIR}/"}"
      local SIZE
      SIZE=$(wc -c < "$SECRET_FILE" 2>/dev/null || echo "?")
      echo -e "  ${CYAN}sealed:${NC} ${SEALED_REL}"
      echo -e "  ${GREEN}secret:${NC} ${SECRET_REL}  ${CYAN}(${SIZE} bytes)${NC}"
      echo ""
      (( FOUND++ )) || true
    fi
  done < <(find "${BASE_DIR}" -name "sealed-secret-*.yaml" | sort)

  if [[ $FOUND -eq 0 ]]; then
    log_warn "Nenhum arquivo secret-*.yaml encontrado."
    return
  fi

  log_warn "Estes arquivos contem dados sensiveis em texto claro!"
  log_warn "Protegidos pelo .gitignore. NUNCA faca commit deles."
}

banner() {
  echo ""
  echo -e "${BOLD}${BLUE}=====================================================${NC}"
  echo -e "${BOLD}${BLUE}  Extract K8s Secrets -- UNISELEC                   ${NC}"
  echo -e "${BOLD}${BLUE}=====================================================${NC}"
  echo -e "  Metodo  : ${BOLD}${METHOD}${NC}"
  echo -e "  Ambiente: ${BOLD}${ENV_FILTER}${NC}"
  echo -e "  Re-seal : ${BOLD}${RESEAL}${NC}"
  echo -e "  Cert ext: ${BOLD}${CERT_FILE:-"(cluster -- fetch automatico)"}${NC}"
  echo -e "  Dry-run : ${BOLD}${DRY_RUN}${NC}"
  echo -e "  Base    : ${BASE_DIR}"
}

main() {
  banner
  check_dependencies

  if [[ "$RESEAL" == "true" && "$DRY_RUN" == "true" ]]; then
    log_step "Mapeamento secret => sealed-secret (dry-run -- nada sera sobrescrito)"
    echo ""
    for PROJECT in uniselec-admin uniselec-api uniselec-selecoes; do
      process_project_reseal "${PROJECT}"
    done
    echo ""
    log_warn "Dry-run concluido. Nenhum SealedSecret foi alterado."
    echo ""
    exit 0
  fi

  if [[ "$DRY_RUN" == "true" ]]; then
    log_step "Mapeamento sealed-secret => secret (dry-run -- nada sera gravado)"
    echo ""
    for PROJECT in uniselec-admin uniselec-api uniselec-selecoes; do
      process_project "${PROJECT}"
    done
    echo ""
    log_warn "Dry-run concluido. Nenhum arquivo foi criado."
    echo ""
    exit 0
  fi

  check_cluster

  if [[ "$RESEAL" == "true" ]]; then
    if [[ -n "$CERT_FILE" ]]; then
      log_step "Usando certificado publico externo para re-selamento (migracao de cluster)..."
      cp "${CERT_FILE}" "${PUBLIC_CERT_FILE}"
      chmod 600 "${PUBLIC_CERT_FILE}"
      log_ok "Certificado carregado: ${CERT_FILE}"
    else
      fetch_public_cert
    fi
    log_step "Re-selando Secrets dos projetos UNISELEC..."
    for PROJECT in uniselec-admin uniselec-api uniselec-selecoes; do
      process_project_reseal "${PROJECT}"
    done
    echo ""
    log_ok "Re-selamento concluido. Faca commit dos sealed-secret-*.yaml atualizados."
    echo ""
    exit 0
  fi

  if [[ "$METHOD" == "offline" ]]; then
    export_private_key
  fi

  log_step "Extraindo Secrets dos projetos UNISELEC..."

  for PROJECT in uniselec-admin uniselec-api uniselec-selecoes; do
    process_project "${PROJECT}"
  done

  show_report

  # temporarios removidos
  echo ""
  echo -e "${GREEN}${BOLD}Concluido.${NC}"
  echo ""
}

main
