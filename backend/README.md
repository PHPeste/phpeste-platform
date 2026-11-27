# PHPeste Platform — backend

API Laravel 13 / PHP 8.4, modular (`internachi/modular`). Ver `CLAUDE.md` para convenções e `../docs/`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Sem Docker, dá para rodar com SQLite: `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=file` e `MAIL_MAILER=log` no `.env`. Com Docker, ver `../infra/`.

```bash
php artisan serve          # http://localhost:8000, documentação da API em /docs/api
php artisan schedule:work  # rotinas: reservas vencidas, virada de lotes, webhooks, conciliação, lembretes
php artisan queue:work     # e-mails e webhooks, quando a fila não é sync
```

Módulos em `app-modules/` (ver a tabela no [README da raiz](../README.md#módulos-do-backend)) e o que cada um faz em detalhe no `CLAUDE.md`.

## Qualidade

```bash
composer check   # pint + phpstan + rector --dry-run + pest
```
