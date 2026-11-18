# backend

API Laravel 13 / PHP 8.4 da PHPeste Platform. Módulos de domínio vivem em `app-modules/<nome>`, cada um um pacote Composer real (`internachi/modular`), namespace `PHPeste\<Nome>`, instalado via path-repository (`composer.json` → `repositories`) e symlink.

## Módulos

MVP: `identity`, `community`, `branding`, `cfp`, `program`, `ticketing`, `payments`, `check-in`, `notifications`, `audit`.
Fase 2/3 (scaffold vazio, sem lógica ainda): `sponsors`, `suppliers`, `finance`, `tasks`, `engagement`.
Ver `docs/produto.md` na raiz do monorepo para a responsabilidade de cada um.

Com implementação real até agora:

- `identity`: `users` (UUID, ver ADR 0007), cadastro com verificação de e-mail, login/logout via Sanctum, `GET /me` (com os papéis da pessoa em cada edição, lidos pelo contrato `EditionMemberships` do Community). Outro módulo consulta e cria conta pelo contrato `UserAccounts`. Recuperação de senha pelo broker do Laravel (link de uso único para `{ADMIN_URL}/redefinir-senha`, expira em `AUTH_PASSWORD_RESET_EXPIRE` minutos, derruba todas as sessões), troca de senha autenticada (mantém só a sessão atual) e login com GitHub via Socialite, sem sessão: `GET /auth/github/redirect` devolve a URL com `state` cifrado, o painel recebe o `code` em `{ADMIN_URL}/login/github` e manda `code` e `state` para `POST /auth/github/callback`. O vínculo usa só o e-mail primário verificado do GitHub (`social_accounts`). Conta criada pelo GitHub não tem senha (`password` nulo) e só desconecta o GitHub depois de definir uma.
- `community`: comunidades, edições com máquina de estados (`EditionStatus`), equipe (`edition_members`) com convites por e-mail (`edition_invitations`, token guardado só como hash, 7 dias) e a infraestrutura de tenancy usada pelos outros módulos (ver abaixo).
- `audit`: trilha mínima em `audit_entries`, alimentada por eventos de outros módulos (hoje `TeamChanged`). O listener é descoberto automaticamente em `Listeners/`, sem `Event::listen` manual.
- `branding`: tema por edição em `edition_settings` (rascunho, publicado e anterior, para reverter), presets Sertão/Maré/Brasa, checagem de contraste AA em `Theme/ContrastChecker`, upload de logos (SVG sanitizado com `enshrined/svg-sanitize`, disco `public`, exige `php artisan storage:link`) e `GET /api/v1/public/editions/{id}/theme` com cache e `ETag`. Os tokens de cor seguem os `--ph-*` do front-admin (`support` = `--ph-terra`, `on_accent` = texto sobre destaque).
- `notifications`: e-mail transacional por edição. Outro módulo envia pelo contrato `EditionMailer::send(editionId, EmailTemplate, email, nome, variáveis, url)`, que registra o envio em `email_deliveries` e enfileira `SendEmailDelivery` (5 tentativas, backoff 30s → 30min). Os tipos de e-mail ficam em `EmailTemplate` (assunto/corpo padrão, variáveis permitidas, transacional ou não), e a edição só sobrescreve assunto e corpo (`email_templates`). O corpo é texto com `{variavel}`, substituído sem Blade e com todo HTML escapado. O layout usa o tema publicado (Branding). Não transacional leva link assinado e `List-Unsubscribe` (one-click), e quem se descadastra fica em `email_unsubscribes`. Produção usa Resend (`MAIL_MAILER=resend`, `RESEND_API_KEY`) e dev usa o Mailpit.

## Estrutura de cada módulo (`app-modules/<nome>/src/`)

