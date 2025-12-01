#!/bin/bash
# ============================================================
# Script de diagnóstico de latência de conexão app <-> MariaDB
# Foco: DNS, conexão TCP/MySQL e comparação service x IP direto
# ============================================================
# Autor: Jeff / erivandosena@gmail.com
# Data: 2025-11-27
# Versão: 1.0.0
# ============================================================
# Uso:
#   ./diagnose-db-network-latency.sh [dev|stg|prd]
#
# Padrões:
#   dev -> namespace uniselec-api-dev
#   stg -> namespace uniselec-api-stg
#   prd -> namespace uniselec-api-prd
# ============================================================

set -euo pipefail
IFS=$'\n\t'

ENV="${1:-stg}"  # dev | stg | prd

declare -A NS_MAP=( \
  ["dev"]="uniselec-api-dev" \
  ["stg"]="uniselec-api-stg" \
  ["prd"]="uniselec-api-prd" \
)

if [[ -z "${NS_MAP[$ENV]+x}" ]]; then
  echo "Uso: $0 [dev|stg|prd]"
  exit 1
fi

NAMESPACE="${NS_MAP[$ENV]}"

# Mesmo padrão do troubleshooting-uniselec.sh
kubectl_cmd() {
  if [ -n "${KUBECTL_CONTEXT:-}" ]; then
    kubectl --context="$KUBECTL_CONTEXT" "$@"
  else
    kubectl "$@"
  fi
}

# -----------------------------
# CONFIG
# -----------------------------
API_LABEL="${API_LABEL:-app=uniselec-api}"
DB_LABEL="${DB_LABEL:-app=mariadb}"

DB_SERVICE_APP="${DB_SERVICE_APP:-mariadb-app}"
DB_SERVICE_PRIMARY="${DB_SERVICE_PRIMARY:-mariadb-primary}"
DB_SERVICE_REPLICAS="${DB_SERVICE_REPLICAS:-mariadb-replicas}"
DB_SERVICE_HEADLESS="${DB_SERVICE_HEADLESS:-mariadb}"

MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASS="${MYSQL_PASS:-Password123}"
MYSQL_DB="${MYSQL_DB:-uniselec_prod}"

DNS_ITERATIONS="${DNS_ITERATIONS:-30}"
PHP_ITERATIONS="${PHP_ITERATIONS:-50}"
MYSQL_ITERATIONS="${MYSQL_ITERATIONS:-10}"

MYSQL_IMAGE="${MYSQL_IMAGE:-mariadb:10.11.15-jammy}"

echolog() { printf "\n===== %s =====\n" "$1"; }

get_api_pod() {
  kubectl_cmd get pods -n "$NAMESPACE" -l "$API_LABEL" -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || true
}

get_db_pods() {
  kubectl_cmd get pods -n "$NAMESPACE" -l "$DB_LABEL" -o jsonpath='{range .items[*]}{.metadata.name}{"\n"}{end}' 2>/dev/null || true
}

measure_dns_from_api() {
  local api_pod="$1"
  local host="$2"
  local label="$3"

  echolog "DNS dentro da API: $label ($host)"

  kubectl_cmd exec -n "$NAMESPACE" "$api_pod" -- bash -c "
    total=0
    success=0
    for i in \$(seq 1 $DNS_ITERATIONS); do
      s=\$(date +%s%3N)
      getent hosts '$host' >/dev/null 2>&1
      status=\$?
      e=\$(date +%s%3N)
      if [ \$status -eq 0 ]; then
        elapsed=\$((e - s))
        echo \"  DNS lookup \$i: \${elapsed} ms\"
        total=\$((total + elapsed))
        success=\$((success + 1))
      else
        echo \"  DNS lookup \$i: ERRO\"
      fi
      sleep 0.1
    done
    if [ \$success -gt 0 ]; then
      avg=\$((total / success))
      echo \">>> MÉDIA DNS $label: \${avg} ms (sucessos: \$success/$DNS_ITERATIONS)\"
    else
      echo \">>> NÃO FOI POSSÍVEL RESOLVER DNS para $host\"
    fi
  "
}

