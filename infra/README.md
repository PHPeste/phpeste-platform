# infra

Docker Compose de desenvolvimento para o `backend/`: MySQL, Redis, Horizon e Mailpit (captura de e-mail local).

## Uso

```bash
cp .env.example .env
docker compose up -d
```

A API sobe em `http://localhost:8000`. O Mailpit (inbox de e-mails de teste) fica em `http://localhost:8025`.

Produção, proxy/TLS e backups ainda não foram definidos — a definir no Marco 0/1.
