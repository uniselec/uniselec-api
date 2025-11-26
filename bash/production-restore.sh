#!/bin/bash
# ============================================================
# Script para restaurar ambiente de produção do cluster Galera
# ============================================================
# Autor: erivandosena@gmail.com
# Data: 2025-11-20
# Versão: 1.0.0
# ============================================================

BACKUP_TIMESTAMP="20231201-020000"

echo "=== RESTORE PRODUCTION ==="

# 1. Parar aplicação
kubectl scale deployment uniselec-api --replicas=0

# 2. Parar cluster Galera
kubectl patch statefulset mariadb -p '{"spec":{"replicas":0}}'

# 3. Aguardar pods pararem
kubectl wait --for=delete pod/mariadb-0 --timeout=360s

# 4. Executar restore
kubectl apply -f job-restore-admin.yaml
kubectl exec job/mariadb-restore-admin -- /scripts/execute-restore-safe.sh $BACKUP_TIMESTAMP

# 5. Reiniciar cluster
kubectl patch statefulset mariadb -p '{"spec":{"replicas":3}}'

# 6. Aguardar cluster estabilizar
kubectl wait --for=condition=ready pod/mariadb-0 --timeout=600s

# 7. Verificar cluster
kubectl exec mariadb-0 -- mariadb -u root -p$MYSQL_ROOT_PASSWORD -e "SHOW STATUS LIKE 'wsrep_cluster_size%'"

# 8. Reiniciar aplicação
kubectl scale deployment uniselec-api --replicas=1

echo "✅ Restore concluído"