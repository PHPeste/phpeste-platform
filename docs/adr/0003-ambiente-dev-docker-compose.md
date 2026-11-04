# 0003 · Ambiente de desenvolvimento com Docker Compose

**Status:** aceito

## Contexto

Qualquer pessoa da comunidade precisa subir o projeto em poucos minutos, com os mesmos serviços de produção (banco, fila, e-mail).

## Decisão

- `infra/docker-compose.yml`: `backend` (runtime PHP 8.4 do Laravel Sail), `horizon` (fila), `scheduler` (`schedule:work`), `mysql`, `redis` e `mailpit`, todos na mesma rede `phpeste`.
- `backend/.env`/`.env.example` já apontam para os hostnames do compose (`mysql`, `redis`, `mailpit`) — não precisa de variável extra no `docker-compose.yml` para isso (ver nota técnica abaixo).
- Healthcheck em todos os serviços; `backend`, `horizon` e `scheduler` só sobem depois que `mysql` e `redis` respondem `service_healthy`.
- `infra/dev.sh` com `up`/`down`/`reset`/`shell`/`logs`.

## Nota técnica: por que o `.env` e não `environment:` no compose

`php artisan serve` (o servidor usado pelo runtime do Sail) descarta, por padrão, qualquer variável de ambiente do processo que não esteja declarada no `.env` do projeto — faz parte do mecanismo de auto-reload do comando. Isso foi descoberto na prática: variáveis passadas só via `environment:` no `docker-compose.yml` (sem estar no `.env`) eram silenciosamente ignoradas pelo container, e a aplicação caía de volta no valor do `.env` (ex.: `127.0.0.1` em vez de `redis`). A correção foi declarar os hostnames do compose direto no `.env`/`.env.example`.

## Consequências

- `infra/dev.sh up` entrega a API em `http://localhost:8000`, Mailpit em `http://localhost:8025`.
- Quem rodar o backend fora do Docker (`php artisan serve` direto) precisa trocar `DB_HOST`/`REDIS_HOST`/`MAIL_HOST` no `.env` local para `127.0.0.1`.
