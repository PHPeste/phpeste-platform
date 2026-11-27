# Convenções da API

Admin, site público e app consomem a mesma API. Este documento é o contrato — qualquer endpoint novo segue isto, não reinventa.

## Versionamento e rotas

Toda rota de API vive sob `/api/v1`, registrada com a macro `Route::apiV1()` (definida em `app/Providers/AppServiceProvider.php`), nunca com `Route::prefix('api/v1')` escrito à mão:

```php
// app-modules/<nome>/routes/<nome>-routes.php
Route::apiV1('community')->group(function (): void {
    Route::get('/editions', EditionsController::class)->name('community.editions.index');
});
```

Isso garante o mesmo middleware group (`api`) e o mesmo prefixo em todo módulo. Ver `routes/api.php` (`GET /api/v1/ping`) como referência mínima.

Middleware extra vai no segundo argumento, nunca encadeado com `->middleware()`: no `RouteRegistrar` isso substitui o `api` em vez de somar (`tests/Feature/Api/ApiMiddlewareGroupTest.php` garante que toda rota de `/api/v1` passa pelo grupo `api`).

```php
Route::apiV1(middleware: ['auth:sanctum'])->get('/me', MeController::class)->name('me');
```

Rota admin de uma edição usa `Route::apiV1Edition()` (registrada pelo módulo Community), que monta `/api/v1/editions/{edition}/...`, exige login e papel na edição e liga a edição no contexto:

```php
Route::apiV1Edition('cfp')->group(function (): void {
    Route::get('/proposals', ProposalsController::class)->name('cfp.proposals.index');
});
```

Rota de uma edição para quem não é da equipe (palestrante, participante) usa `Route::apiV1PublishedEdition()`, que monta `/api/v1/<area>/editions/{edition}/...`, só aceita edição publicada e liga a edição no contexto sem exigir papel:

```php
Route::apiV1PublishedEdition('speaker', 'proposals', ['auth:sanctum'])->group(function (): void {
    Route::get('/', [SpeakerProposalController::class, 'index'])->name('cfp.speaker.proposals.index');
});
```

### Famílias de rota

| Prefixo | Quem usa | Autenticação |
| --- | --- | --- |
| `/api/v1/auth/…`, `/api/v1/me/…` | todos | login devolve token Sanctum; o resto pede `Bearer` |
| `/api/v1/editions/{edition}/…` | painel da organização | `Bearer` e papel na edição (permissões por gate) |
| `/api/v1/speaker/editions/{edition}/…` | palestrante | `Bearer`, edição publicada |
| `/api/v1/attendee/editions/{edition}/…` | participante (ingressos, crachá, transferência, pedidos) | `Bearer`, edição publicada; a posse vem do `user_id` ou do e-mail verificado |
| `/api/v1/checkout/editions/{edition}/orders/…` | quem compra, com ou sem conta | criar pedido pede `Idempotency-Key`; o resto pede o `X-Order-Token` devolvido na criação |
| `/api/v1/public/…` | site da edição | sem login (resolução pelo host, tema, CFP, ingressos à venda, cupom, recebedor do pagamento) |
| `/api/v1/webhooks/payments/{token}`, `/api/v1/webhooks/resend` | gateways e Resend | assinatura do remetente, `throttle:webhooks` |

`X-Order-Token` é o `access_token` do pedido (guardado cifrado): quem comprou sem conta acompanha o pedido, paga e vê os ingressos só com ele. Sem o header, a rota responde 404.

## Envelope de erro

Toda resposta de erro da API (`app/Support/Api/ApiError.php`) segue o mesmo formato, incluindo 404 e 500:

```json
{
  "code": "validation_error",
  "message": "Os dados enviados são inválidos.",
  "errors": { "email": ["O campo email é obrigatório."] },
  "request_id": "20670a74-f702-4eae-8deb-53daaeff3cc1"
}
```

