# Veridit — Identity Service (backend Laravel)

> **Para o Claude Code:** este arquivo é a especificação do projeto. Leia tudo antes de começar, siga as
> **Fases** da seção 13 na ordem e só avance quando os critérios de aceite da fase atual passarem.
> Se algo estiver ambíguo, **pergunte** antes de inventar. Não crie front-end.

---

## 1. Contexto

O **Veridit** é um sistema web para registrar conteúdo de sites como prova (captura de tela/vídeo,
relatório e ZIP). A arquitetura definida pela equipe é **Service-Based Architecture (SBA)** com quatro
serviços de negócio — **Identity, Commerce, Evidence e Notification** — atrás de um Gateway/BFF.

Este repositório implementa **apenas o Identity Service**, o backend de usuários e autenticação.
O front-end será feito por outra equipe e consumirá esta API.

Decisões arquiteturais (ADRs) que este serviço deve respeitar:

| ADR | O que exige deste serviço |
|-----|---------------------------|
| ADR-001 Segurança | Autenticação por **JWT**, controle por perfil (**RBAC**), senha com **hash + salt**, TLS em produção real |
| ADR-003 Fronteiras por domínio | Identity cuida **só** de cadastro, login/logout, recuperação de senha e perfis (REQ 01–04) |
| ADR-012 Dados por serviço | **Um banco por serviço**; Identity só acessa o **seu** banco; dados pessoais (CPF/OAB) protegidos e **mascarados** nas respostas e logs |
| D1 (requisitos) | Primeira versão: perfis **advogado** e **usuário comum**. Empresa fica para depois (deixar o modelo preparado, sem implementar) |

Requisitos funcionais cobertos: **REQ01** cadastrar usuário · **REQ02** recuperar senha · **REQ03** login · **REQ04** logout.

---

## 2. Escopo

**Dentro do escopo**
- API REST versionada (`/api/v1`) com autenticação JWT.
- Cadastro (advogado / comum), login, logout, renovação de token, recuperação e redefinição de senha.
- APIs de consulta para controle de usuários: perfil do usuário logado, listagem e detalhe (admin).
- Documentação **Swagger/OpenAPI** navegável.
- Docker para **desenvolvimento** e para **"produção" local**, com os **4 bancos** da arquitetura.
- Deploy automático: `git push production main` → atualiza a produção local.
- Testes automatizados (feature tests) rodando antes de cada deploy.

**Fora do escopo (não implementar)**
- Front-end, telas, Blade views (exceto o template do e-mail de senha).
- Commerce, Evidence, Notification e Gateway (os bancos deles são **criados vazios**, para as outras equipes).
- Cadastro de empresa, verificação de e-mail por link, OAuth com redes sociais.

---

## 3. Stack

| Item | Escolha |
|------|---------|
| Linguagem | PHP 8.3+ |
| Framework | Laravel na versão estável mais recente (12.x ou superior) |
| Autenticação | JWT com `php-open-source-saver/jwt-auth` (fork mantido do tymon/jwt-auth) |
| Swagger | `darkaonline/l5-swagger` (OpenAPI 3 via atributos PHP) |
| Banco | PostgreSQL 16 (um container por serviço) |
| E-mail (local) | **Mailpit** (captura os e-mails; interface web) |
| Testes | PHPUnit (ou Pest), banco SQLite em memória nos testes |
| Containers | Docker + Docker Compose v2 |

> Antes de instalar, confirme na documentação oficial as versões compatíveis de cada pacote com a
> versão do Laravel usada. Se algum pacote estiver abandonado/incompatível, **pare e pergunte**.

---

## 4. Estrutura de pastas esperada

