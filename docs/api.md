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

`code` por tipo de erro: `validation_error` (422), `unauthenticated` (401), `forbidden` (403), `not_found` (404), `method_not_allowed` (405), `too_many_requests` (429), `server_error` (500). `errors` só é preenchido em erro de validação; fora isso é `null`.

## `X-Request-Id`

Todo request (web ou API) ganha um `X-Request-Id` — o que vier no header da requisição é respeitado, senão a aplicação gera um UUID (`app/Http/Middleware/AssignRequestId.php`). O valor volta no header da resposta, entra em todo log da requisição e aparece no envelope de erro.

## `Idempotency-Key`

Endpoint que cria pedido ou cobrança usa o middleware `idempotent` (alias de `App\Http\Middleware\EnsureIdempotencyKey`):

```php
Route::post('/orders', StoreOrderController::class)->middleware('idempotent');
```

Sem o header `Idempotency-Key`, a requisição recebe 400. Com o header, a resposta da primeira chamada fica em cache por 24h; uma segunda chamada com a mesma chave recebe a resposta cacheada em vez de repetir o efeito colateral — é isso que impede dois ingressos quando o gateway reenvia o mesmo webhook.

## Rate limiting

Rota sensível usa `throttle:<nome>` com um limiter nomeado em `App\Providers\SecurityServiceProvider`: `login`, `password-reset` (por IP + e-mail), `checkout`, `webhooks` (por IP). Limites configuráveis via `.env` (`RATE_LIMIT_LOGIN_ATTEMPTS`, `RATE_LIMIT_LOGIN_DECAY_MINUTES`, etc.) — estourar o limite responde 429.

## CORS

`config/cors.php` libera só as origens em `CORS_ALLOWED_ORIGINS` (lista separada por vírgula no `.env`) — nunca `*` fora de `local`. `X-Request-Id` está em `exposed_headers`, então o front consegue ler o header da resposta mesmo em requisição cross-origin.

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
