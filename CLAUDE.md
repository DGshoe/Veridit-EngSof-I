# Veridit — monorepo

> **Para o Claude Code:** cada projeto tem sua própria especificação. Leia a do projeto em que for trabalhar
> antes de alterar código, e siga as regras dela (fases, idioma, segurança, testes antes do commit).

| Pasta | Especificação |
|-------|---------------|
| `backend/identity/` | [backend/identity/CLAUDE.md](backend/identity/CLAUDE.md) — Identity Service (REQ01–04), padrões gerais do backend |
| `backend/commerce/` | [backend/commerce/CLAUDE.md](backend/commerce/CLAUDE.md) — Commerce Service (REQ05–07, D2) |
| `frontend/` | Front-end (Preact + Vite), mantido por outra pessoa da equipe: só alterar quando pedido |

## Estrutura

```
Veredit/
├── backend/identity/        Laravel — Identity (docker/php, docker/nginx próprios)
├── backend/commerce/        Laravel — Commerce (docker/php, docker/nginx próprios)
├── frontend/                Preact + Vite (:5173)
├── docker/postgres/init/    cria os 4 bancos (identity, commerce, evidence, notification)
├── docker-compose.yml       dev  (projeto veridit_dev:  Identity :8000, Commerce :8001)
├── docker-compose.prod.yml  produção local (veridit_prod: :8080, :8081)
├── deploy/                  post-receive + setup-production.sh (git push production main)
├── .env.example             senhas dos 4 bancos (só o Docker Compose lê)
└── README.md
```

## Regras do monorepo

1. Comandos `docker compose ...` rodam na **raiz**. Testes: `docker compose exec app php artisan test` (Identity)
   e `docker compose exec commerce-app php artisan test` (Commerce).
2. ADR-012: cada serviço conhece **só a senha do próprio banco**. As senhas de todos os bancos ficam apenas no
   `.env` da raiz. Nunca colocar senha de outro banco no `.env` de um serviço.
3. Nunca commitar `.env`, `.env.production`, `.env.local`, segredos ou credenciais (raiz, serviços ou front).
4. Antes de cada commit que toca o backend: rodar os testes dos **dois** serviços (o deploy roda os dois).
5. Respostas ao usuário em português; código em inglês.
