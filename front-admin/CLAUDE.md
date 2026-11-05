# front-admin

Painel da organização da PHPeste Platform. Nuxt 4, módulo-por-feature (`app/modules/{name}/`: types → services → composables → components → views), a partir do template [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular). Fala HTTP com `../backend` (Sanctum, Bearer token) — nunca toca banco ou `app-modules/*` diretamente.

Próximos módulos a construir, nesta ordem (ver `../docs/produto.md`): edição/identidade com prévia de tema → CFP e grade → ingressos/pedidos/gateway → participantes e check-in.

Design/protótipo das telas: [claude.ai/artifact/3B7ZWqjBQPDFZ5vhGgNs4N](https://claude.ai/artifact/3B7ZWqjBQPDFZ5vhGgNs4N#page-dcb81aee8d83).

Kit de UI do tema Sertão (componentes `Ph*`, tokens, acessibilidade): [`docs/UI-KIT.md`](docs/UI-KIT.md). Use-o antes de criar markup próprio.

Full layer rules live in [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — read that first, it's the actual convention document, not a summary. `.claude/rules/` covers what's specific to this being a template (which module is the reference, why `HttpClient` is shaped the way it is, the two different reasons something is "shared").

`app/modules/login/` is the reference module — a complete, working example of every layer, with tests for each. Imitate its shape when creating a new module.

## Structure

```
app/
  application/
    locales/pt-br/index.ts        # aggregates every module's locale — one import+key per module
    shared/
      composables/                 # cross-cutting, not owned by one module (useLogout, useAuthSubmit)
      services/                    # backend calls not owned by one module (authService)
  infrastructure/
    http/HttpClient.ts             # the only place that calls fetch() — Bearer auth, API-envelope errors
    http/api-types.d.ts            # gerado por `npm run api:gen` a partir do OpenAPI do Scramble — não editar à mão
  modules/
    login/                         # reference module
      types/index.ts
      services/{loginService,loginError}.ts
      composables/useLogin.ts
      components/LoginForm.vue
      views/LoginView.vue
      locales/pt-br/index.ts
      router/index.ts              # documental — Nuxt file-based routing resolves navigation today
  stores/
    authStore.ts                   # session data
    editionStore.ts                # edição ativa do painel — lido em quase toda tela
  pages/
    login.vue                      # wires the login module into Nuxt's file-based router
    index.vue
  plugins/
    auth.client.ts                 # hydrates authStore from localStorage on boot
e2e/
  login.spec.ts                    # Playwright — real browser, mocks only the network boundary
```

## Commands

```bash
npm install
npx playwright install chromium   # once, downloads the browser Playwright drives
cp .env.example .env
npm run dev
npm test              # vitest run — every module ships layer-by-layer tests, see login/
npm run test:e2e      # playwright test — real browser, see e2e/login.spec.ts
npm run test:e2e:ui   # same, with Playwright's interactive UI mode (watch it click through the app)
npm run typecheck     # nuxt typecheck (vue-tsc)
npm run lint          # eslint (via @nuxt/eslint) — inclui regras de formatação, sem Prettier separado
npm run build         # build de produção (nitro + nuxt)
```

CI (`.github/workflows/front-admin-ci.yml`): lint, typecheck, test e build em jobs separados.

## Tema "Sertão"

Tokens oficiais em `../docs/design/tema-sertao-tokens.png`, aplicados em `app/assets/css/main.css`: fonte Lexend + variáveis CSS `--ph-brand`, `--ph-accent`, `--ph-terra`, `--ph-link` (marca), `--ph-bg`, `--ph-surface`, `--ph-surface-2`, `--ph-line` (superfícies), `--ph-ink`, `--ph-ink-2`, `--ph-ink-3`, `--ph-on-brand` (texto) e `--ph-ok`/`--ph-warn`/`--ph-danger`/`--ph-info` com seus `-bg` (estados). Trocar um valor muda o visual do painel inteiro sem tocar em componente. Marca/destaque/detalhe/logo/fonte/raio são sobrescrevíveis por edição; cores de estado e de texto ficam fixas (contraste mínimo 4,5:1).

## Non-negotiables

- A module's `services/` never translates the API response — only the composable does. See `docs/ARCHITECTURE.md`, "Layers".
- A module's `views/` never calls a service directly — that's a component/composable's job.
- No per-module Pinia store. Global state (`app/stores/`) is only for things genuinely read across unrelated parts of the app — `authStore` is the reference example. See `.claude/rules/architecture.md`, "Two different reasons a piece of code is 'shared'".
- Every module with a view is registered in Nuxt's file-based routing (`app/pages/`) and in `app/application/locales/pt-br/index.ts` — both, every time.
- **A screen/form-flow change is not verified by `npm test` alone.** Run `npm run test:e2e` (or extend `e2e/*.spec.ts` for the flow you touched) before considering it done — Vitest's component tests mock `useLogin()`/the composable entirely, so they can't catch a broken hydration, a missing `aria-label`, or a redirect that silently stopped firing. See `.claude/rules/architecture.md`, "Testing".

## UI kit

[DaisyUI](https://daisyui.com/) is wired in `app/assets/css/main.css` via `@plugin 'daisyui';` (Tailwind v4 CSS-first config, no JS config file). `LoginForm.vue` demonstrates the intended usage: `fieldset`/`fieldset-legend`/`label` for form fields, `input`/`input-error`, `btn`/`btn-primary`/`btn-ghost`, `alert`/`alert-error` — component classes, not raw utilities, for anything that's a form control, button, or alert. See `.claude/rules/architecture.md`, "UI kit: DaisyUI".

## Cliente de API

`HttpClient.ts` espera o envelope de erro da API (`../docs/api.md`): `{code, message, errors, request_id}` em toda resposta não-2xx, e autenticação Bearer batendo com login estilo Sanctum (`{user, token}`). `HttpClientError` carrega `statusCode`, `fieldErrors`, `code` e `requestId`.

- **Tipos gerados do contrato**: `npm run api:gen` lê `http://localhost:8000/docs/api.json` (backend rodando) e escreve `app/infrastructure/http/api-types.d.ts`, commitado — mudar o contrato da API sem rodar `api:gen` de novo quebra o typecheck do front. Ver `app/application/shared/services/pingService.ts` como referência de serviço tipado a partir de `operations['<nome>']`.
- **401 em requisição autenticada** chama o handler registrado por `httpClient.setUnauthorizedHandler()` — hoje só `app/plugins/auth.client.ts` registra (limpa `authStore`, navega pro `/login`). Um 401 sem token (ex.: login errado) não dispara isso — `HttpClient` só decide que a sessão expirou quando *tinha* token.
- **Token Bearer, não cookie httpOnly** — decisão registrada em `docs/adr/0005-token-bearer-no-client.md` (o app mobile também consome a mesma API com o mesmo token).
- `useEdition()` (`app/application/shared/composables/useEdition.ts`) guarda a edição ativa — hoje só em memória, sem API por trás; liga no módulo Community quando ele existir.

## Roadmap (not built yet)

- No generator/skill scaffolding a new module — creating one is by hand, following `login`'s shape and `docs/ARCHITECTURE.md`.
- `register` (the natural second consumer of `useAuthSubmit`, alongside `login`) isn't built.
- No navigation chrome (sidebar/navbar) yet.
- `backend`'s Sanctum-shaped `/auth/login`/`/auth/logout` endpoints don't exist yet — needed for this app to talk to a real backend instead of just having the client-side shape ready.
- `useEdition()` não tem fonte de dados real (ver acima).
