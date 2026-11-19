#!/usr/bin/env bash
#
# Veridit — configuração ÚNICA da produção local (Identity :8080 e Commerce :8081)
#
# Uso (no Git Bash, a partir da raiz do repositório):
#   bash deploy/setup-production.sh [PROD_ROOT]      # padrão: /c/veridit  (= C:\veridit)
#
# Cria:
#   PROD_ROOT/producao.git      repositório bare + hook post-receive
#   PROD_ROOT/producao-app/     pasta da produção, com os arquivos de ambiente (segredos aleatórios):
#       .env.production                     senhas dos 4 bancos (orquestração)
#       backend/identity/.env.production    Identity (só a senha do SEU banco; par de chaves do JWT)
#       backend/commerce/.env.production    Commerce (só a senha do SEU banco; só a chave PÚBLICA do JWT)
#   remote "production"         neste repositório
#
# Idempotente: pode rodar de novo (reinstala o hook; NÃO troca segredos existentes).
# Também migra uma produção do layout antigo (um .env.production único na raiz + commerce/),
# guardando um backup do arquivo original.
set -euo pipefail

PROD_ROOT="${1:-/c/veridit}"
BARE_REPO="$PROD_ROOT/producao.git"
DEPLOY_DIR="$PROD_ROOT/producao-app"
INFRA_ENV="$DEPLOY_DIR/.env.production"
IDENTITY_ENV="$DEPLOY_DIR/backend/identity/.env.production"
COMMERCE_ENV="$DEPLOY_DIR/backend/commerce/.env.production"

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

for tool in git docker openssl; do
  command -v "$tool" >/dev/null || { echo "!! '$tool' não encontrado no PATH." >&2; exit 1; }
done
docker info >/dev/null 2>&1 || { echo "!! Docker não está rodando. Abra o Docker Desktop e tente de novo." >&2; exit 1; }

# Helpers ----------------------------------------------------------------------
hex()     { openssl rand -hex "$1"; }
app_key() { echo "base64:$(openssl rand -base64 32)"; }
env_value() { grep -E "^$1=" "$2" | head -1 | cut -d= -f2- || true; }

# RSA pair for the JWT (RS256), as single-line "base64:<PEM>" values (see App\Support\JwtKey)
b64_oneline() { openssl base64 -A; }
gen_jwt_keys() {
  local pem
  pem="$(openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 2>/dev/null)"
  JWT_PRIVATE="base64:$(printf '%s\n' "$pem" | b64_oneline)"
  JWT_PUBLIC="base64:$(printf '%s\n' "$pem" | openssl pkey -pubout 2>/dev/null | b64_oneline)"
}

# Valid CPF with random digits (check digits computed; no repeated sequence)
gen_cpf() {
  local d i s r w
  while :; do
    d=(); for i in 0 1 2 3 4 5 6 7 8; do d+=($((RANDOM % 10))); done
    for i in 1 2 3 4 5 6 7 8; do [ "${d[$i]}" != "${d[0]}" ] && break 2; done
  done
  for w in 10 11; do
    s=0; for i in "${!d[@]}"; do s=$((s + d[i] * (w - i))); done
    r=$(((s * 10) % 11)); [ "$r" -eq 10 ] && r=0
    d+=("$r")
  done
  printf '%s' "${d[@]}"
}

assert_filled() { # file label
  if grep -qE '^[^#]*=(gerar|copiar)-' "$1"; then
    echo "!! Sobrou valor 'gerar-*'/'copiar-*' em $1" >&2; exit 1
  fi
  chmod 600 "$1" 2>/dev/null || true
}

echo ">> [1/4] Repositório bare em $BARE_REPO"
if [ ! -d "$BARE_REPO" ]; then
  git init --bare -q "$BARE_REPO"
  git --git-dir="$BARE_REPO" symbolic-ref HEAD refs/heads/main
else
  echo "   (já existe)"
fi

