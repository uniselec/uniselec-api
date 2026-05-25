#!/bin/bash

set +eu

fn_name=$1

function restore_postgres_prod() {
    local connection_string_root_con="postgresql://$PG_USER_ROOT:$PG_ROOT_PASSWORD@$PG_HOST:$PG_PORT/$PG_DATABASE"
    echo "Gerando o mapa de referencia para lista de objetos..."
    pg_restore --list /tmp/bd_pg_dump.dmp | sed -E 's/(.* EXTENSION )/; \1/g' > /tmp/bd_pg_dump.toc
    echo "Iniciando restore do banco de dados..."
    pg_restore --verbose --no-privileges -j 2 -Fc -c --if-exists -L /tmp/bd_pg_dump.toc -d $connection_string_root_con /tmp/bd_pg_dump.dmp
    if [ "$?" -ne 0 ]; then
        echo "Erro ao restaurar o database!"
        exit 1
    else
        echo "O banco de dados foi restaurado!"
    fi
}

function restore_postgres_homolog() {
    local connection_string_root_con="postgresql://$PG_USER_ROOT:$PG_ROOT_PASSWORD@$PG_HOST:$PG_PORT/$PG_DATABASE_HOMOLOGACAO"
    echo "Gerando o mapa de referencia para lista de objetos..."
    pg_restore --list /tmp/bd_pg_dump.dmp | sed -E 's/(.* EXTENSION )/; \1/g' > /tmp/bd_pg_dump.toc
    echo "Iniciando restore do banco de dados..."
    pg_restore --verbose --no-privileges -j 2 -Fc -c --if-exists -L /tmp/bd_pg_dump.toc -d $connection_string_root_con /tmp/bd_pg_dump.dmp
    if [ "$?" -ne 0 ]; then
        echo "Erro ao restaurar o database!"
        exit 1
    else
        echo "O banco de dados foi restaurado!"
    fi
}

case $fn_name in
    restore_postgres_prod)
        restore_postgres_prod
        ;;
    restore_postgres_homolog)
        restore_postgres_homolog
        ;;
    *)
        echo "Function inexistente!"
        exit 1
        ;;
esac
