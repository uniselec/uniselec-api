#!/bin/bash

set +eu

readonly MAX_ATTEMPTS=25
readonly WAIT_TIME=10
attempts=0

connection_string_dump_con="postgresql://$DB_USER_DUMP:$DB_PASSWORD_DUMP@$HOST_DUMP:$PORT_DUMP/postgres"
connection_string_dump_dump="postgresql://$DB_USER_DUMP:$DB_PASSWORD_DUMP@$HOST_DUMP:$PORT_DUMP/$DB_DATABASE_DUMP"

while [[ $(psql $connection_string_dump_con -c "SELECT count(*) FROM pg_stat_activity WHERE datname = '$DB_DATABASE_DUMP';" -t) -gt 0 ]]
do
  echo "Ainda há atividades de banco de dados. Fechando conexão..."
  psql $connection_string_dump_con -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_DATABASE_DUMP';"
  sleep $WAIT_TIME
  attempts=$((attempts+2))
  if [ $attempts -eq $MAX_ATTEMPTS ]; then
      >&2 echo "Todas atividades encerradas na tentaviva $MAX_ATTEMPTS."
      exit 1
  fi
done
echo "Database PostgreSQL DOWN!"

pg_dump $connection_string_dump_dump --no-owner --no-acl -Fc -b -v -f /tmp/bd_pg_dump.dmp
