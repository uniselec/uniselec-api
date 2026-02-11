#!/bin/bash
# ============================================================
# Script para diagnóstico ponta-a-ponta do ambiente UNISELEC
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-11-20
# Versão: 1.0.0
# ============================================================

# troubleshooting-uniselec.sh
# Automação completa de diagnóstico ponta-a-ponta (HAProxy -> Ingress -> API -> MariaDB Galera)
# Suporta 3 ambientes: dev, stg, prd

# ✔ HAProxy externo
# ✔ Ingress Controller
# ✔ Ingress Hostname (DNS + curl timing)
# ✔ Pods, Services, Endpoints, Events
# ✔ HPA (se existir)
# ✔ Logs
# ✔ Testes SQL internos e externos
# ✔ Testes com pod mysql temporário
# ✔ wsrep cluster status (Galera)
# ✔ Teste clusterIP e LoadBalancer
# ✔ curl dentro da API /health
# ✔ dig e nslookup
# ✔ top + metrics

set -euo pipefail
IFS=$'\n\t'

ENV="${1:-dev}"   # dev | stg | prd
HAPROXY_HOST="${HAPROXY_HOST:-10.130.0.60}"
SSH_PORT="${SSH_PORT:-37389}"
SSH_USER="${SSH_USER:-ansible}"
KUBECTL_CONTEXT="${KUBECTL_CONTEXT:-}"

declare -A NS_MAP=( ["dev"]="uniselec-api-dev" ["stg"]="uniselec-api-stg" ["prd"]="uniselec-api-prd" )
declare -A LB_MAP=( ["dev"]="10.130.0.159" ["stg"]="10.130.0.158" ["prd"]="10.130.0.157" )
declare -A INGRESS_HOST=( ["dev"]="uniselec-api-development.unilab.edu.br" ["stg"]="uniselec-api-stg.unilab.edu.br" ["prd"]="uniselec-api.unilab.edu.br" )

NAMESPACE="${NS_MAP[$ENV]}"
LB_IP="${LB_MAP[$ENV]}"
ING_HOST="${INGRESS_HOST[$ENV]}"

MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-Password123}"

if [ -z "$MYSQL_ROOT_PASSWORD" ]; then
  if command -v kubectl >/dev/null 2>&1 ; then
    if [ -n "$KUBECTL_CONTEXT" ]; then KUBECTL_CTX_OPT="--context=$KUBECTL_CONTEXT"; else KUBECTL_CTX_OPT=""; fi
    set +e
    secret_pw=$(kubectl $KUBECTL_CTX_OPT get secret mariadb-secret-env -n "$NAMESPACE" -o jsonpath='{.data.MYSQL_ROOT_PASSWORD}' 2>/dev/null || true)
    set -e
    if [ -n "$secret_pw" ]; then MYSQL_ROOT_PASSWORD="$(echo "$secret_pw" | base64 -d)"; fi
  fi
fi

MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-ChangeMe}"

echolog() { printf "\n===== %s =====\n" "$1"; }

ssh_exec() {
  local cmd="$1"
  ssh -o BatchMode=yes -o ConnectTimeout=8 -p "$SSH_PORT" "$SSH_USER@$HAPROXY_HOST" "$cmd"
}

kubectl_cmd() {
  if [ -n "$KUBECTL_CONTEXT" ]; then
    kubectl --context="$KUBECTL_CONTEXT" "$@"
  else
    kubectl "$@"
  fi
}

################################################################################################
# FASE 1 — TESTES EXTERNOS (Cliente → HAProxy → Ingress)
################################################################################################
echolog "FASE 1: Testes externos (cliente -> HAProxy -> Ingress)"

echolog "1.1 Ping e traceroute para HAProxy $HAPROXY_HOST"
ping -c 4 "$HAPROXY_HOST" || true
command -v traceroute >/dev/null 2>&1 && traceroute -n "$ING_HOST" || true

echolog "1.2 curl timing para https://$ING_HOST"
curl -s -o /dev/null -w "DNS:%{time_namelookup}s TCP:%{time_connect}s SSL:%{time_appconnect}s TTFB:%{time_starttransfer}s TOTAL:%{time_total}s\n" "https://$ING_HOST" || true

