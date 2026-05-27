#!/bin/bash

BACKUP_TIMESTAMP="20231201-020000"

echo "=== RESTORE PRODUCTION ==="

kubectl scale deployment uniselec-api --replicas=0

kubectl patch statefulset mariadb -p '{"spec":{"replicas":0}}'

kubectl wait --for=delete pod/mariadb-0 --timeout=360s

kubectl exec deployment/mariadb-restore-admin -- /scripts/execute-restore-safe.sh $BACKUP_TIMESTAMP

kubectl patch statefulset mariadb -p '{"spec":{"replicas":3}}'

kubectl wait --for=condition=ready pod/mariadb-0 --timeout=600s

kubectl exec mariadb-0 -- mariadb -u root -p$MYSQL_ROOT_PASSWORD -e "SHOW STATUS LIKE 'wsrep_cluster_size%'"

kubectl scale deployment uniselec-api --replicas=1

echo "Restore concluido"