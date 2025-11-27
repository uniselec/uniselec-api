#!/bin/bash

# ============================================================
# Script para diagnóstico de performance entre aplicação e banco de dados
# Diagnóstico de performance app <-> banco - MariaDB Galera (Kubernetes)
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-11-20
# Versão: 1.0.0
# ============================================================

NAMESPACE="uniselec-api-stg"
MYSQL_USER="root"
MYSQL_PASS="Password123"
MYSQL_DB="uniselec_stag"
MYSQL_TABLE="users"
API_LABEL="app=uniselec-api"
DB_LABEL="app=mariadb"
PVC_PATH="/var/lib/mysql"

API_POD=$(kubectl get pods -n $NAMESPACE -l $API_LABEL -o jsonpath='{.items[0].metadata.name}')
DB_POD=$(kubectl get pods -n $NAMESPACE -l $DB_LABEL -o jsonpath='{.items[0].metadata.name}')

echo "==== 1. Teste de queries complexas na aplicação (PHP/MySQLi) ===="
kubectl exec -n $NAMESPACE $API_POD -- php -r "
\$mysqli = new mysqli('mariadb-app', '$MYSQL_USER', '$MYSQL_PASS', '$MYSQL_DB');
if (\$mysqli->connect_errno) { echo 'Erro conexão: '.\$mysqli->connect_error; exit(1); }
\$start = microtime(true);
\$result = \$mysqli->query('SELECT COUNT(*), MAX(id), AVG(id) FROM $MYSQL_TABLE');
\$elapsed = microtime(true)-\$start;
echo 'Query complexa: '.number_format(\$elapsed,4).'s\n';
"

echo "==== 2. Teste de uso alto de disco PVC no MariaDB ===="
kubectl exec -n $NAMESPACE $DB_POD -- df -h $PVC_PATH
kubectl exec -n $NAMESPACE $DB_POD -- du -m $PVC_PATH | sort -rn | head -10

echo "==== 3. Teste de overloaded aplicação (replicas/cpu/mem) ===="
REPLICAS=$(kubectl get hpa -n $NAMESPACE uniselec-api -o jsonpath='{.status.currentReplicas}' 2>/dev/null)
CPU=$(kubectl top pods -n $NAMESPACE --containers | grep $API_POD | awk '{print $3}' | head -1)
MEM=$(kubectl top pods -n $NAMESPACE --containers | grep $API_POD | awk '{print $4}' | head -1)
echo "Replicas API: $REPLICAS"
echo "CPU API: $CPU"
echo "MEM API: $MEM"

echo "==== 4. Benchmark HTTP e framework aplicação ===="
kubectl exec -n $NAMESPACE $API_POD -- ab -n 10 -c 2 http://localhost/up || echo "ApacheBench não disponível no container."
kubectl exec -n $NAMESPACE $API_POD -- tail -20 /var/www/html/storage/logs/laravel.log 2>/dev/null || echo "Log Laravel não encontrado ou não disponível"

echo "==== 5. Teste e análise de queries lentas no banco ===="
SLOW_LOG_PATH=$(kubectl exec -n $NAMESPACE $DB_POD -- mysql -u$MYSQL_USER -p$MYSQL_PASS -e "SHOW VARIABLES LIKE 'slow_query_log_file';" | grep '/' | awk '{print $2}' | tail -1)
if [ -n "$SLOW_LOG_PATH" ]; then
  kubectl exec -n $NAMESPACE $DB_POD -- tail -30 "$SLOW_LOG_PATH"
else
  echo "Arquivo slow query log não encontrado/configurado"
fi
