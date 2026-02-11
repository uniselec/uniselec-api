#!/bin/bash

###############################################################################
# 
# Nome do arquivo: dump-database.sh
# Autor: Erivando Sena <erivandoramos@unilab.edu.br>
# Data de criacao: 29/04/2023
#
# Descricao: Este script foi desenvolvido como parte do projeto [Stack DEVOPS DTI] da 
#            Universidade da Integracao Internacional da Lusofonia Afro-Brasileira (UNILAB).
#
# Direitos autorais (c) 2023 Erivando Sena/UNILAB.
#
# E concedida permissao para usar, copiar, modificar e distribuir este software apenas para 
# uso pessoal ou em sua organizacao, desde que este aviso de direitos autorais apareca em 
# todas as copias. 
# Este software e fornecido "como esta" e sem garantias expressas ou implicitas, incluindo, 
# mas nao se limitando a, garantias implicitas de comercializacao e adequacao a um proposito 
# especifico. 
# Em nenhum caso, o autor sera responsavel por quaisquer danos diretos, indiretos, 
# incidentais, especiais, exemplares ou consequentes (incluindo, mas nao se limitando 
# a, aquisicao de bens ou servicos substitutos, perda de uso, dados ou lucros, ou 
# interrupcao dos negocios) decorrentes do uso, incapacidade de uso ou resultados do 
# uso deste software.
#
# Este programa e distribuido na esperanca de que possa ser util, mas SEM NENHUMA 
# GARANTIA; sem uma garantia implicita de ADEQUACAO a qualquer MERCADO ou APLICACAO EM PARTICULAR.
# Veja a Licenca Publica Geral GNU para mais detalhes.
#
####################################################################################################

# Connection options:
#   -h, --host=HOSTNAME      host do servidor de banco de dados
#   -p, --port=PORT          porta do servidor de banco de dados
#   -U, --username=USERNAME  nome de usuario do banco de dados
#   -w, --no-password        nunca solicitar senha
#   -W, --password           forcar prompt de senha (should happen automatically)

set +eu

readonly MAX_ATTEMPTS=25
readonly WAIT_TIME=10
attempts=0

connection_string_dump_con="postgresql://$DB_USER_DUMP:$DB_PASSWORD_DUMP@$HOST_DUMP:$PORT_DUMP/postgres" 
connection_string_dump_dump="postgresql://$DB_USER_DUMP:$DB_PASSWORD_DUMP@$HOST_DUMP:$PORT_DUMP/$DB_DATABASE_DUMP" 

# Aguardar até não haver atividades no banco de dados
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

# Realiza backup por dump incluindo objetos grandes
pg_dump $connection_string_dump_dump --no-owner --no-acl -Fc -b -v -f /tmp/bd_pg_dump.dmp