```
veridit-identity/
├── CLAUDE.md                     ← este arquivo
├── app/
│   ├── Enums/UserType.php        ← advogado | comum | admin (empresa reservado para o futuro)
│   ├── Http/Controllers/Api/V1/  ← AuthController, PasswordController, UserController, HealthController
│   ├── Http/Requests/            ← RegisterRequest, LoginRequest, ForgotPasswordRequest, ResetPasswordRequest...
│   ├── Http/Resources/           ← UserResource (com CPF mascarado)
│   ├── Http/Middleware/EnsureUserType.php  ← RBAC por perfil
│   ├── Models/User.php           ← implementa JWTSubject
│   ├── Notifications/ResetPasswordNotification.php
│   ├── Rules/Cpf.php, Rules/Oab.php
│   └── Support/Mask.php          ← máscara de CPF/e-mail
├── database/migrations/ · database/seeders/AdminSeeder.php
├── routes/api.php
├── tests/Feature/                ← AuthTest, PasswordTest, UserTest
├── docker/
│   ├── php/Dockerfile            ← multi-stage: base, test, production
│   ├── nginx/default.conf
│   └── postgres/init/            ← scripts que criam os bancos vazios dos outros serviços
├── docker-compose.yml            ← DESENVOLVIMENTO
├── docker-compose.prod.yml       ← PRODUÇÃO LOCAL
├── .env.example                  ← dev
├── .env.production.example       ← modelo da produção (o real fica fora do Git)
└── deploy/post-receive           ← hook de deploy automático (seção 11)
```

---

## 5. Bancos de dados (ADR-012)

São criados **4 bancos**, cada um em **seu próprio container** PostgreSQL, com **usuário e senha próprios**.
Só o `identity` é usado por este serviço; os outros ficam **vazios**, prontos para as outras equipes.

| Container | Banco | Usuário | Usado por |
|-----------|-------|---------|-----------|
| `identity-db` | `identity` | `identity_app` | **este serviço** |
| `commerce-db` | `commerce` | `commerce_app` | futuro (Commerce) |
| `evidence-db` | `evidence` | `evidence_app` | futuro (Evidence) |
| `notification-db` | `notification` | `notification_app` | futuro (Notification) |

Regras:
- A aplicação Laravel só tem a conexão `identity`. **Nunca** criar conexão com os outros bancos.
- Cada ambiente (dev e prod) tem seus **próprios** 4 containers e volumes (não compartilham dados).
- Portas publicadas só em `127.0.0.1` (nunca `0.0.0.0`).

### Tabelas do banco `identity`

**users**
| Coluna | Tipo | Regras |
|--------|------|--------|
| id | uuid (PK) | gerado pela aplicação |
| name | string(150) | obrigatório |
| email | string(150) | obrigatório, **único**, minúsculas |
| password | string | hash (bcrypt/argon2 — padrão do Laravel) |
| cpf | char(11) | obrigatório, **único**, só dígitos, validado |
| type | enum: `advogado`, `comum`, `admin` | `empresa` reservado, **não aceitar** no cadastro (D1) |
| oab_number | string(10) nullable | obrigatório se `type = advogado` |
| oab_uf | char(2) nullable | obrigatório se `type = advogado`; UF válida |
| status | enum: `ativo`, `bloqueado` | padrão `ativo` |
| last_login_at | timestamp nullable | atualizado no login |
| timestamps | | |

Índice único composto `(oab_number, oab_uf)` quando não nulos.

Também usar as tabelas padrão do Laravel: `password_reset_tokens`, `cache` (store `database`, usado pela
blacklist do JWT) e `jobs`, se for usada fila.

**Seeder:** criar um usuário `admin` a partir das variáveis `ADMIN_EMAIL` e `ADMIN_PASSWORD` do `.env`
(nunca senha fixa no código).

---

## 6. Regras de negócio e validação

