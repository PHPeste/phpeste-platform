# backend

API Laravel 13 / PHP 8.4 da PHPeste Platform. Módulos de domínio vivem em `app-modules/<nome>`, cada um um pacote Composer real (`internachi/modular`), namespace `PHPeste\<Nome>`, instalado via path-repository (`composer.json` → `repositories`) e symlink.

## Módulos

MVP: `identity`, `community`, `branding`, `cfp`, `program`, `ticketing`, `payments`, `check-in`, `notifications`, `audit`.
Fase 2/3 (scaffold vazio, sem lógica ainda): `sponsors`, `suppliers`, `finance`, `tasks`, `engagement`.
Ver `docs/produto.md` na raiz do monorepo para a responsabilidade de cada um.

`identity` é o único com implementação real até agora: `users` (UUID, ver ADR 0007), cadastro com verificação de e-mail, login/logout via Sanctum, `GET /me`. Tenant (edição, papéis) é dos módulos Community (#9/#14), ainda não implementados — `/me` devolve `roles: []` até lá.

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
- Validação sempre por `FormRequest` ou `Data` object — nunca `$request->all()` em um controller. `tests/Arch/NoRawRequestAllTest.php` garante isso.
- Rota sensível (login, recuperação de senha, checkout, webhook) usa `throttle:<nome>` com um dos limiters nomeados em `App\Providers\SecurityServiceProvider` (`login`, `password-reset`, `checkout`, `webhooks`), configurável via `.env` (`RATE_LIMIT_*`).
- `Log::info/error/...` nunca precisa esconder segredo na mão — `App\Logging\RedactSensitiveDataProcessor` redige `password`/`token`/`api_key`/`secret`/`authorization`/cartão automaticamente em todo log (JSON estruturado, ver `config/logging.php`).
- Segredo nunca vai pro repositório — ver `docs/adr/0006-politica-de-segredos.md`.
- Macro/bind que uma rota de módulo usa (ex.: `Route::apiV1()`) tem que ser registrada no `register()` do provider, nunca no `boot()` — o `internachi/modular` carrega as rotas de cada módulo num hook `booting()` que dispara antes do `boot()` de qualquer provider (inclusive do `AppServiceProvider`).

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
