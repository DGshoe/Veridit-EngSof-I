# Veridit

Sistema web para registrar conteúdo de sites como prova (captura de imagem e vídeo, relatório e ZIP).
Arquitetura **Service-Based (SBA)**: Identity, Commerce, Evidence e Notification, cada um com seu banco (ADR-012).

Este é o **monorepo** do projeto: backend e front-end no mesmo repositório, em pastas separadas.

| Pasta | O que é | Dev | Produção local |
|---|---|---|---|
| [`backend/identity/`](backend/identity/README.md) | Identity Service: cadastro, login/JWT, recuperação de senha, usuários (REQ01–04) | :8000 | :8080 |
| [`backend/commerce/`](backend/commerce/README.md) | Commerce Service: pacotes, compra, PIX/Mercado Pago, saldo (REQ05–07) | :8001 | :8081 |
| [`frontend/`](frontend/) | Front-end (Preact + Vite) | :5173 | — |
| `docker/postgres/` | Script que cria os 4 bancos (identity, commerce, evidence, notification) | | |
| `docker-compose.yml` · `docker-compose.prod.yml` | Orquestração do dev e da produção local | | |
| [`deploy/`](deploy/) | Deploy automático com `git push production main` | | |

Evidence e Notification ainda não foram implementados; os bancos deles já são criados vazios.

## Sumário

