# Veridit — Commerce Service

Catálogo de pacotes, compra, pagamento e saldo de créditos do Veridit (REQ05–REQ07, D2).

- **API (dev):** http://localhost:8001/api/v1 · **Swagger:** http://localhost:8001/api/documentation
- **Produção local:** http://localhost:8081
- **Especificação, regras de negócio e fases:** [CLAUDE.md](CLAUDE.md)
- **Instalação, Docker e deploy (todos os serviços):** [README da raiz](../../README.md)

## Pagamentos

| Forma | Como funciona |
|---|---|
| `pix` | O próprio Veridit mostra o QR Code e o PIX copia e cola, gerados pela API de pagamentos do Mercado Pago (PSP) |
| `mercadopago` | O comprador paga na página do Mercado Pago (cartão, saldo ou PIX) |
| `simulador` | Só para testes e demonstração: nenhum valor é cobrado |

A confirmação chega pelo webhook assinado `POST /api/v1/webhooks/mercadopago`. Os créditos entram uma
única vez por pedido, mesmo que o aviso chegue repetido. As credenciais ficam em `.env`
(`MERCADOPAGO_*`), nunca no Git.

## Dia a dia (comandos na raiz do monorepo)

```bash
docker compose exec commerce-app php artisan test        # testes
docker compose exec commerce-app php artisan migrate     # migrations
docker compose logs -f commerce-app commerce-queue       # logs
```

O `DB_PASSWORD` do `backend/commerce/.env` precisa ser igual a `COMMERCE_DB_PASSWORD` do `.env` da raiz.