echo ">> [2/4] Instalando o hook post-receive"
sed "s|__DEPLOY_DIR__|$DEPLOY_DIR|" deploy/post-receive > "$BARE_REPO/hooks/post-receive"
chmod +x "$BARE_REPO/hooks/post-receive"

echo ">> [3/4] Arquivos de ambiente em $DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR/backend/identity" "$DEPLOY_DIR/backend/commerce"

# --- Migration from the old layout: one .env.production at the root (Identity + all DB passwords)
if [ -f "$INFRA_ENV" ] && [ ! -f "$IDENTITY_ENV" ] && grep -qE '^APP_KEY=' "$INFRA_ENV"; then
  backup="$INFRA_ENV.bak-$(date +%Y%m%d%H%M%S)"
  cp "$INFRA_ENV" "$backup"
  chmod 600 "$backup" 2>/dev/null || true
  echo "   layout antigo encontrado: migrando (backup em $backup)"

  # Identity keeps everything except the passwords of the other databases (ADR-012)
  grep -vE '^(POSTGRES_ADMIN_PASSWORD|COMMERCE_DB_PASSWORD|EVIDENCE_DB_PASSWORD|NOTIFICATION_DB_PASSWORD)=' "$backup" \
    | grep -vE '^# Usados só pelo docker compose para criar os bancos' > "$IDENTITY_ENV"

  {
    echo "# Veridit — orquestração da PRODUÇÃO LOCAL (migrado do layout antigo em $(date -u +%Y-%m-%dT%H:%M:%SZ))"
    echo "POSTGRES_ADMIN_PASSWORD=$(env_value POSTGRES_ADMIN_PASSWORD "$backup")"
    echo "IDENTITY_DB_PASSWORD=$(env_value DB_PASSWORD "$backup")"
    echo "COMMERCE_DB_PASSWORD=$(env_value COMMERCE_DB_PASSWORD "$backup")"
    echo "EVIDENCE_DB_PASSWORD=$(env_value EVIDENCE_DB_PASSWORD "$backup")"
    echo "NOTIFICATION_DB_PASSWORD=$(env_value NOTIFICATION_DB_PASSWORD "$backup")"
  } > "$INFRA_ENV"

  if grep -qE '^[A-Z_]+=$' "$INFRA_ENV"; then
    echo "!! Senha vazia ao migrar $INFRA_ENV; o original está em $backup" >&2; exit 1
  fi
  assert_filled "$INFRA_ENV"
  assert_filled "$IDENTITY_ENV"
fi
if [ -f "$DEPLOY_DIR/commerce/.env.production" ] && [ ! -f "$COMMERCE_ENV" ]; then
  mv "$DEPLOY_DIR/commerce/.env.production" "$COMMERCE_ENV"
  echo "   commerce/.env.production movido para backend/commerce/"
fi

# --- Orchestration: passwords of the 4 databases
if [ -f "$INFRA_ENV" ]; then
  echo "   .env.production (bancos) já existe: mantido"
else
  sed -e "s|^POSTGRES_ADMIN_PASSWORD=.*|POSTGRES_ADMIN_PASSWORD=$(hex 24)|" \
      -e "s|^IDENTITY_DB_PASSWORD=.*|IDENTITY_DB_PASSWORD=$(hex 24)|" \
      -e "s|^COMMERCE_DB_PASSWORD=.*|COMMERCE_DB_PASSWORD=$(hex 24)|" \
      -e "s|^EVIDENCE_DB_PASSWORD=.*|EVIDENCE_DB_PASSWORD=$(hex 24)|" \
      -e "s|^NOTIFICATION_DB_PASSWORD=.*|NOTIFICATION_DB_PASSWORD=$(hex 24)|" \
      .env.production.example > "$INFRA_ENV"
  assert_filled "$INFRA_ENV"
  echo "   .env.production (bancos) criado com senhas aleatórias"
fi