1. **Cadastro (REQ01)**
   - `type` aceita só `advogado` ou `comum`. `admin` só via seeder; `empresa` → erro 422 "Cadastro de empresas ainda não disponível".
   - CPF: aceitar com ou sem máscara, salvar só dígitos, validar **dígitos verificadores**, rejeitar sequências repetidas (`111.111.111-11`).
   - Advogado: `oab_number` (só dígitos, 1–8) e `oab_uf` (UF brasileira válida) obrigatórios. Usuário comum: ignorar campos OAB.
   - Senha: mínimo 8 caracteres, com letras e números; campo `password_confirmation`.
   - E-mail e CPF únicos → 422 com mensagem clara.
   - Resposta: 201 com o usuário (sem senha, CPF mascarado). **Não** faz login automático.
2. **Login (REQ03)**
   - Credenciais erradas → 401 com mensagem genérica ("E-mail ou senha inválidos") — não revelar qual errou.
   - Usuário `bloqueado` → 403.
   - **Rate limit:** 5 tentativas por minuto por IP+e-mail → 429.
   - Retorna `access_token` (JWT, 15 min), `token_type: bearer`, `expires_in` e os dados do usuário.
3. **Renovação:** `POST /auth/refresh` com o token atual (dentro da janela de refresh de 7 dias) devolve um token novo; o antigo vai para a blacklist.
4. **Logout (REQ04):** invalida o token (blacklist) → 204. Usar o token de novo → 401.
5. **Recuperar senha (REQ02)**
   - `forgot-password`: sempre responde **200 com a mesma mensagem**, exista ou não o e-mail (não revelar cadastro). Rate limit: 3 por minuto.
   - Envia e-mail com link `FRONTEND_URL/redefinir-senha?token=...&email=...` (token do password broker do Laravel, expira em 60 min).
   - `reset-password`: valida token + e-mail + nova senha; invalida o token e **todos os JWT anteriores** do usuário (ex.: guardar `password_changed_at` e rejeitar tokens emitidos antes).
6. **Privacidade (ADR-012)**
   - Respostas da API mostram CPF mascarado: `***.456.789-**`.
   - Logs nunca contêm senha, token ou CPF completo.

---

## 7. Endpoints

Base: `/api/v1`. Respostas sempre em JSON.

| Método | Rota | Auth | Perfil | Descrição |
|--------|------|------|--------|-----------|
| GET | `/health` | — | — | Status da API e do banco (usado no deploy) |
| POST | `/auth/register` | — | — | Cadastrar advogado ou usuário comum (REQ01) |
| POST | `/auth/login` | — | — | Login, retorna JWT (REQ03) |
| POST | `/auth/logout` | JWT | qualquer | Invalida o token (REQ04) |
| POST | `/auth/refresh` | JWT | qualquer | Renova o token |
| POST | `/auth/forgot-password` | — | — | Envia e-mail de recuperação (REQ02) |
| POST | `/auth/reset-password` | — | — | Define nova senha com o token (REQ02) |
| GET | `/me` | JWT | qualquer | **Consulta 1:** dados do usuário logado |
| GET | `/users` | JWT | admin | **Consulta 2:** lista usuários (paginada, filtros) |
| GET | `/users/{id}` | JWT | admin ou o próprio usuário | Detalhe de um usuário |

**Filtros de `GET /users`:** `type`, `status`, `search` (nome ou e-mail), `per_page` (máx. 50), `page`.
Ordenação padrão: `created_at desc`.

### Formato padrão

Sucesso:
```json
{ "data": { ... }, "message": "Opcional" }
```
Lista paginada:
```json
{ "data": [ ... ], "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 } }
```
Erro de validação (422):
```json
{ "message": "Os dados enviados são inválidos.", "errors": { "cpf": ["CPF inválido."] } }
```
Outros erros: `{ "message": "..." }` com 401, 403, 404, 429 ou 500 (sem stack trace em produção).

### Exemplos

`POST /api/v1/auth/register`
```json
{
  "name": "Maria Souza",
  "email": "maria@exemplo.com",
  "password": "Senha1234",
  "password_confirmation": "Senha1234",
  "cpf": "529.982.247-25",
  "type": "advogado",
  "oab_number": "123456",
  "oab_uf": "BA"
}
```

