# backend

API Laravel 13 / PHP 8.4 da PHPeste Platform. Módulos de domínio vivem em `app-modules/<nome>`, cada um um pacote Composer real (`internachi/modular`), namespace `PHPeste\<Nome>`, instalado via path-repository (`composer.json` → `repositories`) e symlink.

## Módulos

MVP: `identity`, `community`, `branding`, `cfp`, `program`, `ticketing`, `payments`, `check-in`, `notifications`, `audit`.
Fase 2/3 (scaffold vazio, sem lógica ainda): `sponsors`, `suppliers`, `finance`, `tasks`, `engagement`.
Ver `docs/produto.md` na raiz do monorepo para a responsabilidade de cada um.

## Estrutura de cada módulo (`app-modules/<nome>/src/`)

| Pasta | O que vai lá |
| --- | --- |
| `Models/` | Eloquent models do módulo |
| `Actions/` | Único lugar que pode mutar (`save`/`update`/`delete`) um Model com regra de negócio por trás — nunca direto de um Controller ou de outro módulo |
| `Data/` | DTOs / objetos de dados (ex.: Spatie Laravel Data) trocados entre camadas e módulos |
| `Events/` | Eventos de domínio disparados pelo módulo (ex.: `OrderPaid`) |
| `Listeners/` | Reage a eventos — do próprio módulo ou de outro |
| `Contracts/` | Interfaces que outro módulo pode depender, em vez de Eloquent direto |
| `Http/` | Controllers, FormRequests e API Resources |
| `Providers/` | Service provider do módulo (gerado pelo `make:module`) |

## Regras

- Módulos conversam por eventos de domínio e contratos (`Contracts/`), nunca chamando Models de outro módulo diretamente. `tests/Arch/ModuleIsolationTest.php` garante que nenhum módulo usa `App\`.
- Toda mutação de Model com regra de negócio por trás passa por uma `Action`, nunca `->save()`/`->update()` direto de um Controller.
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
