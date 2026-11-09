#!/usr/bin/env bash
#
# Runs once, on the first start of an EMPTY data volume (docker-entrypoint-initdb.d).
# Creates the service's application role (NOT a superuser) and its own database,
# owned by that role (ADR-012: one database per service, own user and password).
#
# Shared by the 4 database containers (identity, commerce, evidence, notification);
# each container passes its own APP_DB_NAME / APP_DB_USER / APP_DB_PASSWORD.
set -euo pipefail

: "${APP_DB_NAME:?APP_DB_NAME não definido}"
: "${APP_DB_USER:?APP_DB_USER não definido}"
: "${APP_DB_PASSWORD:?APP_DB_PASSWORD não definido}"

echo ">> Criando role '${APP_DB_USER}' e banco '${APP_DB_NAME}'"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres \
     -v app_db="$APP_DB_NAME" -v app_user="$APP_DB_USER" -v app_pass="$APP_DB_PASSWORD" <<'EOSQL'
CREATE ROLE :"app_user" LOGIN PASSWORD :'app_pass' NOSUPERUSER NOCREATEDB NOCREATEROLE;
CREATE DATABASE :"app_db" OWNER :"app_user" ENCODING 'UTF8';
REVOKE ALL ON DATABASE :"app_db" FROM PUBLIC;
GRANT ALL ON DATABASE :"app_db" TO :"app_user";
EOSQL