`POST /api/v1/auth/login` → 200
```json
{
  "data": {
    "access_token": "eyJ0eXAiOiJKV1Qi...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": { "id": "9b1c...", "name": "Maria Souza", "email": "maria@exemplo.com",
              "cpf": "***.982.247-**", "type": "advogado", "oab": "123456/BA", "status": "ativo" }
  }
}
```

---

## 8. Segurança

- **JWT** no header `Authorization: Bearer <token>`; access token de 15 min, refresh de 7 dias, **blacklist ativada**.
- `JWT_SECRET` gerado por `php artisan jwt:secret`, **diferente** em dev e prod, nunca commitado.
- **RBAC:** middleware `EnsureUserType` (ex.: `->middleware('type:admin')`); **Policies** para "admin ou o próprio usuário" em `/users/{id}`.
- **CORS:** liberar só as origens em `CORS_ALLOWED_ORIGINS` (o front da outra equipe).
- `APP_DEBUG=false` em produção; sem stack trace nas respostas.
- Cabeçalhos básicos de segurança no Nginx (`X-Content-Type-Options`, `X-Frame-Options`).
- Em produção real usaria HTTPS (ADR-001); localmente roda em HTTP, e isso deve estar registrado no README.

---

## 9. Swagger / OpenAPI

- Usar `l5-swagger` com **atributos PHP** (`#[OA\...]`) nos controllers.
- Página em **`/api/documentation`**, nos dois ambientes.
- Definir o esquema de segurança **bearerAuth (JWT)**, para o botão "Authorize" funcionar.
- Documentar **todos** os endpoints da seção 7, com corpo de requisição, exemplos e respostas 200/201/204/401/403/404/422/429.
- Schemas reutilizáveis: `User`, `TokenResponse`, `ValidationError`, `Error`.
- Em dev: gerar sempre (`L5_SWAGGER_GENERATE_ALWAYS=true`). Em prod: gerar **no build/deploy** (`php artisan l5-swagger:generate`) e deixar `false`.

---

## 10. Docker

### 10.1 Imagem da aplicação — `docker/php/Dockerfile` (multi-stage)

- **base:** PHP 8.3-FPM com extensões `pdo_pgsql`, `pdo_sqlite`, `intl`, `zip`, `opcache`; Composer.
- **test:** `composer install` **com** dependências de dev; usado só para rodar os testes no deploy.
- **production:** `composer install --no-dev --optimize-autoloader`; código **copiado** para a imagem (sem volume); opcache ligado; roda como usuário não-root.

Servidor web: **Nginx** em container próprio (`docker/nginx/default.conf`), repassando para o PHP-FPM.
(Alternativa aceitável: uma imagem que já junte Nginx e FPM; se escolher outra, justifique no README.)

### 10.2 Desenvolvimento — `docker-compose.yml`

Projeto Compose: `veridit_dev`.

| Serviço | Detalhe |
|---------|---------|
| `app` | stage `base`, código montado como volume (alterou, refletiu) |
| `web` | Nginx → `http://localhost:8000` |
| `identity-db` | Postgres 16, porta `127.0.0.1:5432` |
| `commerce-db` | Postgres 16, porta `127.0.0.1:5433` (vazio) |
| `evidence-db` | Postgres 16, porta `127.0.0.1:5434` (vazio) |
| `notification-db` | Postgres 16, porta `127.0.0.1:5435` (vazio) |
| `mailpit` | SMTP `1025`; interface em `http://localhost:8025` |

Cada banco com volume nomeado próprio e `healthcheck` (`pg_isready`); `app` depende de `identity-db` saudável.

### 10.3 Produção local — `docker-compose.prod.yml`

Projeto Compose: `veridit_prod` (containers, redes e volumes **separados** do dev).

