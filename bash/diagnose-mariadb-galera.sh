#!/bin/bash
# ============================================================
# Script para testes de latência, flow control, replicação detalhada,
# testes de carga de inserts/queries concorrentes e distribuição do service.
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-11-20
# Versão: 1.0.0
# ============================================================

set -o errexit
set -o nounset
set -o pipefail

NAMESPACE="uniselec-api-stg"
PASSWORD="Password123"
TEST_DB="test_diagnose"
TEST_TABLE="diagnose_test"
LB_SERVICE="mariadb-app"
PODS=(mariadb-0 mariadb-1 mariadb-2)
LB_TEST_ROUNDS=50
CONCURRENT_INSERTS=50
INSERT_ITERATIONS=50

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     Diagnóstico Completo - MariaDB Galera Cluster              ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# --- Helpers ---
kubexec_sql() {
  # $1 = pod, $2 = SQL (must be quoted)
  kubectl exec "$1" -n "$NAMESPACE" -- mariadb -u root -p"$PASSWORD" -N -e "$2" 2>/dev/null || true
}

kubexec_sh() {
  # $1 = pod, $2 = comando shell (quoted)
  kubectl exec "$1" -n "$NAMESPACE" -- sh -c "$2" 2>/dev/null || true
}

ms_now() {
  date +%s%3N
}

safe_rm_test_table() {
  for p in "${PODS[@]}"; do
    kubexec_sql "$p" "DROP TABLE IF EXISTS ${TEST_DB}.${TEST_TABLE};" >/dev/null
  done
}

# Ensure cleanup on exit
trap 'safe_rm_test_table || true' EXIT

# Create test table if DB exists
create_test_table_if_needed() {
  local p=${PODS[0]}
  # Tenta criar o banco se não existir
  if ! kubexec_sql "$p" "SHOW DATABASES;" | grep -q "^${TEST_DB}$"; then
    echo "   📁 Criando banco ${TEST_DB} em ${p}..."
    kubexec_sql "$p" "CREATE DATABASE IF NOT EXISTS ${TEST_DB};"
  fi

  # Agora cria a tabela se o banco existe
  if kubexec_sql "$p" "SHOW DATABASES;" | grep -q "^${TEST_DB}$"; then
    kubexec_sql "$p" "CREATE TABLE IF NOT EXISTS ${TEST_DB}.${TEST_TABLE} (
      id BIGINT AUTO_INCREMENT PRIMARY KEY,
      created TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6),
      payload VARCHAR(255)
    ) ENGINE=InnoDB;" >/dev/null
    echo "   ✅ Tabela ${TEST_TABLE} criada/verificada no banco ${TEST_DB}."
  else
    echo "   ❌ Falha ao criar banco ${TEST_DB} — pulando criação de tabela de teste."
  fi
}

