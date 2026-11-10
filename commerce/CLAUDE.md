# Veridit — Commerce Service (backend Laravel)

> **Para o Claude Code:** especificação do Commerce. Siga as **Fases** da seção 10 na ordem e só avance
> quando os critérios de aceite da fase atual passarem. Em dúvida sobre regra de negócio, **pergunte**.
> Não crie front-end. Padrões de código, idioma, Docker e privacidade seguem o Identity (`../CLAUDE.md`).

---

## 1. Contexto

Serviço de **catálogo, compra, pagamento e saldo de créditos** do Veridit (SBA, ADR-003/005).
Requisitos: **REQ05** compra · **REQ06** pagamento · **REQ07** e-mail de compra (via evento para o
Notification) · **D2** pacotes básico, médio e premium.

| ADR | O que exige deste serviço |
|-----|---------------------------|
| ADR-001 | Aceitar o **JWT RS256** emitido pelo Identity, verificado só com a **chave pública** |
| ADR-002 / 011 | **Idempotência** em webhooks e no saldo; **outbox** transacional para eventos; inbox de eventos do provedor |
| ADR-006 | **Payments Adapter** (camada anticorrupção): Mercado Pago + PIX/QR por PSP; simulador para testes |
| ADR-007 | Packages, Payments e Ledger/Wallet são **módulos internos** de um único serviço |
| ADR-012 | Banco **próprio** (`commerce`); nunca acessar o banco do Identity; dados pessoais mínimos e mascarados |

Módulos internos (Figura 3 do documento):
- **Packages** — catálogo e pedidos (compra).
- **Payments** — adaptadores de provedor, webhooks, inbox de eventos.
- **Wallet** — saldo e lançamentos (ledger) imutáveis.
- **Outbox** — eventos internos (`commerce.order.paid`, ...).

---

## 2. Regras de negócio (definidas pela equipe)

| Regra | Valor |
|-------|-------|
| Pacotes (D2) | **Básico 10 créditos · Médio 25 · Premium 50** |
| Preços | R$ 30,00 · R$ 65,00 · R$ 120,00 (valores do design da equipe; configuráveis por `.env`) |
| Custo de captura | **Imagem 1 crédito · Vídeo 2 créditos** |
| Expiração de créditos | **Não expiram** |
| Estorno / reembolso | **Fora desta versão** |
| Faturamento (T05) | **Só dados já existentes no cadastro**: nome, e-mail e CPF mascarado, obtidos do Identity (`GET /me`) |

Regras derivadas:
1. Um pedido nasce `pendente` e vai para `pago` ou `falhou` **uma única vez** (sem voltar de `pago`).
2. Crédito é lançado **somente** quando o provedor confirma o pagamento (webhook/simulador), nunca na criação do pedido.
3. O mesmo evento do provedor entregue N vezes credita **uma vez** (inbox com chave única `provider + event_id`).
4. Cada pedido pago gera **no máximo um** lançamento de crédito (chave única no ledger).
5. Débito de captura é idempotente por `capture_id`; saldo **nunca** fica negativo (422 "Saldo insuficiente").
6. Saldo e lançamento são gravados na **mesma transação**, com bloqueio da carteira (`SELECT ... FOR UPDATE`).
7. `POST /orders` aceita header `Idempotency-Key`: repetir a requisição devolve o mesmo pedido (evita compra dupla).
8. Commerce **não** guarda CPF completo (só o mascarado do Identity) nem senha/token.

---

## 3. Banco `commerce`

| Tabela | Colunas principais |
|--------|-------------------|
| `packages` | id uuid, code (`basico`/`medio`/`premium`, único), name, credits, price_cents, active |
| `orders` | id uuid, user_id uuid, package_id, package_code, credits, amount_cents, status (`pendente`/`pago`/`falhou`), payment_method, provider_payment_id, idempotency_key (único por usuário), billing_name, billing_email, billing_cpf_masked, paid_at, failed_at |
| `wallets` | user_id uuid (PK), balance (≥ 0) |
| `ledger_entries` | id uuid, user_id, type (`credito`/`debito`), amount (> 0), balance_after, reason (`compra`/`captura_imagem`/`captura_video`), reference_type, reference_id — **único (reference_type, reference_id, type)** |
| `payment_events` | id, provider, event_id — **único (provider, event_id)** —, order_id, status, payload, processed_at |
| `outbox_messages` | id uuid, type, payload, occurred_at, published_at, attempts |

`user_id` é o `sub` do JWT; **não há FK** para o Identity (outro banco).

---

## 4. Endpoints (`/api/v1`)