| Serviço | Detalhe |
|---------|---------|
| `app` | stage `production`, **sem** volume de código, `restart: unless-stopped` |
| `web` | Nginx → `http://localhost:8080` |
| 4 bancos | mesmos nomes, **volumes próprios de prod**, portas `127.0.0.1:5442–5445` (só para inspeção) |
| `mailpit` | interface em `http://localhost:8026` (simula o provedor de e-mail) |

Variáveis vêm de `.env.production`, que **não vai para o Git** (fica na pasta de deploy, seção 11).

---

## 11. Deploy automático com `git push` (produção local)

Ideia: existe um **repositório Git "de produção"** na própria máquina. Quando você faz
`git push production main`, um **hook `post-receive`** copia o código para a pasta da produção,
roda os testes, reconstrói os containers, aplica as migrations e verifica o `/health`.
Se os testes falharem, **o deploy é cancelado** e a produção continua na versão anterior.

```
VS Code / repositório de trabalho ──git push production main──▶ producao.git (bare)
                                                                   │ hook post-receive
                                                                   ▼
                                                        producao-app/  (código + .env.production)
                                                                   │ docker compose -p veridit_prod
                                                                   ▼
                                                        http://localhost:8080
```

### 11.1 Configuração única (o Claude Code deve gerar um script `deploy/setup-production.sh`)

```bash
# Exemplo de caminhos (Windows com Git Bash: C:\veridit → /c/veridit)
PROD_ROOT=/c/veridit
git init --bare "$PROD_ROOT/producao.git"
mkdir -p "$PROD_ROOT/producao-app"
cp deploy/post-receive "$PROD_ROOT/producao.git/hooks/post-receive"
chmod +x "$PROD_ROOT/producao.git/hooks/post-receive"
cp .env.production.example "$PROD_ROOT/producao-app/.env.production"   # depois editar senhas/segredos
git remote add production "$PROD_ROOT/producao.git"
```

### 11.2 Hook `deploy/post-receive` (base; o Claude Code deve completar e testar)

```bash
#!/usr/bin/env bash
set -euo pipefail

DEPLOY_DIR="/c/veridit/producao-app"     # ajustar ao caminho da máquina
BRANCH="main"
COMPOSE="docker compose -p veridit_prod -f docker-compose.prod.yml --env-file .env.production"

while read -r oldrev newrev ref; do
  [ "$ref" = "refs/heads/$BRANCH" ] || { echo "Ignorando $ref"; continue; }

  echo ">> [1/6] Atualizando código ($newrev)"
  git --work-tree="$DEPLOY_DIR" checkout -f "$BRANCH"
  cd "$DEPLOY_DIR"

  echo ">> [2/6] Rodando testes"
  docker build -f docker/php/Dockerfile --target test -t veridit-identity:test .
  docker run --rm veridit-identity:test php artisan test || { echo "!! Testes falharam. Deploy cancelado."; exit 1; }

  echo ">> [3/6] Build da imagem de produção"
  $COMPOSE build app

  echo ">> [4/6] Subindo containers"
  $COMPOSE up -d

  echo ">> [5/6] Migrations, Swagger e cache"
  $COMPOSE exec -T app php artisan migrate --force
  $COMPOSE exec -T app php artisan l5-swagger:generate
  $COMPOSE exec -T app php artisan optimize

  echo ">> [6/6] Verificando saúde"
  for i in $(seq 1 10); do
    curl -fsS http://localhost:8080/api/v1/health && { echo; echo ">> Deploy OK"; exit 0; }
    sleep 3
  done
  echo "!! /health não respondeu"; exit 1
done
```

Observações:
- `checkout -f` não apaga arquivos fora do Git, então o `.env.production` da pasta de deploy é preservado.
- O primeiro deploy também deve rodar `php artisan db:seed --class=AdminSeeder --force` (o script de setup pode fazer isso uma vez).
- **Alternativa**, se a equipe usar GitHub: um *self-hosted runner* do GitHub Actions na própria máquina, executando os mesmos passos a cada push na `main`. Só implementar se for pedido.

---

## 12. Testes (obrigatórios antes de cada deploy)

