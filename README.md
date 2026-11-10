# Veridit — Identity Service

Backend de **usuários e autenticação** do Veridit. Faz cadastro, login/logout com JWT, recuperação de senha e consultas de usuários (REQ01–REQ04).

É um dos quatro serviços da arquitetura SBA (Identity, Commerce, Evidence, Notification). Este repositório implementa **somente o Identity**. Os bancos dos outros três serviços são criados vazios, para as outras equipes.

- **Stack:** PHP 8.3 · Laravel 13 · PostgreSQL 16 · JWT (`php-open-source-saver/jwt-auth`) · Swagger (`l5-swagger`) · Docker
- **Especificação completa:** [CLAUDE.md](CLAUDE.md)

---

## Sumário

1. [Pré-requisitos](#1-pré-requisitos)
2. [Ambiente de desenvolvimento](#2-ambiente-de-desenvolvimento)
3. [Produção local e deploy com `git push`](#3-produção-local-e-deploy-com-git-push)
4. [URLs](#4-urls)
5. [Endpoints da API](#5-endpoints-da-api)
6. [Variáveis de ambiente](#6-variáveis-de-ambiente)
7. [Comandos úteis](#7-comandos-úteis)
8. [Segurança e decisões de projeto](#8-segurança-e-decisões-de-projeto)
9. [Solução de problemas](#9-solução-de-problemas)

---

## 1. Pré-requisitos

| Ferramenta | Observação |
|---|---|
| **Docker Desktop** (com Docker Compose v2) | Precisa estar **aberto** antes de qualquer comando |
| **Git** | No Windows, use o **Git Bash** para os scripts de deploy |

**Não é preciso instalar PHP nem Composer na máquina.** Tudo roda dentro dos containers, e um PHP local mais antigo (ex.: XAMPP 8.1) não interfere.

As portas abaixo precisam estar livres. Todas são publicadas só em `127.0.0.1`:

| Ambiente | Portas |
|---|---|
| Dev | 8000 (API), 8025 (Mailpit), 1025 (SMTP), 5432–5435 (bancos) |
| Produção local | 8080 (API), 8026 (Mailpit), 5442–5445 (bancos) |

---

## 2. Ambiente de desenvolvimento

No dev, o código é montado como volume: alterou o arquivo, a mudança já vale.

### 2.1 Primeira instalação

```bash
git clone <url-do-repositorio> veridit-identity
cd veridit-identity

# 1. Variáveis de ambiente
cp .env.example .env
```

**Antes de subir os containers**, edite o `.env`:

- Troque todos os valores `troque-*` (senhas dos bancos e do admin) por senhas suas.
- Preencha `ADMIN_CPF` com um CPF **válido**, com ou sem máscara.

> As senhas dos bancos só são aplicadas quando o volume é criado (primeira subida). Para trocá-las depois, veja a [seção 9](#9-solução-de-problemas).

```bash
# 2. Subir os containers (app, web, queue, 4 bancos e Mailpit)
docker compose up -d --build

# 3. Dependências PHP (a pasta vendor/ não vai para o Git)
docker compose exec app composer install

# 4. Chaves da aplicação e do JWT (gravadas no .env)
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:keys      # par RSA do JWT (RS256)

# 5. Tabelas + usuário admin (a partir de ADMIN_* do .env)
docker compose exec app php artisan migrate --seed

# 6. Conferir
curl http://localhost:8000/api/v1/health
docker compose exec app php artisan test
```

Pronto. Abra o Swagger em **http://localhost:8000/api/documentation**.

### 2.2 Dia a dia

```bash
docker compose up -d                                  # subir
docker compose down                                   # parar (os dados ficam nos volumes)
docker compose exec app php artisan test              # rodar os testes
docker compose exec app php artisan migrate           # aplicar migrations novas
docker compose logs -f app queue                      # logs
docker compose exec app php artisan tinker            # console
```

### 2.3 Containers do dev (projeto `veridit_dev`)

| Serviço | Função |
|---|---|
| `app` | PHP 8.3-FPM com o código montado como volume |
| `web` | Nginx → http://localhost:8000 |
| `queue` | Worker da fila (envia os e-mails de recuperação de senha) |
| `identity-db` | PostgreSQL do Identity: banco `identity`, usuário `identity_app`, porta 5432 |
| `commerce-db` / `evidence-db` / `notification-db` | Bancos **vazios** das outras equipes (portas 5433–5435) |
| `mailpit` | Captura os e-mails enviados → http://localhost:8025 |

Cada banco roda no seu próprio container, com volume e usuário próprios. O usuário de cada banco não é superusuário. A aplicação conecta **somente** ao `identity` (ADR-012).

---

## 3. Produção local e deploy com `git push`

A "produção" roda na própria máquina, em containers, redes e volumes **separados** do dev (projeto `veridit_prod`). O código fica **dentro** da imagem, sem volume.

```
repositório de trabalho ──git push production main──▶ C:\veridit\producao.git (bare)
                                                          │ hook post-receive
                                                          ▼
                                               C:\veridit\producao-app\ (código + .env.production)
                                                          │ docker compose -p veridit_prod
                                                          ▼
                                                  http://localhost:8080
```

### 3.1 Configuração (uma única vez)

No **Git Bash**, na raiz do repositório:

```bash
bash deploy/setup-production.sh            # padrão: /c/veridit  (= C:\veridit)
# ou escolha outra pasta:
bash deploy/setup-production.sh /d/veridit
```

O script:

1. cria o repositório bare `producao.git` e instala o hook `post-receive`;
2. cria `producao-app/.env.production` a partir de [.env.production.example](.env.production.example), com **segredos aleatórios** (`APP_KEY`, par de chaves RSA do JWT e senhas, todos diferentes do dev) e um CPF válido para o admin;
3. adiciona o remote `production` a este repositório.

O script pode ser executado de novo sem problema: ele reinstala o hook e **não** sobrescreve o `.env.production`.

### 3.2 Publicar

```bash
git push production main
```

O hook executa, nesta ordem:

| Passo | O que faz |
|---|---|
| 1 | Roda **todos os testes** numa cópia temporária do commit (imagem `test`). **Se algum falhar, o deploy é cancelado** e a produção segue na versão anterior, sem nenhuma alteração |
| 2 | Atualiza o código em `producao-app/` (o `.env.production` é preservado) |
| 3 | Faz o build das imagens de produção |
| 4 | Sobe ou recria os containers |
| 5 | Roda as migrations, o seed do admin (**só no 1º deploy**), a geração do Swagger e o cache (`optimize`); reinicia o worker |
| 6 | Verifica `http://localhost:8080/api/v1/health` |

A saída aparece no próprio terminal do `git push`, com linhas `remote: >> [n/6] ...`.

> O hook `post-receive` roda **depois** que o Git aceita o push. Por isso, quando os testes falham, o branch do repositório `production` aponta para o commit rejeitado, mas os containers e a pasta `producao-app/` continuam na versão anterior. Corrija o problema e faça um novo push.

### 3.3 Operar a produção

```bash
cd /c/veridit/producao-app
P="docker compose -p veridit_prod -f docker-compose.prod.yml --env-file .env.production"

$P ps                     # status
$P logs -f app            # logs da aplicação (stderr do container)
$P exec app php artisan migrate:status
$P down                   # parar (os dados ficam nos volumes veridit_prod_*)
```

A senha do admin de produção está em `ADMIN_PASSWORD` dentro de `C:\veridit\producao-app\.env.production`.

---

## 4. URLs

| O quê | Dev | Produção local |
|---|---|---|
| API | http://localhost:8000/api/v1 | http://localhost:8080/api/v1 |
| Swagger | http://localhost:8000/api/documentation | http://localhost:8080/api/documentation |
| Health | http://localhost:8000/api/v1/health | http://localhost:8080/api/v1/health |
| Mailpit | http://localhost:8025 | http://localhost:8026 |
| Banco `identity` | 127.0.0.1:5432 | 127.0.0.1:5442 |
| Bancos commerce / evidence / notification | 127.0.0.1:5433–5435 | 127.0.0.1:5443–5445 |

---

## 5. Endpoints da API

Base: `/api/v1`. Todas as respostas são JSON e as mensagens estão em português. A documentação interativa completa, com exemplos, fica no **Swagger**.

| Método | Rota | Auth | Perfil | Descrição |
|---|---|---|---|---|
| GET | `/health` | — | — | Status da API e do banco |
| POST | `/auth/register` | — | — | Cadastrar advogado ou usuário comum (REQ01). **Não** faz login |
| POST | `/auth/login` | — | — | Login, retorna o JWT (REQ03). Limite: 5/min por IP + e-mail |
| POST | `/auth/logout` | JWT | qualquer | Invalida o token (REQ04) → 204 |
| POST | `/auth/refresh` | JWT | qualquer | Novo token; aceita token expirado em até 7 dias após o login |
| POST | `/auth/forgot-password` | — | — | Envia o link de redefinição (REQ02). Sempre 200. Limite: 3/min |
| POST | `/auth/reset-password` | — | — | Define a nova senha com o token do e-mail (REQ02) |
| GET | `/me` | JWT | qualquer | Dados do usuário logado |
| GET | `/users` | JWT | admin | Lista paginada. Filtros: `type`, `status`, `search`, `per_page` (≤ 50), `page` |
| GET | `/users/{id}` | JWT | admin ou o próprio usuário | Detalhe de um usuário |

### Usando o Swagger com JWT

1. Abra `POST /api/v1/auth/login` → **Try it out** → informe e-mail e senha → **Execute**.
2. Copie o `access_token` da resposta.
3. Clique em **Authorize** (cadeado no topo), cole **só o token**, sem `Bearer`, e confirme.
4. As rotas com cadeado passam a enviar `Authorization: Bearer <token>`. A autorização fica salva até você recarregar com "Logout".

### Formato das respostas

```jsonc
// sucesso
{ "data": { ... }, "message": "Opcional" }
// lista paginada
{ "data": [ ... ], "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 } }
// erro de validação (422)
{ "message": "Os dados enviados são inválidos.", "errors": { "cpf": ["CPF inválido."] } }
// outros erros (401, 403, 404, 429, 500)
{ "message": "Não autenticado." }
```

O CPF **sempre** sai mascarado (`***.982.247-**`), e a senha nunca é retornada.

### Exemplo rápido com curl

```bash
API=http://localhost:8000/api/v1

curl -X POST $API/auth/register -H "Content-Type: application/json" -d '{
  "name": "Maria Souza", "email": "maria@exemplo.com",
  "password": "Senha1234", "password_confirmation": "Senha1234",
  "cpf": "529.982.247-25", "type": "advogado", "oab_number": "123456", "oab_uf": "BA"
}'

TOKEN=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" \
  -d '{"email":"maria@exemplo.com","password":"Senha1234"}' | sed -E 's/.*"access_token":"([^"]+)".*/\1/')

curl $API/me -H "Authorization: Bearer $TOKEN"
```

---

## 6. Variáveis de ambiente

Os modelos são [.env.example](.env.example) (dev) e [.env.production.example](.env.production.example) (produção). Os arquivos reais (`.env` e `.env.production`) **nunca** vão para o Git.

### Aplicação

| Variável | Dev | Produção | Descrição |
|---|---|---|---|
| `APP_ENV` | `local` | `production` | Ambiente |
| `APP_DEBUG` | `true` | **`false`** | Em produção, nunca mostra stack trace |
| `APP_KEY` | `key:generate` | gerado pelo setup | Chave de criptografia do Laravel |
| `APP_URL` | `http://localhost:8000` | `http://localhost:8080` | URL base |
| `APP_LOCALE` | `pt_BR` | `pt_BR` | Idioma das mensagens |
| `LOG_CHANNEL` / `LOG_LEVEL` | `stack` / `debug` | `stderr` / `info` | Em produção, os logs saem em `docker compose logs` |

### Banco de dados

| Variável | Descrição |
|---|---|
| `DB_CONNECTION` | Sempre `identity`, a única conexão da aplicação |
| `DB_DRIVER` | `pgsql` (os testes trocam para `sqlite` em memória pelo `phpunit.xml`) |
| `DB_HOST` / `DB_PORT` | `identity-db` / `5432` (rede interna do Docker) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Banco `identity`, usuário `identity_app` e a senha dele |
| `POSTGRES_ADMIN_PASSWORD` | Senha do superusuário `postgres` dos 4 containers (só administração) |
| `COMMERCE_DB_PASSWORD` / `EVIDENCE_DB_PASSWORD` / `NOTIFICATION_DB_PASSWORD` | Senhas dos bancos vazios das outras equipes. Usadas só pelo Docker Compose |

### Autenticação e segurança

| Variável | Padrão | Descrição |
|---|---|---|
| `JWT_ALGO` | `RS256` | Assinatura assimétrica (chave privada assina, pública verifica) |
| `JWT_PRIVATE_KEY` | — | Chave privada RSA (`base64:<PEM>`), gerada por `php artisan jwt:keys`. **Só este serviço** a tem; **diferente** em dev e produção |
| `JWT_PUBLIC_KEY` | — | Chave pública correspondente. É a única que pode ser entregue aos outros serviços (`php artisan jwt:keys --show-public`) |
| `JWT_TTL` | `15` | Validade do access token (minutos) |
| `JWT_REFRESH_TTL` | `10080` | Janela de refresh (minutos = 7 dias) |
| `JWT_BLACKLIST_ENABLED` | `true` | Tokens de logout/refresh vão para a blacklist |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:3000,...` | Origens do front-end, separadas por vírgula |
| `FRONTEND_URL` | `http://localhost:3000` | Base do link do e-mail: `FRONTEND_URL/redefinir-senha?token=...&email=...` |
| `BCRYPT_ROUNDS` | `12` | Custo do hash das senhas |

### Administrador inicial (`AdminSeeder`)

| Variável | Descrição |
|---|---|
| `ADMIN_NAME` | Nome (padrão "Administrador") |
| `ADMIN_EMAIL` | E-mail de login |
| `ADMIN_PASSWORD` | Mínimo 8 caracteres, com letras e números |
| `ADMIN_CPF` | CPF válido (obrigatório e único na tabela `users`) |

O seeder pode ser executado de novo sem duplicar o admin: ele atualiza o usuário com o mesmo e-mail. Se alguma variável estiver ausente ou inválida, ele para com uma mensagem clara.

### Fila, cache, e-mail e Swagger

| Variável | Valor | Descrição |
|---|---|---|
| `QUEUE_CONNECTION` | `database` | Fila dos e-mails de recuperação (worker `queue`) |
| `CACHE_STORE` | `database` | Cache, rate limit e blacklist do JWT |
| `SESSION_DRIVER` | `array` | API stateless: sem sessão no servidor |
| `MAIL_HOST` / `MAIL_PORT` | `mailpit` / `1025` | SMTP do Mailpit |
| `MAIL_FROM_ADDRESS` | `nao-responda@veridit.local` | Remetente |
| `L5_SWAGGER_GENERATE_ALWAYS` | dev `true` / prod `false` | No dev, regenera a cada acesso; na produção, é gerado no build/deploy |

---

## 7. Comandos úteis

| Tarefa | Comando |
|---|---|
| Rodar testes (dev) | `docker compose exec app php artisan test` |
| Rodar um teste | `docker compose exec app php artisan test --filter=AuthTest` |
| Testes como no deploy | `docker build -f docker/php/Dockerfile --target test -t veridit-identity:test . && docker run --rm veridit-identity:test` |
| Recriar o banco de dev | `docker compose exec app php artisan migrate:fresh --seed` |
| Recriar o admin | `docker compose exec app php artisan db:seed --class=AdminSeeder` |
| Regenerar o Swagger | `docker compose exec app php artisan l5-swagger:generate` |
| Listar rotas | `docker compose exec app php artisan route:list --path=api` |
| Acessar o banco (dev) | `docker compose exec identity-db psql -U postgres -d identity` |
| Instalar um pacote | `docker compose exec app composer require vendor/pacote` |

---

## 8. Segurança e decisões de projeto

- **JWT (ADR-001):** access token de 15 min e refresh dentro de 7 dias após o login. Logout e refresh mandam o token antigo para a blacklist.
- **Assinatura RS256:** o Identity assina os tokens com a chave **privada**, que só ele tem. Commerce, Evidence, Notification e Gateway recebem apenas a chave **pública** (`php artisan jwt:keys --show-public`) e com ela conseguem **verificar** um token, mas não **emitir** um. Os testes garantem que tokens assinados com outra chave, com `alg: none` ou com HS256 usando a chave pública como segredo (ataque de confusão de algoritmo) são recusados com 401.
- **Limite da verificação local:** os outros serviços validam assinatura e expiração, mas não enxergam a blacklist nem a claim `pwv` (que dependem do banco e da `APP_KEY` do Identity). Assim, um token de quem fez logout ou trocou a senha ainda é aceito por eles até expirar, no máximo 15 minutos. Se um serviço precisar de revogação imediata, deve consultar o Identity (`GET /api/v1/me`).
- **Trocar as chaves** (`php artisan jwt:keys --force` no dev; no `.env.production` na produção) invalida todos os tokens emitidos; os outros serviços precisam receber a nova chave pública.
- **Troca de senha revoga todos os tokens:** cada JWT carrega a claim `pwv`, uma impressão digital HMAC da senha atual. Depois de um reset, os tokens anteriores, inclusive os ainda dentro da janela de refresh, passam a receber 401. A coluna `password_changed_at` guarda a data da troca para auditoria.
- **Usuário bloqueado** recebe 403 no login e também **imediatamente** em qualquer rota autenticada, mesmo com um token ainda válido.
- **RBAC:** o middleware `type:admin` controla o acesso por perfil, e a `UserPolicy` cuida do caso "admin ou o próprio usuário" em `/users/{id}`.
- **Não revelar quem tem cadastro:** o login devolve sempre "E-mail ou senha inválidos." e leva tempo parecido exista ou não o e-mail. O `forgot-password` responde sempre a mesma coisa, e o envio do e-mail roda **na fila**, para que nem o tempo de resposta denuncie o cadastro.
- **Senhas** são guardadas com bcrypt, que já inclui salt.
- **Dados pessoais (ADR-012):** o CPF é sempre mascarado nas respostas e nos logs, e o JWT não leva CPF nem OAB. Erros esperados (credenciais inválidas, token inválido) não vão para o log.
- **Um banco por serviço (ADR-012):** a aplicação tem uma única conexão (`identity`), e cada banco tem seu próprio usuário, sem privilégio de superusuário.
- **Perfis (D1):** só `advogado` e `comum` podem se cadastrar. `admin` vem só do seeder, e `empresa` é recusado com "Cadastro de empresas ainda não disponível." (o valor fica reservado em `UserType::RESERVED`).
- **Nginx:** envia `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` e `Referrer-Policy: no-referrer`, e bloqueia arquivos ocultos (`.env`, `.git`).
- **Imagem de produção:** roda como `www-data` (não-root). O código pertence a `root` e é somente leitura para a aplicação. A imagem não tem `.env` nem testes, e o OPcache não revalida arquivos.

> ⚠️ **HTTPS:** localmente, a API roda em **HTTP puro**, inclusive na "produção local". Em produção real, ela deve ficar atrás de um proxy com **TLS/HTTPS** (ADR-001). Sem isso, tokens e senhas trafegam em texto claro.

**Nginx em container próprio**, como a spec pede. Na produção há uma imagem Nginx que já leva a pasta `public/`, porque lá não existe volume de código.

**PHP-FPM como `root` só no dev:** no Windows/macOS, os arquivos do volume aparecem como `root`. Por isso, no dev, os workers do FPM rodam com o mesmo usuário da CLI, configurado em [docker/php/fpm-dev.conf](docker/php/fpm-dev.conf). Isso evita erros de permissão em `storage/`. A imagem de produção não usa essa configuração.

---

## 9. Solução de problemas

| Sintoma | Causa / solução |
|---|---|
| `Cannot connect to the Docker daemon` | O Docker Desktop não está aberto. Abra e espere ficar "running" |
| `port is already allocated` | Outra aplicação está usando a porta (ver [seção 1](#1-pré-requisitos)). Encerre essa aplicação |
| Respostas lentas no dev (1–4 s) | Limitação do Docker Desktop no Windows com código montado como volume. Não acontece na produção (~0,2 s). Para acelerar o dev, mantenha o projeto dentro do sistema de arquivos do WSL2 |
| `password authentication failed` depois de trocar `DB_PASSWORD` | A senha só é aplicada na criação do volume. Em dev, **apagando os dados**: `docker compose down -v && docker compose up -d`, depois rode `migrate --seed` |
| E-mail de recuperação não chega ao Mailpit | Confira se o worker está de pé (`docker compose ps queue`) e veja `docker compose logs queue` |
| 500 com `vendor/autoload.php` ausente | Rode `docker compose exec app composer install` |
| Deploy: `Falta .env.production` | Rode `bash deploy/setup-production.sh` |
| Deploy: `/health não respondeu` | Veja os logs: `docker compose -p veridit_prod -f docker-compose.prod.yml logs app` (na pasta `producao-app`) |
| `git push production` não acha o remote | O setup não foi executado neste clone. Rode `bash deploy/setup-production.sh` de novo |
