# PHPeste Platform — backend

API Laravel 13 / PHP 8.4, modular (`internachi/modular`). Ver `CLAUDE.md` para convenções e `../docs/`.

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## Qualidade

```bash
composer check   # pint + phpstan + rector --dry-run + pest
```