| Pasta | O que vai lá |
| --- | --- |
| `Models/` | Eloquent models do módulo |
| `Enums/` | Enums de domínio (status, papéis) |
| `Policies/` | Policies do Laravel, registradas no service provider do módulo |
| `Tenancy/` | Só no Community: contexto da edição atual, global scope e trait `BelongsToEdition` |
| `Rules/` | Regras de validação customizadas (`ValidationRule`) |
| `Theme/` | Só no Branding: tokens, contraste e montagem do tema público |
| `Actions/` | Único lugar que pode mutar (`save`/`update`/`delete`) um Model com regra de negócio por trás — nunca direto de um Controller ou de outro módulo |
| `Data/` | DTOs / objetos de dados (ex.: Spatie Laravel Data) trocados entre camadas e módulos |
| `Events/` | Eventos de domínio disparados pelo módulo (ex.: `OrderPaid`) |
| `Listeners/` | Reage a eventos — do próprio módulo ou de outro |
| `Contracts/` | Interfaces que outro módulo pode depender, em vez de Eloquent direto |
| `Http/` | Controllers, FormRequests e API Resources |
| `Jobs/` | Jobs de fila; recebem `edition_id` explícito no construtor |
| `Mail/` | Só no Notifications: Mailable, composição e renderização dos modelos |
| `Providers/` | Service provider do módulo (gerado pelo `make:module`) |

## Tenancy (a edição é o tenant, ADR 0007)

Vive em `PHPeste\Community\Tenancy` e é o que todo módulo com dado de edição usa:

- **Model de edição** usa o trait `BelongsToEdition`: liga o `EditionScope` e preenche `edition_id` a partir da edição no contexto ao criar. Sem edição no contexto a consulta não devolve nada e criar sem `edition_id` lança `LogicException`. Para consultar outra edição de propósito: `Model::query()->forEdition($editionId)`.
- **Rota admin de edição** usa `Route::apiV1Edition('<prefixo>')`, que monta `/api/v1/editions/{edition}/<prefixo>`, exige `auth:sanctum`, confere se a pessoa tem papel na edição (403 se não) e liga a edição no contexto (`CurrentEdition`) só durante a requisição. O papel específico de cada ação é conferido na Policy.
- **Job e listener** recebem `edition_id` explícito no construtor e rodam o trabalho dentro de `resolve(CurrentEdition::class)->run($editionId, fn () => ...)`, nunca resolvem a edição do contexto de quem disparou.
- **Evento de domínio** carrega ids e enums, não Models, porque outro módulo não pode usar Model do Community (ex.: `EditionStatusChanged(editionId, from, to)`).

## Papéis e permissões

Os papéis da edição (`EditionRole`) e o que cada um pode fazer (`EditionPermission`) vivem em código, em `EditionRole::permissions()`. Cada permissão vira um Gate com o próprio nome (`edition.manage`, `cfp.review`, `orders.view`...), que usa a edição do contexto ou uma passada como argumento:

```php
Gate::authorize(EditionPermission::ReviewProposals->value);
Gate::forUser($user)->allows(EditionPermission::CheckIn->value, $editionId);
```

Policy ou Gate de outro módulo confere permissão, nunca papel: `EditionMemberships::can($editionId, $userId, EditionPermission::...)`. A edição nunca fica sem organizador (trocar papel ou remover o último é bloqueado).

## Regras

- Módulos conversam por eventos de domínio e contratos (`Contracts/`), nunca chamando Models de outro módulo diretamente. `tests/Arch/ModuleIsolationTest.php` garante que nenhum módulo usa `App\`.
- Toda mutação de Model com regra de negócio por trás passa por uma `Action`, nunca `->save()`/`->update()` direto de um Controller.
- Toda entidade de edição carrega `edition_id`; usuários, perfis de palestrante e fornecedores são globais.
- Sem Filament — o admin é o Nuxt em `../front-admin`, consumindo esta API.
- Validação sempre por `FormRequest` ou `Data` object — nunca `$request->all()` em um controller. `tests/Arch/NoRawRequestAllTest.php` garante isso.
- Rota sensível (login, recuperação de senha, checkout, webhook) usa `throttle:<nome>` com um dos limiters nomeados em `App\Providers\SecurityServiceProvider` (`login`, `password-reset`, `checkout`, `webhooks`), configurável via `.env` (`RATE_LIMIT_*`).
- `Log::info/error/...` nunca precisa esconder segredo na mão — `App\Logging\RedactSensitiveDataProcessor` redige `password`/`token`/`api_key`/`secret`/`authorization`/cartão automaticamente em todo log (JSON estruturado, ver `config/logging.php`).
- View de módulo nunca usa nome que o Laravel já usa no mesmo namespace: o módulo `notifications` divide o namespace `notifications::` com o template de e-mail das Notifications do framework (`notifications::email`), e uma `email.blade.php` no módulo quebra todo e-mail de Notification (`NotificationMailRenderTest` renderiza de verdade para pegar isso).
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