1. [Pré-requisitos](#1-pré-requisitos)
2. [Ambiente de desenvolvimento](#2-ambiente-de-desenvolvimento)
3. [Produção local e deploy com `git push`](#3-produção-local-e-deploy-com-git-push)
4. [URLs](#4-urls)
5. [Variáveis de ambiente](#5-variáveis-de-ambiente)
6. [Testes](#6-testes)

---

## 1. Pré-requisitos

| Ferramenta | Observação |
|---|---|
| **Docker Desktop** (com Docker Compose v2) | Precisa estar **aberto** antes de qualquer comando |
| **Git** | No Windows, use o **Git Bash** para os scripts de deploy |
| **Node.js 20+** | Só para o front-end |

Não é preciso instalar PHP nem Composer: o backend roda inteiro dentro dos containers.

Portas usadas (todas só em `127.0.0.1`): dev 8000, 8001, 8025, 1025, 5432–5435 e 5173; produção 8080, 8081, 8026 e 5442–5445.

---

## 2. Ambiente de desenvolvimento

### 2.1 Primeira instalação

```bash
git clone <url-do-repositorio> veridit
cd veridit

# 1. Variáveis de ambiente (nenhum desses arquivos vai para o Git)
cp .env.example .env                                   # senhas dos 4 bancos (orquestração)
cp backend/identity/.env.example backend/identity/.env
cp backend/commerce/.env.example backend/commerce/.env
```

**Antes de subir os containers**, edite os três arquivos:

- no `.env` da raiz, troque todas as senhas `troque-*`;
- em `backend/identity/.env`, `DB_PASSWORD` = `IDENTITY_DB_PASSWORD` da raiz; troque a senha do admin e preencha `ADMIN_CPF` com um CPF válido;
- em `backend/commerce/.env`, `DB_PASSWORD` = `COMMERCE_DB_PASSWORD` da raiz.

> As senhas dos bancos só são aplicadas quando o volume é criado (primeira subida).

```bash
# 2. Subir tudo (Identity, Commerce, 4 bancos e Mailpit)
docker compose up -d --build

# 3. Identity: dependências, chaves, tabelas e admin
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:keys
docker compose exec app php artisan migrate --seed

# 4. Commerce: dependências, chave, tabelas e pacotes
docker compose exec commerce-app composer install
docker compose exec commerce-app php artisan key:generate
docker compose exec commerce-app php artisan migrate --seed
```

O Commerce verifica os tokens com a **chave pública** do Identity. Copie a saída de
`docker compose exec app php artisan jwt:keys --show-public` para `IDENTITY_JWT_PUBLIC_KEY` em `backend/commerce/.env`.

```bash
# 5. Front-end
cd frontend
cp .env.example .env.local      # URLs das APIs (padrão: dev 8000/8001)
npm install
npm run dev                     # http://localhost:5173
```

### 2.2 Dia a dia

```bash
docker compose up -d            # subir o backend
docker compose down             # parar (os dados ficam nos volumes)
docker compose ps               # status
cd frontend && npm run dev      # front-end
```

Detalhes de cada serviço: [Identity](backend/identity/README.md) · [Commerce](backend/commerce/README.md).

---

## 3. Produção local e deploy com `git push`

A "produção" roda na própria máquina, com containers, redes e volumes **separados** do dev (projeto `veridit_prod`).
O código fica **dentro** das imagens, sem volume.

```
repositório de trabalho ──git push production main──▶ C:\veridit\producao.git (bare)
                                                          │ hook post-receive
                                                          ▼
                                               C:\veridit\producao-app\  (código + .env.production)
                                                          │ docker compose -p veridit_prod
                                                          ▼
                                          Identity :8080 · Commerce :8081
```

### 3.1 Configuração (uma única vez)

No **Git Bash**, na raiz do repositório:

```bash
bash deploy/setup-production.sh            # padrão: /c/veridit  (= C:\veridit)
```

O script cria o repositório `producao.git` com o hook, os arquivos de ambiente da produção com **segredos
aleatórios** (diferentes do dev) e o remote `production`:

| Arquivo em `producao-app/` | Conteúdo |
|---|---|
| `.env.production` | Senhas dos 4 bancos (orquestração) |
| `backend/identity/.env.production` | Identity: `APP_KEY`, par de chaves do JWT, senha do **seu** banco, admin |
| `backend/commerce/.env.production` | Commerce: só a **chave pública** do JWT, senha do **seu** banco, credenciais de pagamento |

Pode ser executado de novo: reinstala o hook e **não** troca segredos existentes. Também migra uma produção
do layout antigo (um `.env.production` único), guardando um backup.

### 3.2 Publicar

```bash
git push production main
```

| Passo | O que faz |
|---|---|
| 1 | Roda os testes do **Identity e do Commerce** numa cópia temporária do commit. **Se algum falhar, o deploy é cancelado** e a produção segue na versão anterior |
| 2 | Atualiza o código em `producao-app/` (os `.env.production` são preservados) |
| 3 | Build das imagens de produção |
| 4 | Sobe ou recria os containers |
| 5 | Migrations, seed (admin só no 1º deploy; pacotes a cada deploy), Swagger e cache |
| 6 | Verifica o `/health` das duas APIs |

### 3.3 Operar a produção

```bash
cd /c/veridit/producao-app
P="docker compose -p veridit_prod -f docker-compose.prod.yml --env-file .env.production"
$P ps
$P logs -f app commerce-app
$P down                   # parar (os dados ficam nos volumes veridit_prod_*)
```

> Localmente tudo roda em HTTP. Em produção real seria obrigatório HTTPS (ADR-001).

---

## 4. URLs

| O quê | Dev | Produção local |
|---|---|---|
| Identity API / Swagger | http://localhost:8000/api/v1 · `/api/documentation` | http://localhost:8080/api/v1 · `/api/documentation` |
| Commerce API / Swagger | http://localhost:8001/api/v1 · `/api/documentation` | http://localhost:8081/api/v1 · `/api/documentation` |
| Front-end | http://localhost:5173 | — |
| Mailpit | http://localhost:8025 | http://localhost:8026 |

---

## 5. Variáveis de ambiente

Cada projeto tem seus próprios arquivos; os reais **nunca** vão para o Git (só os `*.example`).

| Arquivo (dev) | Modelo | Usado por |
|---|---|---|
| `.env` | [.env.example](.env.example) | Docker Compose: senhas dos 4 bancos |
| `backend/identity/.env` | [modelo](backend/identity/.env.example) | Identity ([variáveis](backend/identity/README.md#6-variáveis-de-ambiente)) |
| `backend/commerce/.env` | [modelo](backend/commerce/.env.example) | Commerce |
| `frontend/.env.local` | [modelo](frontend/.env.example) | Front-end: `VITE_API_URL`, `VITE_COMMERCE_API_URL` |

Pela ADR-012, cada serviço conhece **só a senha do próprio banco**. As senhas de todos os bancos ficam apenas
no `.env` da raiz, que só o Docker Compose lê.

---

## 6. Testes

```bash
docker compose exec app php artisan test             # Identity
docker compose exec commerce-app php artisan test    # Commerce
cd frontend && npm run build                         # front-end (compilação)
```

Os testes do backend usam SQLite em memória e nunca acessam serviços externos reais.
