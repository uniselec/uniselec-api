#!/bin/bash
# ================================================================
# Script de Recuperação Galera Cluster - Split Brain
# ================================================================

set -e

NAMESPACE="uniselec-api-stg"

echo "🚨 INICIANDO RECUPERAÇÃO DO CLUSTER GALERA"
echo ""

# 1. Escalar para 0
echo "📉 1. Parando todos os pods..."
kubectl scale statefulset mariadb -n $NAMESPACE --replicas=0
sleep 10

# 2. Verificar pods terminados
echo "✅ 2. Verificando pods..."
kubectl get pods -n $NAMESPACE -l app=mariadb

# 3. Limpar grastate.dat em TODOS os PVCs
echo "🧹 3. Limpando grastate.dat dos PVCs..."
for i in 0 1 2; do
  PVC="database-mariadb-$i"

  echo "   Limpando $PVC..."

  kubectl run cleanup-$i -n $NAMESPACE --rm -i --restart=Never \
    --image=busybox:latest \
    --overrides="{
      \"spec\": {
        \"containers\": [{
          \"name\": \"cleanup\",
          \"image\": \"busybox:latest\",
          \"command\": [\"sh\", \"-c\", \"rm -f /data/grastate.dat /data/gvwstate.dat && echo 'Arquivos removidos' && ls -la /data/ | grep -E 'grastate|gvwstate' || echo 'Nenhum arquivo encontrado'\"],
          \"volumeMounts\": [{
            \"name\": \"data\",
            \"mountPath\": \"/data\"
          }]
        }],
        \"volumes\": [{
          \"name\": \"data\",
          \"persistentVolumeClaim\": {
            \"claimName\": \"$PVC\"
          }
        }]
      }
    }" 2>&1 | grep -v "warning:" || true

  sleep 2
done

echo ""
echo "📈 4. Escalando de volta para 1 pod (bootstrap)..."
kubectl scale statefulset mariadb -n $NAMESPACE --replicas=1

echo "⏳ 5. Aguardando mariadb-0 ficar READY..."
kubectl wait --for=condition=ready pod/mariadb-0 -n $NAMESPACE --timeout=300s

echo "🔍 6. Verificando cluster_size do mariadb-0..."
kubectl exec mariadb-0 -n $NAMESPACE -- bash -c '
  mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW STATUS LIKE '\''wsrep_cluster_size'\'';"
'

echo ""
echo "📈 7. Escalando para 3 pods..."
kubectl scale statefulset mariadb -n $NAMESPACE --replicas=3

echo "⏳ 8. Aguardando todos os pods ficarem READY..."
kubectl wait --for=condition=ready pod -l app=mariadb -n $NAMESPACE --timeout=600s

echo ""
echo "✅ 9. Verificando cluster final..."
for i in 0 1 2; do
  echo "   📊 mariadb-$i:"
  kubectl exec mariadb-$i -n $NAMESPACE -- bash -c '
    mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW STATUS LIKE '\''wsrep_cluster_size'\'';" 2>/dev/null
  ' | grep wsrep_cluster_size || echo "   ⚠️ Falha ao conectar"
done

echo ""
echo "🎉 RECUPERAÇÃO CONCLUÍDA!"
echo ""
echo "Execute o diagnóstico novamente:"
echo "bash bash/diagnose-mariadb-galera.sh"
echo ""

# 1. Cluster size = 3 em todos
for i in 0 1 2; do
  echo "=== mariadb-$i ==="
  kubectl exec mariadb-$i -n uniselec-api-stg -- \
    mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" \
    -e "SHOW STATUS LIKE 'wsrep_%';" | grep -E "cluster_size|local_state_comment|ready"
done

# 2. Todos com 21 tabelas
for i in 0 1 2; do
  echo "=== mariadb-$i ==="
  kubectl exec mariadb-$i -n uniselec-api-stg -- \
    mariadb -u root -p"${MYSQL_ROOT_PASSWORD}" uniselec_stag \
    -e "SELECT COUNT(*) as tables FROM information_schema.tables WHERE table_schema='uniselec_stag';"
done

# 3. Testar conectividade
bash bash/diagnose-mariadb-galera.sh