################################################################################################
# FASE 2 — HAProxy Externo
################################################################################################
echolog "FASE 2: HAProxy externo"

echolog "2.1 Processos HAProxy"
ssh_exec "ps aux | grep -Ei 'haproxy|ingress' | grep -v grep" || true

echolog "2.2 Conexões ESTABLISHED"
ssh_exec "netstat -tpn 2>/dev/null | grep ESTABLISHED | wc -l" || true

echolog "2.3 Monitor 10s conexões ESTABLISHED"
ssh_exec 'for i in {1..10}; do netstat -tpn 2>/dev/null | grep ESTABLISHED | wc -l; sleep 1; done'

echolog "2.4 Config haproxy ingress"
ssh_exec "sudo ls -la /tmp/haproxy-ingress/etc 2>/dev/null || true"

echolog "2.5 Exibir haproxy.cfg"
ssh_exec "sudo head -n 80 /tmp/haproxy-ingress/etc/haproxy.cfg 2>/dev/null || sudo head -n 80 /etc/haproxy/haproxy.cfg 2>/dev/null"

echolog "2.6 Logs HAProxy (journalctl)"
ssh_exec "sudo journalctl -u haproxy -n 80 2>/dev/null || true"

echolog "2.7 Logs reais do HAProxy Ingress Controller no Kubernetes"
if kubectl_cmd get ns ingress-nginx >/dev/null 2>&1; then
  kubectl_cmd -n ingress-nginx logs deploy/haproxy-ingress-controller --all-containers=true
else
  echo "Namespace ingress-nginx não existe, pulando logs do ingress controller"
fi

echolog "2.8 Teste nc -> Ingress LoadBalancer (DNS -> IP)"
K8S_INGRESS_IP="$(dig +short "$ING_HOST" | head -n1 || true)"
if [ -n "$K8S_INGRESS_IP" ]; then
  ssh_exec "nc -zv -w3 $K8S_INGRESS_IP 80 || nc -zv -w3 $K8S_INGRESS_IP 443" || true
fi

################################################################################################
# FASE 3 — Kubernetes Interno
################################################################################################
echolog "FASE 3: Kubernetes | Namespace $NAMESPACE"

echolog "3.1 Pods, services, endpoints, ingress"
kubectl_cmd get pods -n "$NAMESPACE" -o wide || true
kubectl_cmd get svc -n "$NAMESPACE" -o wide || true
kubectl_cmd get endpoints -n "$NAMESPACE" || true
kubectl_cmd get ingress -n "$NAMESPACE" -o wide || true

echolog "3.2 HPA"
kubectl_cmd get hpa -n "$NAMESPACE" || true

echolog "3.3 Últimos eventos"
kubectl_cmd get events -n "$NAMESPACE" --sort-by=.lastTimestamp | tail -n 30 || true

echolog "3.4 top pods (metrics)"
kubectl_cmd top pods -n "$NAMESPACE" --containers || true

################################################################################################
# FASE 4 — MariaDB Galera
################################################################################################
echolog "FASE 4: MariaDB Galera"
echo "MYSQL_ROOT_PASSWORD está setado (oculto)"

set +e
mapfile -t pods < <(kubectl_cmd get pods -n "$NAMESPACE" -l app=mariadb -o name)
set -e

# Usar MariaDB 10.6.24 LTS como cliente compatível com o servidor
MYSQL_IMAGE="mariadb:10.11.15-jammy"

echolog "4.1 wsrep status"
for p in "${pods[@]}"; do
  echo "===> $p"
  TMP_CLIENT="tmp-client-$(date +%s%N)"
  kubectl_cmd run -n "$NAMESPACE" "$TMP_CLIENT" --rm -i --restart=Never \
    --image="$MYSQL_IMAGE" \
    -- bash -c "mysql --connect-timeout=5 -h mariadb-app.$NAMESPACE.svc.cluster.local -uroot -p'${MYSQL_ROOT_PASSWORD}' \
      -e 'SHOW STATUS LIKE \"wsrep_cluster_size\"; SHOW STATUS LIKE \"wsrep_local_state_comment\";'" || true
