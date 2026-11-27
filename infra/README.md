# infra

Docker Compose de desenvolvimento para o `backend/`: MySQL, Redis, Horizon (fila), scheduler (rotinas como expirar reservas e conciliar pagamentos) e Mailpit (captura de e-mail local). `./dev.sh up` sobe tudo; `./dev.sh` sem argumento lista os outros comandos.

## Uso

```bash
cp .env.example .env
docker compose up -d
```

A API sobe em `http://localhost:8000`. O Mailpit (inbox de e-mails de teste) fica em `http://localhost:8025`.

Proxy reverso e certificados dos domínios das edições: ver `docs/deploy/dominios-e-tls.md`. Backups ainda não foram definidos.
