# backend

API Laravel 13 / PHP 8.4 da PHPeste Platform. Módulos de domínio vivem em `app-modules/<nome>`, cada um um pacote Composer real (`internachi/modular`), namespace `PHPeste\<Nome>`, instalado via path-repository (`composer.json` → `repositories`) e symlink.

## Módulos (MVP)

`identity`, `community`, `branding`, `cfp`, `program`, `ticketing`, `payments`, `check-in`, `notifications`, `audit` — ver `docs/produto.md` na raiz do monorepo para a responsabilidade de cada um.

## Regras

- Módulos conversam por eventos de domínio e contratos, nunca chamando Models de outro módulo diretamente. `tests/Arch/ModuleIsolationTest.php` garante que nenhum módulo usa `App\`.
- Toda entidade de edição carrega `edition_id`; usuários, perfis de palestrante e fornecedores são globais.
- Sem Filament — o admin é o Nuxt em `../front-admin`, consumindo esta API.

## Comandos

```bash
php artisan make:module <Nome>     # cria novo módulo em app-modules/
composer update phpeste/<nome>     # linka o módulo recém-criado
php artisan modules:list

composer check     # pint + phpstan + rector --dry-run + pest
composer pint
composer phpstan
composer rector
composer test
```
