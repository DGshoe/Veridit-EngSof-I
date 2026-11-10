#!/usr/bin/env bash
#
# Veridit Identity — configuração ÚNICA da produção local (CLAUDE.md §11.1)
#
# Uso (no Git Bash, a partir da raiz do repositório):
#   bash deploy/setup-production.sh [PROD_ROOT]      # padrão: /c/veridit  (= C:\veridit)
#
# Cria:
#   PROD_ROOT/producao.git      repositório bare + hook post-receive
#   PROD_ROOT/producao-app/     pasta da produção, com .env.production (segredos aleatórios)
#   remote "production"         neste repositório
#
# Idempotente: pode rodar de novo (reinstala o hook; NÃO sobrescreve o .env.production).
set -euo pipefail

PROD_ROOT="${1:-/c/veridit}"
BARE_REPO="$PROD_ROOT/producao.git"
DEPLOY_DIR="$PROD_ROOT/producao-app"
ENV_FILE="$DEPLOY_DIR/.env.production"

REPO_ROOT="$(git rev-parse --show-toplevel)"
cd "$REPO_ROOT"

for tool in git docker openssl; do
  command -v "$tool" >/dev/null || { echo "!! '$tool' não encontrado no PATH." >&2; exit 1; }
done
docker info >/dev/null 2>&1 || { echo "!! Docker não está rodando. Abra o Docker Desktop e tente de novo." >&2; exit 1; }

# Random secrets ---------------------------------------------------------------
hex()     { openssl rand -hex "$1"; }
app_key() { echo "base64:$(openssl rand -base64 32)"; }

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

echo ">> [3/4] Pasta da produção e .env.production em $DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"
if [ -f "$ENV_FILE" ]; then
  echo "   .env.production já existe: mantido (segredos não foram trocados)"
else
  admin_password="Adm$(hex 10)7"
  sed -e "s|^APP_KEY=.*|APP_KEY=$(app_key)|" \
      -e "s|^JWT_SECRET=.*|JWT_SECRET=$(hex 32)|" \
      -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(hex 24)|" \
      -e "s|^POSTGRES_ADMIN_PASSWORD=.*|POSTGRES_ADMIN_PASSWORD=$(hex 24)|" \
      -e "s|^COMMERCE_DB_PASSWORD=.*|COMMERCE_DB_PASSWORD=$(hex 24)|" \
      -e "s|^EVIDENCE_DB_PASSWORD=.*|EVIDENCE_DB_PASSWORD=$(hex 24)|" \
      -e "s|^NOTIFICATION_DB_PASSWORD=.*|NOTIFICATION_DB_PASSWORD=$(hex 24)|" \
      -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=$admin_password|" \
      -e "s|^ADMIN_CPF=.*|ADMIN_CPF=$(gen_cpf)|" \
      .env.production.example > "$ENV_FILE"
  chmod 600 "$ENV_FILE" 2>/dev/null || true
  grep -q 'gerar-' "$ENV_FILE" && { echo "!! Sobrou valor 'gerar-*' em $ENV_FILE" >&2; exit 1; }
  echo "   criado com segredos aleatórios (JWT_SECRET e APP_KEY diferentes do dev)"
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

Depois do primeiro deploy:
    API       http://localhost:8080/api/v1/health
    Swagger   http://localhost:8080/api/documentation
    Mailpit   http://localhost:8026
    Admin     e-mail e senha em ADMIN_EMAIL / ADMIN_PASSWORD de $ENV_FILE
EOF
