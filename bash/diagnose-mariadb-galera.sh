#!/bin/bash
# diagnose-galera-complete.sh

NAMESPACE="uniselec-api-stg"
PASSWORD="Password123"

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     Diagnóstico Completo - MariaDB Galera Cluster              ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

for i in 0 1 2; do
  POD="mariadb-$i"

  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
  echo "📦 POD: $POD"
  echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

  # Info do pod
  kubectl get pod $POD -n $NAMESPACE -o wide
  echo ""

  # 1. Verificar se MariaDB está rodando
  echo "🔍 Processo MariaDB:"
  kubectl exec $POD -n $NAMESPACE -- ps aux | grep -E "mariadbd|mysqld" | grep -v grep | head -3
  echo ""

  # 2. Portas abertas
  echo "🔌 Portas Abertas:"
  kubectl exec $POD -n $NAMESPACE -- sh -c "
    # Tentar instalar netstat silenciosamente se não estiver disponível
    if ! command -v netstat >/dev/null 2>&1 && ! command -v ss >/dev/null 2>&1; then
      echo '   Instalando ferramentas de rede...'
      apt-get update >/dev/null 2>&1 && apt-get install -y net-tools >/dev/null 2>&1 || \
      yum install -y net-tools >/dev/null 2>&1 || \
      apk add net-tools >/dev/null 2>&1 || \
      echo '   Não foi possível instalar net-tools'
    fi

    # Agora tentar usar as ferramentas
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
  if kubectl exec $POD -n $NAMESPACE -- mariadb -u root -p"$PASSWORD" -e "SELECT 'OK' as status;" 2>/dev/null | grep -q "OK"; then
    echo "   ✅ Conexão bem-sucedida"
  else
    echo "   ❌ Falha na conexão"
  fi
  echo ""

  # 4. Status CRÍTICO do Galera
  echo "🌐 Status Galera (CRÍTICO):"
  kubectl exec $POD -n $NAMESPACE -- mariadb -u root -p"$PASSWORD" -N -e "
    SELECT
      CONCAT('   wsrep_cluster_size: ', VARIABLE_VALUE)
    FROM information_schema.GLOBAL_STATUS
    WHERE VARIABLE_NAME='wsrep_cluster_size'
    UNION ALL
    SELECT
      CONCAT('   wsrep_cluster_status: ', VARIABLE_VALUE)
    FROM information_schema.GLOBAL_STATUS
    WHERE VARIABLE_NAME='wsrep_cluster_status'
    UNION ALL
    SELECT
      CONCAT('   wsrep_local_state_comment: ', VARIABLE_VALUE)
    FROM information_schema.GLOBAL_STATUS
    WHERE VARIABLE_NAME='wsrep_local_state_comment'
    UNION ALL
    SELECT
      CONCAT('   wsrep_ready: ', VARIABLE_VALUE)
    FROM information_schema.GLOBAL_STATUS
    WHERE VARIABLE_NAME='wsrep_ready'
    UNION ALL
    SELECT
      CONCAT('   wsrep_connected: ', VARIABLE_VALUE)
    FROM information_schema.GLOBAL_STATUS
    WHERE VARIABLE_NAME='wsrep_connected'
    UNION ALL
    SELECT
      CONCAT('   wsrep_local_state: ', VARIABLE_VALUE)
    FROM information_schema.GLOBAL_STATUS
    WHERE VARIABLE_NAME='wsrep_local_state'
    UNION ALL
    SELECT
      CONCAT('   wsrep_evs_state: ', VARIABLE_VALUE)
    FROM information_schema.GLOBAL_STATUS
    WHERE VARIABLE_NAME='wsrep_evs_state';
  " 2>/dev/null
  echo ""

  # 5. Conexões ativas
  echo "📊 Conexões Ativas:"
  kubectl exec $POD -n $NAMESPACE -- mariadb -u root -p"$PASSWORD" -N -e "
    SELECT CONCAT('   Total: ', COUNT(*)) FROM information_schema.PROCESSLIST;
  " 2>/dev/null
  echo ""

  # 6. Bancos de dados
  echo "🗄️  Bancos de Dados:"
  kubectl exec $POD -n $NAMESPACE -- mariadb -u root -p"$PASSWORD" -e "
    SHOW DATABASES;
  " 2>/dev/null | grep -E "uniselec|Database" | sed 's/^/   /'
  echo ""

  # 7. Tabelas no banco uniselec_stag (se existir)
  echo "📋 Tabelas em uniselec_stag:"
  kubectl exec $POD -n $NAMESPACE -- mariadb -u root -p"$PASSWORD" -e "
    SELECT COUNT(*) as total_tables FROM information_schema.TABLES WHERE TABLE_SCHEMA='uniselec_stag';
  " 2>/dev/null | sed 's/^/   /'
  echo ""

  # 8. Tamanho dos dados
  echo "💾 Tamanho dos Dados:"
  kubectl exec $POD -n $NAMESPACE -- mariadb -u root -p"$PASSWORD" -N -e "
    SELECT
      CONCAT('   ', TABLE_SCHEMA, ': ',
      ROUND(SUM(data_length + index_length) / 1024 / 1024, 2), ' MB')
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA IN ('uniselec_stag', 'uniselec_prod')
    GROUP BY TABLE_SCHEMA;
  " 2>/dev/null
  echo ""

  echo ""
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🌐 SERVICES & ENDPOINTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
kubectl get svc,endpoints -n $NAMESPACE | grep mariadb
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🧪 TESTE DE CONECTIVIDADE VIA SERVICE"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "Testando mariadb-app (service principal):"
for i in {1..5}; do
  POD_NAME=$(kubectl run mysql-test-$RANDOM --rm -i --restart=Never --image=mariadb:11.3.2 -n $NAMESPACE -- \
    mariadb -h mariadb-app -u root -p"$PASSWORD" -N -e "SELECT @@hostname;" 2>/dev/null)
  echo "   Tentativa $i: Conectado em $POD_NAME"
  sleep 1
done
echo ""

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 RESUMO DO CLUSTER"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

kubectl run mysql-summary --rm -i --restart=Never --image=mariadb:11.3.2 -n $NAMESPACE -- \
  mariadb -h mariadb-app -u root -p"$PASSWORD" -e "
    SELECT
      '=== CLUSTER STATUS ===' as '';
    SELECT
      @@hostname as 'Connected to Pod',
      @@server_id as 'Server ID',
      (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_size') as 'Cluster Size',
      (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_local_state_comment') as 'Node State',
      (SELECT VARIABLE_VALUE FROM information_schema.GLOBAL_STATUS WHERE VARIABLE_NAME='wsrep_cluster_status') as 'Cluster Status';
  " 2>/dev/null

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                    Diagnóstico Concluído                       ║"
echo "╚════════════════════════════════════════════════════════════════╝"