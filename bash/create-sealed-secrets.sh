#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "Criando SealedSecrets (regcred + mariadb)..."
echo ""

# Validar kubeseal
if ! command -v kubeseal &> /dev/null; then
    echo "kubeseal não encontrado"
    echo "   Instale: https://github.com/bitnami-labs/sealed-secrets#kubeseal"
    exit 1
fi

# Validar conexão com cluster
if ! kubectl cluster-info &> /dev/null; then
    echo "Não conectado ao cluster Kubernetes"
    echo "   Execute: kubectl config use-context SEU_CONTEXTO"
    exit 1
fi

# Validar sealed-secrets-controller
if ! kubectl get deployment sealed-secrets-controller -n kube-system &> /dev/null 2>&1; then
    echo "Sealed Secrets controller não encontrado no cluster"
    echo ""
    echo "Verifique se você está no cluster correto:"
    echo "   kubectl config current-context"
    echo ""
    echo "Liste os contextos disponíveis:"
    echo "   kubectl config get-contexts"
    echo ""
    echo "Troque para o cluster correto:"
    echo "   kubectl config use-context NOME_DO_CONTEXTO"
    echo ""
    exit 1
fi

echo "Conectado ao cluster: $(kubectl config current-context)"
echo "Sealed Secrets controller encontrado!"
echo ""

# =============================================================================
# 1. GERAR SEALED SECRET DO REGCRED
# =============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[1/2] Gerando SealedSecret para regcred..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

REGCRED_SECRET="${PROJECT_ROOT}/kustomize/base/secret-regcred.yaml"
REGCRED_SEALED="${PROJECT_ROOT}/kustomize/base/sealed-secret-regcred.yaml"

if [ ! -f "$REGCRED_SECRET" ]; then
    echo "Arquivo não encontrado: $REGCRED_SECRET"
    echo "Pulando geração do regcred..."
    echo ""
else
    echo "Criptografando regcred..."

    kubeseal -f "$REGCRED_SECRET" \
             -w "$REGCRED_SEALED" \
             --scope cluster-wide

    if [ $? -eq 0 ]; then
        echo "SealedSecret criado: kustomize/base/sealed-secret-regcred.yaml"

        # Validar
        if kubeseal --validate < "$REGCRED_SEALED" &> /dev/null; then
            echo "Validação OK: sealed-secret-regcred.yaml"
        else
            echo "Aviso: Validação falhou (pode ser normal se não tiver CRD instalado)"
        fi
    else
        echo "Erro ao criar SealedSecret do regcred"
        exit 1
    fi
    echo ""
fi

# =============================================================================
# 2. GERAR SEALED SECRET DO MARIADB
# =============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "[2/2] Gerando SealedSecret para MariaDB..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "Digite as senhas para o MariaDB:"
echo "(As senhas não serão exibidas)"
echo ""

read -sp "Senha ROOT do MariaDB: " MYSQL_ROOT_PASSWORD
echo
read -sp "Senha do usuário 'uniselec': " MYSQL_PASSWORD
echo
read -sp "Senha SST (State Snapshot Transfer): " SST_PASSWORD
echo
read -sp "Senha de replicação: " MARIADB_REPLICATION_PASSWORD
echo

# Validar senhas não vazias
if [ -z "$MYSQL_ROOT_PASSWORD" ] || [ -z "$MYSQL_PASSWORD" ] || \
   [ -z "$SST_PASSWORD" ] || [ -z "$MARIADB_REPLICATION_PASSWORD" ]; then
    echo ""
    echo "Erro: Todas as senhas são obrigatórias"
    exit 1
fi

echo ""
echo "Gerando Secret temporário do MariaDB..."

# Criar Secret temporário
kubectl create secret generic mariadb-secret-env \
  --from-literal=MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}" \
  --from-literal=MYSQL_PASSWORD="${MYSQL_PASSWORD}" \
  --from-literal=SST_USER="sst_user" \
  --from-literal=SST_PASSWORD="${SST_PASSWORD}" \
  --from-literal=MARIADB_REPLICATION_PASSWORD="${MARIADB_REPLICATION_PASSWORD}" \
  --dry-run=client -o yaml > /tmp/mariadb-secret.yaml

echo "Criptografando MariaDB secret..."

MARIADB_SEALED="${PROJECT_ROOT}/kustomize/base/mariadb/sealed-secret-mariadb.yaml"

# Gerar SealedSecret do MariaDB
kubeseal -f /tmp/mariadb-secret.yaml \
         -w "$MARIADB_SEALED" \
         --scope cluster-wide

if [ $? -eq 0 ]; then
    echo "SealedSecret criado: kustomize/base/mariadb/sealed-secret-mariadb.yaml"

    # Validar
    if kubeseal --validate < "$MARIADB_SEALED" &> /dev/null; then
        echo "Validação OK: sealed-secret-mariadb.yaml"
    else
        echo "Aviso: Validação falhou (pode ser normal se não tiver CRD instalado)"
    fi
else
    echo "Erro ao criar SealedSecret do MariaDB"
    rm -f /tmp/mariadb-secret.yaml
    exit 1
fi

# Limpar arquivo temporário
rm -f /tmp/mariadb-secret.yaml
echo "Arquivo temporário removido"

# =============================================================================
# RESUMO FINAL
# =============================================================================
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "CONCLUÍDO!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Arquivos gerados:"
if [ -f "$REGCRED_SEALED" ]; then
    echo "kustomize/base/sealed-secret-regcred.yaml"
fi
echo "kustomize/base/mariadb/sealed-secret-mariadb.yaml"
echo ""
echo "Os arquivos foram criados mas NÃO foram aplicados no cluster."
echo ""
echo "Próximos passos:"
echo ""
echo "  1. Verificar os arquivos gerados:"
echo "     ls -lh kustomize/base/sealed-secret-*.yaml"
echo "     ls -lh kustomize/base/mariadb/sealed-secret-*.yaml"
echo ""
echo "  2. Validar com Kustomize:"
echo "     kubectl kustomize overlays/staging/ | grep -A5 'kind: SealedSecret'"
echo ""
echo "  3. Commitar no Git:"
echo "     git add kustomize/base/sealed-secret-regcred.yaml"
echo "     git add kustomize/base/mariadb/sealed-secret-mariadb.yaml"
echo "     git commit -m 'chore: atualiza sealed secrets (regcred + mariadb)'"
echo "     git push"
echo ""
echo "  4. Aplicar via Kustomize (quando quiser):"
echo "     kubectl apply -k overlays/staging/"
echo ""