# --- Identity
if [ -f "$IDENTITY_ENV" ]; then
  echo "   backend/identity/.env.production já existe: mantido"
  # Upgrade from HS256: add the RS256 pair if the file still has no private key
  if ! grep -qE '^JWT_PRIVATE_KEY=base64:' "$IDENTITY_ENV"; then
    gen_jwt_keys
    sed -i -e '/^JWT_SECRET=/d' -e '/^JWT_ALGO=/d' -e '/^JWT_PRIVATE_KEY=/d' -e '/^JWT_PUBLIC_KEY=/d' "$IDENTITY_ENV"
    printf '\n# JWT RS256 (adicionado pelo setup-production.sh)\nJWT_ALGO=RS256\nJWT_PRIVATE_KEY=%s\nJWT_PUBLIC_KEY=%s\n' \
      "$JWT_PRIVATE" "$JWT_PUBLIC" >> "$IDENTITY_ENV"
    echo "   chaves RS256 do JWT adicionadas (tokens antigos HS256 deixam de valer)"
  fi
else
  admin_password="Adm$(hex 10)7"
  gen_jwt_keys
  sed -e "s|^APP_KEY=.*|APP_KEY=$(app_key)|" \
      -e "s|^JWT_PRIVATE_KEY=.*|JWT_PRIVATE_KEY=$JWT_PRIVATE|" \
      -e "s|^JWT_PUBLIC_KEY=.*|JWT_PUBLIC_KEY=$JWT_PUBLIC|" \
      -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(env_value IDENTITY_DB_PASSWORD "$INFRA_ENV")|" \
      -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=$admin_password|" \
      -e "s|^ADMIN_CPF=.*|ADMIN_CPF=$(gen_cpf)|" \
      backend/identity/.env.production.example > "$IDENTITY_ENV"
  assert_filled "$IDENTITY_ENV"
  echo "   backend/identity/.env.production criado (chaves do JWT e APP_KEY diferentes do dev)"
fi

# --- Commerce: only Identity's PUBLIC key (verifies tokens, never issues them — ADR-001)
if [ -f "$COMMERCE_ENV" ]; then
  echo "   backend/commerce/.env.production já existe: mantido"
else
  commerce_db_password="$(env_value COMMERCE_DB_PASSWORD "$INFRA_ENV")"
  identity_public_key="$(env_value JWT_PUBLIC_KEY "$IDENTITY_ENV")"
  [ -n "$commerce_db_password" ] && [ -n "$identity_public_key" ] \
    || { echo "!! COMMERCE_DB_PASSWORD ou JWT_PUBLIC_KEY ausente" >&2; exit 1; }

  sed -e "s|^APP_KEY=.*|APP_KEY=$(app_key)|" \
      -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$commerce_db_password|" \
      -e "s|^IDENTITY_JWT_PUBLIC_KEY=.*|IDENTITY_JWT_PUBLIC_KEY=$identity_public_key|" \
      -e "s|^COMMERCE_SERVICE_TOKEN=.*|COMMERCE_SERVICE_TOKEN=$(hex 32)|" \
      -e "s|^PAYMENTS_SIMULATOR_WEBHOOK_SECRET=.*|PAYMENTS_SIMULATOR_WEBHOOK_SECRET=$(hex 32)|" \
      backend/commerce/.env.production.example > "$COMMERCE_ENV"
  assert_filled "$COMMERCE_ENV"
  echo "   backend/commerce/.env.production criado (só a chave PÚBLICA do Identity)"
fi

echo ">> [4/4] Remote 'production' neste repositório"
if git remote get-url production >/dev/null 2>&1; then
  git remote set-url production "$BARE_REPO"
else
  git remote add production "$BARE_REPO"
fi
git remote -v | grep '^production' | head -1

cat <<EOF

Pronto. Para publicar:
    git push production main

Depois do deploy:
    Identity  http://localhost:8080/api/v1/health
    Commerce  http://localhost:8081/api/v1/health
    Swagger   http://localhost:8080/api/documentation · http://localhost:8081/api/documentation
    Mailpit   http://localhost:8026
    Admin     e-mail e senha em ADMIN_EMAIL / ADMIN_PASSWORD de $IDENTITY_ENV
EOF