# --- Main loop por pod (mantendo saída/formatacões originais) ---
for i in 0 1 2; do
  POD="mariadb-$i"

  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "📦 POD: $POD"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

  # Info do pod
  kubectl get pod "$POD" -n "$NAMESPACE" -o wide
  echo ""

  # 1. Verificar se MariaDB está rodando
  echo "🔍 Processo MariaDB:"
  kubectl exec "$POD" -n "$NAMESPACE" -- ps aux | grep -E "mariadbd|mysqld" | grep -v grep | head -3 || true
  echo ""

  # 2. Portas abertas
  echo "🔌 Portas Abertas:"
  kubexec_sh "$POD" "
    if ! command -v netstat >/dev/null 2>&1 && ! command -v ss >/dev/null 2>&1; then
      echo '   Instalando ferramentas de rede (silencioso)...'
      apt-get update >/dev/null 2>&1 && apt-get install -y net-tools >/dev/null 2>&1 || \
      yum install -y net-tools >/dev/null 2>&1 || \
      apk add net-tools >/dev/null 2>&1 || \
      echo '   Não foi possível instalar net-tools'
    fi

    if command -v ss >/dev/null 2>&1; then
      ss -tlnp | grep -E '3306|4567|4444|4568' || echo '   Portas não encontradas (ss)'
    elif command -v netstat >/dev/null 2>&1; then
      netstat -tlnp 2>/dev/null | grep -E '3306|4567|4444|4568' || echo '   Portas não encontradas (netstat)'
    else
      echo '   Ferramentas de rede não disponíveis'
    fi
  "
  echo ""

  # 3. Teste de conexão
  echo "✅ Teste de Conexão:"
  if kubexec_sql "$POD" "SELECT 'OK' as status;" | grep -q "OK"; then
    echo "   ✅ Conexão bem-sucedida"
  else
    echo "   ❌ Falha na conexão"
  fi
  echo ""

  # 4. Status CRÍTICO do Galera
  echo "🌐 Status Galera (CRÍTICO):"
  kubexec_sql "$POD" "
    SELECT CONCAT('   wsrep_cluster_size: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_size'
    UNION ALL
    SELECT CONCAT('   wsrep_cluster_status: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_status'
    UNION ALL
    SELECT CONCAT('   wsrep_local_state_comment: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_state_comment'
    UNION ALL
    SELECT CONCAT('   wsrep_ready: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_ready'
    UNION ALL
    SELECT CONCAT('   wsrep_connected: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_connected'
    UNION ALL
    SELECT CONCAT('   wsrep_local_state: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_state'
    UNION ALL
    SELECT CONCAT('   wsrep_evs_state: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_evs_state';
  "
  echo ""

  # 4b. Métricas avançadas do Galera (flow control, cert failures, incoming addresses, last committed, sst donor)
  echo "🔎 Métricas Galera detalhadas:"
  kubexec_sql "$POD" "
    SELECT CONCAT('   wsrep_flow_control_paused: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_flow_control_paused' UNION ALL
    SELECT CONCAT('   wsrep_flow_control_sent: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_flow_control_sent' UNION ALL
    SELECT CONCAT('   wsrep_flow_control_recv: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_flow_control_recv' UNION ALL
    SELECT CONCAT('   wsrep_local_cert_failures: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_cert_failures' UNION ALL
    SELECT CONCAT('   wsrep_local_bf_aborts: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_bf_aborts' UNION ALL
    SELECT CONCAT('   wsrep_last_committed: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_last_committed' UNION ALL
    SELECT CONCAT('   wsrep_incoming_addresses: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_incoming_addresses' UNION ALL
    SELECT CONCAT('   wsrep_sst_donor: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_sst_donor' UNION ALL
    SELECT CONCAT('   wsrep_evs_repl_latency: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_evs_repl_latency';
  " 2>/dev/null || true
  echo ""

  # 5. Conexões ativas
  echo "📊 Conexões Ativas:"
  kubexec_sql "$POD" "SELECT CONCAT('   Total: ', COUNT(*)) FROM information_schema.PROCESSLIST;" 2>/dev/null
  echo ""

  # 6. Bancos de dados
  echo "🗄️  Bancos de Dados:"
  kubexec_sql "$POD" "SHOW DATABASES;" 2>/dev/null | grep -E "uniselec|Database" | sed 's/^/   /' || true
  echo ""

  # 7. Tabelas no banco uniselec_stag (se existir)
  echo "📋 Tabelas em uniselec_stag:"
  kubexec_sql "$POD" "SELECT COUNT(*) as total_tables FROM information_schema.TABLES WHERE TABLE_SCHEMA='${TEST_DB}';" 2>/dev/null | sed 's/^/   /' || true
  echo ""

  # 8. Tamanho dos dados
  echo "💾 Tamanho dos Dados:"
  kubexec_sql "$POD" "SELECT CONCAT('   ', TABLE_SCHEMA, ': ', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2), ' MB') FROM information_schema.TABLES WHERE TABLE_SCHEMA IN ('${TEST_DB}', 'uniselec_prod') GROUP BY TABLE_SCHEMA;" 2>/dev/null || true
  echo ""

  # 9. Latência de conectividade entre este nó e os outros nós (ping)
  echo "📡 Latência de rede entre nós (ping - 4 pacotes):"
  for target in "${PODS[@]}"; do
    if [ "$target" != "$POD" ]; then
      echo -n "   $POD -> $target : "
      kubexec_sh "$POD" "ping -c 4 ${target} 2>/dev/null | awk -F'/' '/rtt/ {print \"rtt avg=\"\$5\" ms\"} END{if (!found) print \"ping falhou ou ICMP bloqueado\"}'" || kubexec_sh "$POD" "echo 'ping indisponível no container'"
    fi
  done
  echo ""

  # 10. RTT de uma query simples (medição cliente-side): SELECT 1
  echo "⏱️ RTT de uma query simples (SELECT 1) medida no cliente (ms):"
  START_MS=$(ms_now)
  kubexec_sql "$POD" "SELECT 1;" >/dev/null
  END_MS=$(ms_now)
  ELAPSED=$((END_MS - START_MS))
  echo "   Tempo de ida e volta (cliente) para SELECT 1: ${ELAPSED} ms"
  echo ""

  # 11. Ver variáveis wsrep específicas adicionais por segurança / diagnóstico
  echo "🔧 Variáveis adicionais (locks/flow/queus etc) — amostra:"
  kubexec_sql "$POD" "SHOW VARIABLES LIKE 'wsrep%';" | sed 's/^/   /' | head -n 40 || true
  echo ""

done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐 SERVICES & ENDPOINTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
kubectl get svc,endpoints -n "$NAMESPACE" | grep mariadb || true
echo ""

# --- Testes de conectividade via service (ampliado) ---
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🧪 TESTE DE CONECTIVIDADE VIA SERVICE (${LB_TEST_ROUNDS} rodadas)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "Testando ${LB_SERVICE} (service principal):"

declare -A LB_COUNT
for p in "${PODS[@]}"; do
  LB_COUNT["$p"]=0
done

for i in $(seq 1 $LB_TEST_ROUNDS); do
  # obtém o hostname onde a conexão foi atendida
  POD_NAME=$(kubectl run mysql-test-$RANDOM --rm -i --restart=Never --image=mariadb:10.11.15-jammy -n "$NAMESPACE" -- \
    mariadb -h "$LB_SERVICE" -u root -p"$PASSWORD" -N -e "SELECT @@hostname;" 2>/dev/null || echo "UNREACHABLE")
  echo "   Tentativa $i: Conectado em $POD_NAME"
  if [[ " ${PODS[*]} " =~ " ${POD_NAME} " ]]; then
    LB_COUNT["$POD_NAME"]=$((LB_COUNT["$POD_NAME"] + 1))
  else
    LB_COUNT["_other"]=$((LB_COUNT["_other"] + 1))
  fi
  sleep 0.1
done

echo ""
echo "Distribuição das conexões via ${LB_SERVICE}:"
for p in "${PODS[@]}"; do
  count=${LB_COUNT["$p"]:-0}
  percent=$(awk -v c="$count" -v t="$LB_TEST_ROUNDS" 'BEGIN{if(t==0){print "0.00"} else printf("%.2f", (c/t)*100)}')
  echo "   $p: $count (${percent}%)"
done
other=${LB_COUNT["_other"]:-0}
if [ "$other" -gt 0 ]; then
  percent=$(awk -v c="$other" -v t="$LB_TEST_ROUNDS" 'BEGIN{printf("%.2f", (c/t)*100)}')
  echo "   Outros/erros: $other (${percent}%)"
fi
echo ""

# --- Cria tabela de teste (se possível) para benchmarks ---
create_test_table_if_needed

# --- Benchmark simples: medição de inserts e selects sequenciais e concorrentes ---
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "⚙️  Benchmark de inserts e selects (medições simples)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# 1) Latência média de INSERT sequencial (INSERT_ITERATIONS)
echo "→ Medindo INSERT sequencial (${INSERT_ITERATIONS} inserts) no service ${LB_SERVICE}:"
total_ms=0
successes=0
for i in $(seq 1 $INSERT_ITERATIONS); do
  START=$(ms_now)
  kubectl run mysql-ins-$RANDOM --rm -i --restart=Never --image=mariadb:10.11.15-jammy -n "$NAMESPACE" -- \
    mariadb -h "$LB_SERVICE" -u root -p"$PASSWORD" -N -e "INSERT INTO ${TEST_DB}.${TEST_TABLE} (payload) VALUES (UUID());" >/dev/null 2>&1 && OK=1 || OK=0
  END=$(ms_now)
  if [ "$OK" -eq 1 ]; then
    elapsed=$((END - START))
    total_ms=$((total_ms + elapsed))
    successes=$((successes + 1))
  fi
done

if [ "$successes" -gt 0 ]; then
  avg_insert=$(awk -v s="$total_ms" -v c="$successes" 'BEGIN{printf("%.2f", s/c)}')
  echo "   Inserts bem-sucedidos: ${successes}/${INSERT_ITERATIONS}, latência média: ${avg_insert} ms"
else
  echo "   Nenhum insert bem-sucedido nos testes sequenciais."
fi
echo ""

# 2) SELECT simples latência (100 queries)
echo "→ Medindo SELECT simples (100 queries, SELECT id,payload ORDER BY id DESC LIMIT 10):"
SEL_ROUNDS=100
total_sel_ms=0
sel_success=0
for i in $(seq 1 $SEL_ROUNDS); do
  START=$(ms_now)
  kubectl run mysql-sel-$RANDOM --rm -i --restart=Never --image=mariadb:10.11.15-jammy -n "$NAMESPACE" -- \
    mariadb -h "$LB_SERVICE" -u root -p"$PASSWORD" -N -e "SELECT id,payload FROM ${TEST_DB}.${TEST_TABLE} ORDER BY id DESC LIMIT 10;" >/dev/null 2>&1 && OK=1 || OK=0
  END=$(ms_now)
  if [ "$OK" -eq 1 ]; then
    elapsed=$((END - START))
    total_sel_ms=$((total_sel_ms + elapsed))
    sel_success=$((sel_success + 1))
  fi
done

if [ "$sel_success" -gt 0 ]; then
  avg_sel=$(awk -v s="$total_sel_ms" -v c="$sel_success" 'BEGIN{printf("%.2f", s/c)}')
  echo "   SELECTs bem-sucedidos: ${sel_success}/${SEL_ROUNDS}, latência média: ${avg_sel} ms"
else
  echo "   Nenhum SELECT bem-sucedido nos testes."
fi
echo ""

# 3) Teste de inserts concorrentes (CONCURRENT_INSERTS workers, cada um com INSERT_ITERATIONS/CONCURRENT_INSERTS inserts)
echo "→ Teste de inserts concorrentes: ${CONCURRENT_INSERTS} workers (cada worker fará ~$(awk -v a="$INSERT_ITERATIONS" -v b="$CONCURRENT_INSERTS" 'BEGIN{printf("%d", a/b)}') inserts)"
pids=()
start_total=$(ms_now)
for w in $(seq 1 $CONCURRENT_INSERTS); do
  (
    for j in $(seq 1 $(awk -v a="$INSERT_ITERATIONS" -v b="$CONCURRENT_INSERTS" 'BEGIN{printf("%d", a/b)}')); do
      kubectl run mysql-par-$RANDOM --rm -i --restart=Never --image=mariadb:10.11.15-jammy -n "$NAMESPACE" -- \
        mariadb -h "$LB_SERVICE" -u root -p"$PASSWORD" -N -e "INSERT INTO ${TEST_DB}.${TEST_TABLE} (payload) VALUES (UUID());" >/dev/null 2>&1 || true
    done
  ) &
  pids+=($!)
done

# wait for all
for pid in "${pids[@]}"; do
  wait "$pid" || true
done
end_total=$(ms_now)
elapsed_total=$((end_total - start_total))
echo "   Teste de concorrência finalizado em ${elapsed_total} ms (wall-clock)."
echo ""

# --- Verificação de conflitos/aborts/amostras após carga ---
echo "🔔 Checando conflitos/aborts e flow control após carga:"
for p in "${PODS[@]}"; do
  echo "   Nodo: $p"
  kubexec_sql "$p" "SELECT CONCAT('      wsrep_local_cert_failures: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_cert_failures';"
  kubexec_sql "$p" "SELECT CONCAT('      wsrep_local_bf_aborts: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_bf_aborts';"
  kubexec_sql "$p" "SELECT CONCAT('      wsrep_flow_control_paused: ', VARIABLE_VALUE) FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_flow_control_paused';"
done
echo ""

# --- Resumo do cluster (mantendo a parte original) ---
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 RESUMO DO CLUSTER"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

kubectl run mysql-summary --rm -i --restart=Never --image=mariadb:10.11.15-jammy -n "$NAMESPACE" -- \
  mariadb -h "$LB_SERVICE" -u root -p"$PASSWORD" -e "
    SELECT
      '=== CLUSTER STATUS ===' as '';
    SELECT
      @@hostname as 'Connected to Pod',
      @@server_id as 'Server ID',
      (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_size') as 'Cluster Size',
      (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_state_comment') as 'Node State',
      (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_status') as 'Cluster Status';
  " 2>/dev/null || true

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    Diagnóstico Concluído                       ║"
echo "╚════════════════════════════════════════════════════════════════╝"