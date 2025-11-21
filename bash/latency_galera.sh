#!/usr/bin/env bash
set -euo pipefail
IFS=$'\n\t'

# ======================================================
# Script paralelo de latência real de replicação Galera
# ======================================================

NODES=("mariadb-0" "mariadb-1" "mariadb-2")
NAMESPACE="uniselec-api-dev"
DB="tests"
TABLE="latency_test"
ITERATIONS=3

# Usuário e senha do MariaDB (em produção, use Secrets!)
DB_USER="root"
DB_PASSWORD="Password123"

echo "=== Teste paralelo otimizado de latência Galera Cluster ==="
echo "Nós: ${NODES[*]}"
echo ""

# Cria banco e tabela se não existirem
for NODE in "${NODES[@]}"; do
    echo "--- Verificando banco e tabela no nó $NODE ---"
    kubectl exec -n "$NAMESPACE" "$NODE" -- mariadb -u "$DB_USER" -p"$DB_PASSWORD" -e \
        "CREATE DATABASE IF NOT EXISTS $DB; USE $DB; \
         CREATE TABLE IF NOT EXISTS $TABLE (id INT AUTO_INCREMENT PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);" >/dev/null
    echo ""
done

for ((iter=1; iter<=ITERATIONS; iter++)); do
    echo "=== Iteração $iter ==="

    declare -A INSERT_TIMES
    declare -A INSERT_IDS
    JOBS=()

    # Inserção em paralelo
    for ORIGIN in "${NODES[@]}"; do
        TMP_FILE=$(mktemp)
        (
            INSERT_ID=$(kubectl exec -n "$NAMESPACE" "$ORIGIN" -c mariadb -- mariadb \
                -u"$DB_USER" -p"$DB_PASSWORD" -e \
                "USE $DB; INSERT INTO $TABLE () VALUES (); SELECT LAST_INSERT_ID();" -s -N)
            INSERT_TIME=$(kubectl exec -n "$NAMESPACE" "$ORIGIN" -c mariadb -- mariadb \
                -u"$DB_USER" -p"$DB_PASSWORD" -e \
                "USE $DB; SELECT UNIX_TIMESTAMP(created_at)*1000 FROM $TABLE WHERE id=$INSERT_ID;" -s -N)
            echo "$ORIGIN|$INSERT_ID|$INSERT_TIME" > "$TMP_FILE"
        ) &
        JOBS+=("$TMP_FILE")
    done

    # Espera todas as inserções terminarem e lê os resultados
    for TMP_FILE in "${JOBS[@]}"; do
        wait
        IFS='|' read -r ORIGIN INSERT_ID INSERT_TIME < "$TMP_FILE"
        INSERT_IDS["$ORIGIN"]=$INSERT_ID
        INSERT_TIMES["$ORIGIN"]=$INSERT_TIME
        rm -f "$TMP_FILE"
        echo "Timestamp (ms) no nó $ORIGIN: $INSERT_TIME"
    done

    echo ""

    # Medição da latência de replicação
    for ORIGIN in "${NODES[@]}"; do
        ORIGIN_TS=${INSERT_TIMES[$ORIGIN]}
        for TARGET in "${NODES[@]}"; do
            if [ "$ORIGIN" == "$TARGET" ]; then
                DIFF_MS=0
            else
                # Espera até o registro aparecer no nó TARGET
                while true; do
                    TARGET_TS=$(kubectl exec -n "$NAMESPACE" "$TARGET" -c mariadb -- mariadb \
                        -u"$DB_USER" -p"$DB_PASSWORD" -e \
                        "USE $DB; SELECT UNIX_TIMESTAMP(created_at)*1000 FROM $TABLE WHERE id=${INSERT_IDS[$ORIGIN]};" -s -N || echo "")
                    if [ -n "$TARGET_TS" ]; then
                        break
                    fi
                    sleep 0.01
                done
                DIFF_MS=$((TARGET_TS - ORIGIN_TS))
            fi
            printf "Origem: %s -> Destino: %s | Latência real ~ %d ms\n" "$ORIGIN" "$TARGET" "$DIFF_MS"
        done
        echo ""
    done

done

echo "=== Teste concluído! ==="