done

echolog "4.2 Teste de replicação (tabela check_galera)"
if [ ${#pods[@]} -ge 1 ]; then
  TMP_CLIENT="tmp-client-$(date +%s%N)"
  kubectl_cmd run -n "$NAMESPACE" "$TMP_CLIENT" --rm -i --restart=Never \
    --image="$MYSQL_IMAGE" \
    -- bash -c "mysql --connect-timeout=5 -h mariadb-app.$NAMESPACE.svc.cluster.local -uroot -p'${MYSQL_ROOT_PASSWORD}' \
      -e 'CREATE DATABASE IF NOT EXISTS check_galera; \
          CREATE TABLE IF NOT EXISTS check_galera.t (id INT PRIMARY KEY); \
          INSERT IGNORE INTO check_galera.t VALUES (1); \
          SELECT * FROM check_galera.t;'" || true
fi

echolog "4.3 Latência SELECT 1"
if [ ${#pods[@]} -ge 2 ]; then
  TMP_CLIENT="tmp-client-$(date +%s%N)"
  DST="mariadb-app.$NAMESPACE.svc.cluster.local"
  kubectl_cmd run -n "$NAMESPACE" "$TMP_CLIENT" --rm -i --restart=Never \
    --image="$MYSQL_IMAGE" \
    -- bash -c "time mysql --connect-timeout=5 -h $DST -uroot -p'${MYSQL_ROOT_PASSWORD}' -e 'SELECT 1'" || true
fi

echolog "4.4 Teste via Service clusterIP"
TMP_CLIENT="tmp-client-$(date +%s%N)"
kubectl_cmd run -n "$NAMESPACE" "$TMP_CLIENT" --rm -i --restart=Never \
  --image="$MYSQL_IMAGE" \
  -- bash -c "mysql --connect-timeout=5 -h mariadb-app.$NAMESPACE.svc.cluster.local -uroot -p'${MYSQL_ROOT_PASSWORD}' -e 'SELECT NOW()'" || true

echolog "4.5 Testes via LoadBalancer IP $LB_IP"
nc -zv -w3 "$LB_IP" 3306 || true
TMP_CLIENT="tmp-client-$(date +%s%N)"
kubectl_cmd run -n "$NAMESPACE" "$TMP_CLIENT" --rm -i --restart=Never \
  --image="$MYSQL_IMAGE" \
  -- bash -c "mysql --connect-timeout=5 -h $LB_IP -uroot -p'${MYSQL_ROOT_PASSWORD}' -e 'SELECT 1'" || true

echolog "4.6 DNS checks"
dig +short "$ING_HOST" || true

echolog "4.7 Services e Endpoints"
kubectl_cmd get svc -n "$NAMESPACE" | grep mariadb || true
kubectl_cmd get endpoints -n "$NAMESPACE" mariadb-app -o yaml || true

################################################################################################
# FASE 5 — Ingress e API
################################################################################################
echolog "FASE 5: Ingress e API"

echolog "5.1 Ingress YAML"
kubectl_cmd get ingress -n "$NAMESPACE" -o yaml || true

echolog "5.2 Descrição do ingress"
kubectl_cmd describe ingress -n "$NAMESPACE" || true

echolog "5.3 Teste da API dentro do pod (/up)"
API_POD=$(kubectl_cmd get pods -n "$NAMESPACE" -l app=uniselec-api -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || true)
if [ -n "$API_POD" ]; then
  kubectl_cmd exec -n "$NAMESPACE" "$API_POD" -- \
    bash -c "curl -s -o /dev/null -w '%{http_code} %{time_total}s\n' http://localhost/up" || true
else
  echo "Nenhum pod da API encontrado."
fi

echolog "5.4 Teste externo via ingress (curl)"
curl -I "https://$ING_HOST" || true

################################################################################################
# FIM
################################################################################################
echolog "DIAGNÓSTICO COMPLETO FINALIZADO PARA O AMBIENTE: $ENV  (namespace=$NAMESPACE)"