| Método | Rota | Auth | Descrição |
|--------|------|------|-----------|
| GET | `/health` | — | Status da API e do banco |
| GET | `/packages` | — | Catálogo (REQ05 / D2) |
| GET | `/credits/costs` | — | Custo de cada tipo de captura |
| POST | `/orders` | JWT | Cria pedido de compra (REQ05). Corpo: `package`, `payment_method` |
| GET | `/orders` | JWT | Pedidos do usuário (admin: todos), paginado |
| GET | `/orders/{id}` | JWT | Detalhe (dono ou admin) |
| POST | `/orders/{id}/simulate` | JWT | **Simulador** (T06): `outcome` = `aprovado`/`recusado`. Só com `PAYMENTS_SIMULATOR_ENABLED=true` |
| POST | `/webhooks/simulator` | assinatura HMAC | Webhook do simulador (mesmo caminho dos provedores reais) |
| POST | `/webhooks/mercadopago` | assinatura `x-signature` | Webhook Mercado Pago (Fase 6) |
| POST | `/webhooks/pix` | conforme PSP | Webhook PIX do PSP (Fase 7) |
| GET | `/wallet` | JWT | Saldo do usuário |
| GET | `/wallet/entries` | JWT | Extrato (lançamentos), paginado |
| POST | `/internal/credits/consume` | `X-Service-Token` | **Contrato para o Evidence**: `user_id`, `capture_id`, `capture_type` (`imagem`/`video`) |

Formato de respostas e erros: igual ao Identity (`data`/`meta`/`message`/`errors`, mensagens em português).

---

## 5. Segurança

- JWT validado localmente com `IDENTITY_JWT_PUBLIC_KEY` (RS256 **fixo**: `alg` diferente → 401).
- Perfil vem da claim `type`; `admin` vê todos os pedidos.
- Webhooks: assinatura obrigatória, comparação em tempo constante; assinatura inválida → 401, sem detalhes.
- `/internal/*`: token de serviço (`COMMERCE_SERVICE_TOKEN`), nunca exposto ao front.
- Segredos de provedores só em `.env`; certificados PIX fora do Git (`commerce/storage/certs/`).

---

## 6. Payments Adapter (ADR-006)

Interface `PaymentGateway` com `createCharge(Order): Charge` e `parseWebhook(Request): PaymentEvent`.
Implementações: `SimulatorGateway` (Fase 4), `MercadoPagoGateway` (Fase 6), `PixGateway` (Fase 7, PSP a definir).
Todas convergem para o **mesmo** `PaymentEventProcessor` (inbox → status do pedido → crédito → outbox).

---

## 7. Eventos (outbox)

| Evento | Quando | Consumidor |
|--------|--------|------------|
| `commerce.order.paid` | Pedido confirmado e creditado | Notification (REQ07) |
| `commerce.order.failed` | Pagamento recusado | Notification |

Gravados na mesma transação do efeito. Relay e entrega ao Notification: Fase 8.

---

## 8. Docker

- Dev: serviços `commerce-app`, `commerce-web` (`http://localhost:8001`) e `commerce-queue` no `docker-compose.yml`
  da raiz (projeto `veridit_dev`), usando o `commerce-db` já existente.
- Produção local e deploy: Fase 9.

---

## 9. Testes

SQLite em memória; `Http::fake()` para Identity e provedores. Obrigatórios:
catálogo; criar pedido (201) e repetir com mesma `Idempotency-Key` (mesmo pedido); pacote inválido (422);
sem token (401); token com outra chave ou `alg` HS256 (401); pedido de outro usuário (403);
simulador aprovado credita; **webhook repetido credita uma vez**; recusado não credita; pedido pago não volta;
débito de imagem/vídeo; débito repetido com mesmo `capture_id` não debita de novo; saldo insuficiente (422);
token de serviço inválido (401); outbox gravado junto com o crédito.

---

## 10. Fases

| Fase | Entrega | Critério de aceite |
|------|---------|--------------------|
| 1 | Projeto Laravel em `commerce/` + Docker dev | `http://localhost:8001/api/v1/health` = 200 com banco `commerce` |
| 2 | Pacotes, pedidos, JWT do Identity | Testes de catálogo, pedido e autenticação passando |
| 3 | Wallet/Ledger + consumo de crédito (contrato Evidence) | Testes de saldo e idempotência passando |
| 4 | Simulador de pagamento + webhook + inbox + outbox | Fluxo completo simulado; webhook repetido credita 1 vez |
| 5 | Swagger | `/api/documentation` com todos os endpoints |
| 6 | Mercado Pago (sandbox) | Pagamento de teste credita via webhook (precisa de credenciais) |
| 7 | PIX via PSP (sandbox) | Idem (precisa do PSP escolhido e credenciais) |
| 8 | Relay do outbox → Notification (REQ07) | E-mail de compra enviado |
| 9 | Docker produção + deploy | `git push production main` publica o Commerce |
