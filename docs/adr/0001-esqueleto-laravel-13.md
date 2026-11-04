# 0001 · Esqueleto do backend em Laravel 13

**Status:** aceito

## Contexto

Todo o resto do backend (módulos, API, filas) depende de um projeto Laravel limpo, com qualidade automatizada desde o primeiro commit.

## Decisão

- `backend/` é um projeto Laravel 13, PHP 8.4, criado com `composer create-project laravel/laravel`.
- Pint (preset `laravel`), Larastan (nível 8) e Pest 4 configurados desde o início, com `declare(strict_types=1)` forçado pelo Pint.
- `composer check` roda pint + phpstan + rector --dry-run + pest; CI (`.github/workflows/backend-ci.yml`) roda cada um em paralelo, mais `composer audit`.
- `.env.example` documenta cada variável com um comentário e já aponta para os hostnames do `infra/docker-compose.yml` (mysql, redis, mailpit), para não exigir edição manual além de `APP_KEY`.

## Alternativas consideradas

- PostgreSQL como banco relacional: adiado — MySQL é o que o `infra/docker-compose.yml` já sobe hoje; revisitar se surgir motivo concreto.

## Consequências

- Qualquer pessoa clona o repo, roda `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate`, ou `infra/dev.sh up`, e tem a API rodando.