measure_mysql_rtt() {
  local host="$1"
  local label="$2"

  echolog "MySQL RTT via host: $label ($host)"

  local total_ms=0
  local success=0

  for i in $(seq 1 "$MYSQL_ITERATIONS"); do
    local TMP_CLIENT="mysql-nettest-$RANDOM"
    local start_time=$(date +%s%3N)

    # Executa o teste e captura apenas stdout limpo
    local result=$(kubectl_cmd run "$TMP_CLIENT" \
      -n "$NAMESPACE" --rm -i --restart=Never \
      --image="$MYSQL_IMAGE" -- \
      bash -c "mariadb -h '$host' -u'$MYSQL_USER' -p'$MYSQL_PASS' -e 'SELECT 1;' >/dev/null 2>&1 && echo 'OK' || echo 'ERROR'" 2>&1 | grep -E '^(OK|ERROR)' || echo "ERROR")

    local end_time=$(date +%s%3N)
    local elapsed=$((end_time - start_time))

    if [[ "$result" == "OK" && "$elapsed" -gt 0 ]]; then
      echo "  Tentativa $i: ${elapsed} ms"
      total_ms=$((total_ms + elapsed))
      success=$((success + 1))
    else
      echo "  Tentativa $i: ERRO na conexão"
    fi

    sleep 0.2
  done

  if [ "$success" -gt 0 ]; then
    local avg=$((total_ms / success))
    echo ">>> MÉDIA RTT MySQL ($label): ${avg} ms (sucessos: ${success}/${MYSQL_ITERATIONS})"
  else
    echo ">>> NÃO FOI POSSÍVEL CONECTAR EM $label ($host)"
  fi
}

# -----------------------------
# 0) CONTEXTO
# -----------------------------
echolog "Ambiente selecionado: $ENV  (namespace: $NAMESPACE)"

kubectl_cmd get svc -n "$NAMESPACE" || true
kubectl_cmd get pods -n "$NAMESPACE" -l "$API_LABEL" -o wide || true
kubectl_cmd get pods -n "$NAMESPACE" -l "$DB_LABEL" -o wide || true
kubectl_cmd get endpoints -n "$NAMESPACE" | grep -E 'mariadb|uniselec-api' || true

# -----------------------------
# LOCALIZAÇÃO DE PODS
# -----------------------------
echolog "Localizando pods de API e MariaDB"

API_POD="$(get_api_pod)"
if [ -z "$API_POD" ]; then
  echo "Não foi possível encontrar pod da API com label '$API_LABEL' no namespace '$NAMESPACE'"
  exit 1
fi
echo "API_POD: $API_POD"

DB_PODS_STR="$(get_db_pods)"
mapfile -t DB_PODS <<<"$DB_PODS_STR"
if [ "${#DB_PODS[@]}" -eq 0 ] || [ -z "$DB_PODS_STR" ]; then
  echo "Não foi possível encontrar pods do MariaDB com label '$DB_LABEL' no namespace '$NAMESPACE'"
  exit 1
fi
echo "DB_PODS: ${DB_PODS[*]}"

# -----------------------------
# 1) TESTE DE LATÊNCIA DNS
# -----------------------------
echolog "1) Teste de latência DNS dentro do pod da API"

measure_dns_from_api "$API_POD" "$DB_SERVICE_APP"       "mariadb-app"
measure_dns_from_api "$API_POD" "$DB_SERVICE_PRIMARY"   "mariadb-primary"
measure_dns_from_api "$API_POD" "$DB_SERVICE_REPLICAS"  "mariadb-replicas"
measure_dns_from_api "$API_POD" "$DB_SERVICE_HEADLESS"  "mariadb (headless)"
measure_dns_from_api "$API_POD" "uniselec-api"          "uniselec-api (service)"

# FQDN de cada pod individualmente (padrão k8s StatefulSet)
# Formato: <pod-name>.<headless-service>.<namespace>.svc.cluster.local
echolog "1b) Teste de DNS para FQDNs individuais dos pods"
for pod in "${DB_PODS[@]}"; do
  # Pula se for linha vazia
  if [ -z "$pod" ]; then
    continue
  fi

  # StatefulSet pod FQDN pattern
  fqdn="${pod}.${DB_SERVICE_HEADLESS}.${NAMESPACE}.svc.cluster.local"
  echo "Testando FQDN: $fqdn"
  measure_dns_from_api "$API_POD" "$fqdn" "pod ${pod}"
done

# -----------------------------
# 2) PHP: TEMPO CONEXÃO x QUERY
# -----------------------------
echolog "2) Teste PHP na API: tempo de conexão vs tempo de query (SELECT 1)"

