#!/bin/bash

# ./undeploy-argocd.sh uniselec-api-dev-as

set -euo pipefail

if [ $# -ne 1 ]; then
  echo "Uso: $0 <nome-applicationset>"
  exit 1
fi

APPSET_NAME="$1"
NAMESPACE="argocd"
APP_NAME="${APPSET_NAME%-as}"  # suposição de nome da App

echo "Desfazendo deploy para ApplicationSet: $APPSET_NAME"
echo "Namespace ArgoCD: $NAMESPACE"
echo "Application associada estimada: $APP_NAME"

# Função para checar existência
resource_exists() {
  kubectl get "$1" "$2" -n "$3" &> /dev/null
}

# 1. Limpar generadores do ApplicationSet se existir
if resource_exists applicationset "$APPSET_NAME" "$NAMESPACE"; then
  echo "Limpando generadores do ApplicationSet $APPSET_NAME"
  kubectl -n $NAMESPACE patch applicationset $APPSET_NAME --type='merge' -p '{"spec":{"generators":[{"list":{"elements":[]}}]}}'
else
  echo "ApplicationSet $APPSET_NAME não encontrado, pulando patch"
fi

# 2. Remover finalizadores da aplicação se existir
if resource_exists application "$APP_NAME" "$NAMESPACE"; then
  echo "Removendo finalizadores da aplicação $APP_NAME..."
  kubectl patch application $APP_NAME -n $NAMESPACE --type=json -p='[{"op": "remove", "path": "/metadata/finalizers"}]' || echo "Falha ao remover finalizadores (possível que não existam)"
else
  echo "Aplicação $APP_NAME não encontrada, pulando remoção de finalizadores"
fi

# 3. Deletar a aplicação se existir
if resource_exists application "$APP_NAME" "$NAMESPACE"; then
  echo "Deletando aplicação $APP_NAME..."
  kubectl delete application $APP_NAME -n $NAMESPACE || echo "Falha ao deletar aplicação (já removida)"
else
  echo "Aplicação $APP_NAME não encontrada, pulando exclusão"
fi

# 4. Deletar ApplicationSet se existir
if resource_exists applicationset "$APPSET_NAME" "$NAMESPACE"; then
  echo "Deletando ApplicationSet $APPSET_NAME..."
  kubectl delete applicationset $APPSET_NAME -n $NAMESPACE || echo "Falha ao deletar ApplicationSet (já removido)"
else
  echo "ApplicationSet $APPSET_NAME não encontrado, pulando exclusão"
fi

# 5. Deletar namespace aproximado da aplicação (ajustar conforme sua estrutura)
APP_NAMESPACE="${APP_NAME%-as}"
echo "Tentando deletar namespace $APP_NAMESPACE"
if resource_exists namespace "$APP_NAMESPACE" ""; then
  kubectl delete ns $APP_NAMESPACE || echo "Falha ao deletar namespace $APP_NAMESPACE"
else
  echo "Namespace $APP_NAMESPACE não encontrado, pulando exclusão"
fi

echo "Undeploy do ApplicationSet e Application '$APPSET_NAME' concluído."