`tests/Feature`, com banco SQLite em memória (`phpunit.xml`) e `Notification::fake()` para e-mails:

- Cadastro de advogado válido (201) e de usuário comum (201).
- Cadastro de advogado sem OAB (422); CPF inválido (422); CPF/e-mail duplicado (422); tipo `empresa` (422).
- Login correto (200 + token); senha errada (401); usuário bloqueado (403); 6ª tentativa em 1 minuto (429).
- `/me` com token (200, CPF mascarado) e sem token (401).
- Logout (204) e reutilização do token após logout (401).
- Refresh devolve token novo; o antigo deixa de valer.
- Forgot-password responde 200 para e-mail existente **e** inexistente; e-mail enviado só no primeiro caso.
- Reset-password com token válido troca a senha; tokens JWT antigos deixam de valer; token inválido (422).
- `/users`: admin lista (200, paginado, filtros funcionam); usuário comum (403).
- `/users/{id}`: admin vê qualquer um; usuário vê só a si mesmo (403 para outro).
- `/health` responde 200.

---

## 13. Fases de implementação (siga na ordem)

| Fase | Entrega | Critério de aceite |
|------|---------|--------------------|
| 1 | Projeto Laravel + Docker de **dev** com os 4 bancos e Mailpit | `docker compose up -d` sobe tudo; `http://localhost:8000/api/v1/health` = 200; os 4 bancos aceitam conexão |
| 2 | Migrations, model `User`, enums, seeder admin | `php artisan migrate --seed` ok; admin criado a partir do `.env` |
| 3 | Cadastro, login, logout, refresh (JWT + RBAC) | Testes de auth passando |
| 4 | Recuperação/redefinição de senha | E-mail aparece no Mailpit; reset funciona; testes passando |
| 5 | `/me`, `/users`, `/users/{id}` | Testes de consulta e permissão passando |
| 6 | Swagger completo | `/api/documentation` lista todos os endpoints; "Authorize" com JWT funciona |
| 7 | Docker de **produção** + hook de deploy + script de setup | `git push production main` publica em `http://localhost:8080`; teste quebrado de propósito cancela o deploy |
| 8 | README | Passo a passo de instalação, comandos, URLs e variáveis de ambiente |

Ao final de cada fase: rodar `php artisan test`, mostrar o resultado e **fazer commit** com mensagem
descritiva (ex.: `feat(auth): login e logout com JWT`).

---

## 14. URLs e comandos úteis

| O quê | Dev | Produção local |
|-------|-----|----------------|
| API | http://localhost:8000/api/v1 | http://localhost:8080/api/v1 |
| Swagger | http://localhost:8000/api/documentation | http://localhost:8080/api/documentation |
| Mailpit | http://localhost:8025 | http://localhost:8026 |
| Banco identity | 127.0.0.1:5432 | 127.0.0.1:5442 |

```bash
docker compose up -d                                   # subir dev
docker compose exec app php artisan migrate --seed     # migrations + admin
docker compose exec app php artisan test               # testes
git push production main                               # deploy na produção local
docker compose -p veridit_prod -f docker-compose.prod.yml logs -f app   # logs da produção
```

---

## 15. Regras para o Claude Code

1. Não criar front-end, telas ou páginas além do Swagger e do template de e-mail.
2. Não conectar este serviço aos bancos commerce, evidence ou notification.
3. Não commitar `.env`, `.env.production`, segredos ou senhas.
4. Não usar pacotes abandonados; confirmar a compatibilidade com a versão do Laravel antes de instalar.
5. Mensagens de erro e da API em **português**; código (classes, métodos, variáveis) em **inglês**.
6. Manter controllers finos: validação em Form Requests, regras em Services/Actions, saída em Resources.
7. Se um comando falhar por ambiente (Docker parado, porta ocupada), explicar o problema em vez de contorná-lo.
8. Em dúvida sobre regra de negócio, perguntar — não inventar requisito.