kubectl_cmd exec -n "$NAMESPACE" "$API_POD" -- php -r "
\$host = '$DB_SERVICE_APP';
\$user = '$MYSQL_USER';
\$pass = '$MYSQL_PASS';
\$db   = '$MYSQL_DB';
\$iterations = $PHP_ITERATIONS;

\$totalConnect = 0.0;
\$totalQuery   = 0.0;
\$success      = 0;

for (\$i = 0; \$i < \$iterations; \$i++) {
    \$t0 = microtime(true);
    \$mysqli = @new mysqli(\$host, \$user, \$pass, \$db);
    \$t1 = microtime(true);

    if (\$mysqli->connect_errno) {
        echo \"Conexão falhou no ciclo \".(\$i+1).\": {\$mysqli->connect_error}\n\";
        continue;
    }

    \$result = \$mysqli->query('SELECT 1');
    \$t2 = microtime(true);

    if (!\$result) {
        echo \"Query falhou no ciclo \".(\$i+1).\": {\$mysqli->error}\n\";
        \$mysqli->close();
        continue;
    }

    \$result->free();
    \$mysqli->close();

    \$totalConnect += (\$t1 - \$t0);
    \$totalQuery   += (\$t2 - \$t1);
    \$success++;
}

if (\$success > 0) {
    \$avgConnectMs = (\$totalConnect / \$success) * 1000.0;
    \$avgQueryMs   = (\$totalQuery   / \$success) * 1000.0;
    echo \"Sucessos: {\$success}/{$PHP_ITERATIONS}\n\";
    echo \"MÉDIA conexão (new mysqli -> $DB_SERVICE_APP): \".number_format(\$avgConnectMs, 3).\" ms\n\";
    echo \"MÉDIA query (SELECT 1):                        \".number_format(\$avgQueryMs, 3).\" ms\n\";
} else {
    echo \"NENHUMA conexão bem-sucedida ao banco.\n\";
}
"

# -----------------------------
# 3) RTT MySQL via services x IP
# -----------------------------
echolog "3) RTT MySQL via serviços (mariadb-app / primary / replicas)"

measure_mysql_rtt "$DB_SERVICE_APP"      "service $DB_SERVICE_APP"
measure_mysql_rtt "$DB_SERVICE_PRIMARY"  "service $DB_SERVICE_PRIMARY"
measure_mysql_rtt "$DB_SERVICE_REPLICAS" "service $DB_SERVICE_REPLICAS"

echolog "3b) RTT MySQL via IP direto dos pods MariaDB"

kubectl_cmd get pods -n "$NAMESPACE" -l "$DB_LABEL" -o wide

for pod in "${DB_PODS[@]}"; do
  pod_ip=$(kubectl_cmd get pod "$pod" -n "$NAMESPACE" -o jsonpath='{.status.podIP}')
  if [ -n "$pod_ip" ]; then
    measure_mysql_rtt "$pod_ip" "pod $pod (IP $pod_ip)"
  else
    echo "Não foi possível obter IP do pod $pod"
  fi
done

# -----------------------------
# 4) TESTE DE PING ENTRE PODS
# -----------------------------
echolog "4) Teste de ping entre API e pods MariaDB"

for pod in "${DB_PODS[@]}"; do
  pod_ip=$(kubectl_cmd get pod "$pod" -n "$NAMESPACE" -o jsonpath='{.status.podIP}')
  if [ -n "$pod_ip" ]; then
    echo "Ping de $API_POD para $pod ($pod_ip):"
    kubectl_cmd exec -n "$NAMESPACE" "$API_POD" -- sh -c "
      if command -v ping >/dev/null 2>&1; then
        ping -c 4 $pod_ip 2>/dev/null | awk -F'/' '/rtt/ {print \"  rtt min/avg/max/mdev = \"\$5\"/\"\$6\"/\"\$7\"/\"\$8\" ms\"} END{if (!found) print \"  ping executado mas sem estatísticas\"}' || echo '  ping falhou'
      else
        echo '  ping não disponível no container'
      fi
    " || echo "  Falha ao executar ping"
  fi
done

# -----------------------------
# 5) TESTE DE CONEXÃO TCP (nc)
# -----------------------------
echolog "5) Teste de conexão TCP (netcat) para porta 3306"