`code` por tipo de erro: `validation_error` (422), `unauthenticated` (401), `forbidden` (403), `not_found` (404), `method_not_allowed` (405), `gone` (410, recurso que existiu e não vale mais, como convite usado ou expirado; a `message` explica o motivo), `too_many_requests` (429), `server_error` (500). `errors` só é preenchido em erro de validação; fora isso é `null`.

## `X-Request-Id`

Todo request (web ou API) ganha um `X-Request-Id` — o que vier no header da requisição é respeitado, senão a aplicação gera um UUID (`app/Http/Middleware/AssignRequestId.php`). O valor volta no header da resposta, entra em todo log da requisição e aparece no envelope de erro.

## Idempotência

- **Criar pedido** (`POST /checkout/editions/{id}/orders`) exige o header `Idempotency-Key` (sem ele, 400). A garantia fica no banco, não no cache: `unique(edition_id, idempotency_key)` mais o hash do corpo. A mesma chave com o mesmo corpo devolve o mesmo pedido; com outro corpo, 409. O site repete a chave enquanto o corpo não muda.
- **Iniciar o pagamento** trava por pedido (`Cache::lock` no `ChargeService`): dois cliques ao mesmo tempo geram uma cobrança só.
- **Webhooks** entram numa caixa de entrada (`webhook_events`) com o id do evento único por conta de gateway; evento repetido não é processado de novo, e o status vem sempre de uma consulta ao gateway, nunca do corpo recebido.
- Para outros endpoints que criam algo, há o middleware genérico `idempotent` (`App\Http\Middleware\EnsureIdempotencyKey`): sem o header, 400; com o header, guarda a resposta por 24h (inclusive 422) e devolve a mesma resposta na repetição. Ele não serve para o checkout, porque um 422 guardado impediria corrigir os dados e tentar de novo com a mesma chave.

## Rate limiting

Rota sensível usa `throttle:<nome>` com um limiter nomeado em `App\Providers\SecurityServiceProvider`: `login`, `password-reset` (por IP + e-mail), `checkout`, `webhooks` (por IP). Limites configuráveis via `.env` (`RATE_LIMIT_LOGIN_ATTEMPTS`, `RATE_LIMIT_LOGIN_DECAY_MINUTES`, etc.) — estourar o limite responde 429.

## CORS

`config/cors.php` libera só as origens em `CORS_ALLOWED_ORIGINS` (lista separada por vírgula no `.env`) — nunca `*` fora de `local`. Além delas, `AllowEditionSiteOrigins` libera, só nas rotas `/api/v1/public/*` e só naquela requisição, a origem `https` do site próprio cadastrado numa edição que não está em rascunho (ver `site-proprio.md`). `X-Request-Id` está em `exposed_headers`, então o front consegue ler o header da resposta mesmo em requisição cross-origin.

## Paginação

- **Cursor** (`cursorPaginate()`): listas públicas ou potencialmente grandes (programação, propostas do CFP). Não permite pular página, mas escala sem `OFFSET`.
- **Página** (`paginate()`): telas administrativas (pedidos, participantes) onde navegar por número de página importa.

As duas já serializam como `{data, links, meta}` pelo Resource padrão do Laravel — não precisa reimplementar isso, só escolher a estratégia certa por endpoint.

## Resource padrão

Toda API Resource estende `App\Http\Resources\ApiResource`, que traz:

- `money(?int $cents, string $currency = 'BRL')` → `{"amount": 1990, "currency": "BRL"}`. Dinheiro é sempre inteiro em centavos, nunca float.
- `isoDate(?Carbon $date)` → string ISO 8601 em UTC (`2027-10-08T12:00:00Z`).

## Documentação (Scramble)

`GET /docs/api` (UI) e `GET /docs/api.json` (OpenAPI) são gerados automaticamente a partir das rotas e dos Form Requests/Resources — não se escreve YAML/JSON de OpenAPI à mão. Cada módulo vira um grupo na doc porque cada controller vive no namespace do próprio módulo (`PHPeste\<Nome>\Http\Controllers`).

Fora do ambiente `local`, a doc só é acessível a usuário autenticado (gate `viewApiDocs` em `AppServiceProvider`).