for pod in "${DB_PODS[@]}"; do
  pod_ip=$(kubectl_cmd get pod "$pod" -n "$NAMESPACE" -o jsonpath='{.status.podIP}')
  if [ -n "$pod_ip" ]; then
    echo "Testando TCP para $pod ($pod_ip:3306):"
    kubectl_cmd exec -n "$NAMESPACE" "$API_POD" -- sh -c "
      if command -v nc >/dev/null 2>&1; then
        timeout 3 nc -zv $pod_ip 3306 2>&1 || echo '  Falha na conexão TCP'
      elif command -v telnet >/dev/null 2>&1; then
        timeout 3 telnet $pod_ip 3306 2>&1 | head -3 || echo '  Falha na conexão TCP'
      else
        echo '  nc/telnet não disponível no container'
      fi
    " || echo "  Erro ao testar TCP"
  fi
done

# -----------------------------
# 6) ESTATÍSTICAS DE CLUSTER GALERA
# -----------------------------
echolog "6) Estatísticas do Cluster Galera"

for pod in "${DB_PODS[@]}"; do
  # Pula se for linha vazia
  if [ -z "$pod" ]; then
    continue
  fi

  echo "Stats do pod: $pod"

  # Usar FQDN completo do pod
  pod_fqdn="${pod}.${DB_SERVICE_HEADLESS}.${NAMESPACE}.svc.cluster.local"

  TMP_CLIENT="mysql-stats-$RANDOM"
  kubectl_cmd run "$TMP_CLIENT" -n "$NAMESPACE" --rm -i --restart=Never \
    --image="$MYSQL_IMAGE" -- \
    mariadb -h "$pod_fqdn" -u"$MYSQL_USER" -p"$MYSQL_PASS" -N -e "
      SELECT CONCAT('  wsrep_cluster_size: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_size'
      UNION ALL
      SELECT CONCAT('  wsrep_cluster_status: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_status'
      UNION ALL
      SELECT CONCAT('  wsrep_local_state_comment: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_state_comment'
      UNION ALL
      SELECT CONCAT('  wsrep_ready: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_ready'
      UNION ALL
      SELECT CONCAT('  wsrep_connected: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_connected';
    " 2>/dev/null || echo "  Falha ao obter estatísticas do $pod"
  echo ""
done

# -----------------------------
# 7) RESUMO E RECOMENDAÇÕES
# -----------------------------
echolog "7) RESUMO E RECOMENDAÇÕES"

echo "
┌─────────────────────────────────────────────────────────────────┐
│                    INTERPRETAÇÃO DOS RESULTADOS                 │
├─────────────────────────────────────────────────────────────────┤
│ DNS (bom):           < 10ms                                     │
│ MySQL RTT (bom):     < 50ms                                     │
│ PHP conexão (bom):   < 30ms                                     │
│ PHP query (bom):     < 10ms                                     │
│                                                                 │
│ ⚠️  ATENÇÃO: PHP conexão observada: ~102ms                      │
│    Isso indica overhead no handshake MySQL/SSL                 │
│                                                                 │
│ Se DNS > 10ms:       Problema no CoreDNS/kube-dns              │
│ Se RTT > 100ms:      Problema de rede/CNI                      │
│ Se conexão > query:  Overhead de handshake MySQL               │
│                                                                 │
│ RECOMENDAÇÕES PARA SEU AMBIENTE:                                │
│ ✓ Use connection pooling na aplicação (PgBouncer/ProxySQL)    │
│ ✓ Configure persistent connections no Laravel (.env):          │
│   DB_PERSISTENT=true                                            │
│ ✓ Desabilite SSL interno (cluster local confiável):            │
│   DB_SSL_MODE=disabled                                          │
│ ✓ Aumente pool de conexões do Laravel:                         │
│   DB_MAX_CONNECTIONS=100                                        │
│ ✓ Verifique max_connections do MariaDB (recomendado: 200+)    │
│ ✓ Monitore wsrep_local_state_comment (deve ser 'Synced')      │
│ ✓ Cluster size deve ser 3                                      │
│                                                                 │
│ FERRAMENTAS AUSENTES NO CONTAINER API:                          │
│ • ping - Instale: apt-get install iputils-ping                 │
│ • nc/telnet - Instale: apt-get install netcat-openbsd          │
└─────────────────────────────────────────────────────────────────┘
"

echolog "FIM DO TESTE DE LATÊNCIA DE CONEXÃO APP <-> DB"