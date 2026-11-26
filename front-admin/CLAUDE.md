# front-admin

Painel da organização da PHPeste Platform. Nuxt 4, módulo-por-feature (`app/modules/{name}/`: types → services → composables → components → views), a partir do template [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular). Fala HTTP com `../backend` (Sanctum, Bearer token) — nunca toca banco ou `app-modules/*` diretamente.

Próximos módulos a construir, nesta ordem (ver `../docs/produto.md`): edição/identidade com prévia de tema → CFP e grade → ingressos/pedidos/gateway → participantes e check-in.

Design/protótipo das telas: [claude.ai/artifact/3B7ZWqjBQPDFZ5vhGgNs4N](https://claude.ai/artifact/3B7ZWqjBQPDFZ5vhGgNs4N#page-dcb81aee8d83).

Kit de UI do tema Sertão (componentes `Ph*`, tokens, acessibilidade): [`docs/UI-KIT.md`](docs/UI-KIT.md). Use-o antes de criar markup próprio.

Layout do painel (menu, barra superior, papéis, guarda de rota, erro global): [`docs/LAYOUT.md`](docs/LAYOUT.md).

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
    login/                         # reference module (login, "manter conectado", entrar com GitHub)
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
    login/index.vue                # wires the login module into Nuxt's file-based router
    login/github.vue               # volta do GitHub (módulo githubLogin)
    esqueci-senha.vue              # módulo forgotPassword
    redefinir-senha.vue            # módulo resetPassword (link do e-mail)
    convite/[token].vue            # módulo invitation (link do convite da equipe)
    comunidades.vue                # módulo communities: seletor de edição, nova comunidade, nova edição
    identidade.vue                 # módulo identity: editor de tema com prévia ao vivo
    equipe.vue                     # módulo team: equipe, convites e papéis da edição
    cfp/index.vue                  # módulo cfpSettings: janela, regras, trilhas, critérios e prévia do formulário
    cfp/avaliacao.vue              # módulo cfpReview: fila do revisor com atalhos, quadro e atribuição da organização
    palestrantes/index.vue         # módulo speakers: confirmações, cobertura dos nove estados e lista de espera
    palestrantes/[id].vue          # módulo speakers: perfil, palestras, viagem e hospedagem e confirmação
    ingressos/index.vue            # módulo tickets: tipos e lotes com valor líquido e prévia do checkout, cupons e regras da venda
    pedidos/index.vue              # módulo orders: lista com busca e filtro, detalhe com pagamento e histórico, reembolso, marcar como pago, reenviar cobrança, CSV
    participantes/index.vue        # módulo attendees (só organização): uma linha por ingresso, filtros de dados faltando, acessibilidade e transferidos, detalhe com edição registrada, cobrança de dados com prévia do e-mail, CSV dos crachás
    patrocinadores/index.vue       # módulo sponsors (só organização): indicadores da meta, funil arrastável com desfazer no erro e confirmação de cota sem vaga, perdidos, cotas
    patrocinadores/[id].vue        # módulo sponsors: contato e acordo, pagamentos, entregas, anexos por URL assinada, histórico de contato
    fornecedores/index.vue         # módulo suppliers (só organização): totais de orçado e contratado da lista filtrada, filtros por status e serviço, novo fornecedor, trazer de outra edição da comunidade
    fornecedores/[id].vue          # módulo suppliers: orçamentos, contratar e recusar, dados e contatos do cadastro da comunidade, anexos por URL assinada, histórico
    financas/index.vue             # módulo finance (organização e financeiro): receita, despesas pagas, saldo e previsão, orçamento por categoria com barra e texto, receita por origem e por conta, despesas com comprovante, exportar planilha e CSV
    pagamentos/index.vue           # módulo payments (só organização): conectar/trocar gateway, testar conexão, webhook e último evento, prévia do recebedor, eventos com reprocessar
    configuracoes.vue              # módulo editionSettings: dados e etapa da edição, domínio e site próprio, privacidade e auditoria
    meu-perfil.vue                 # módulo myProfile: conta, senha, sessões ativas e notificações
    index.vue
  plugins/
    auth.client.ts                 # hydrates authStore from localStorage (ou sessionStorage, sem "manter conectado") on boot
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
- **Estados de tela seguem o kit** (`docs/UI-KIT.md`, "Estados das telas"): esqueleto no lugar da rodinha, `PhLoadError` com "Tentar de novo", `PhEmptyState` com a próxima ação em toda lista e `PhFormErrors` nos formulários com mais de um campo. O checklist do PR (`.github/pull_request_template.md`) cobra isso.
- **A screen/form-flow change is not verified by `npm test` alone.** Run `npm run test:e2e` (or extend `e2e/*.spec.ts` for the flow you touched) before considering it done — Vitest's component tests mock `useLogin()`/the composable entirely, so they can't catch a broken hydration, a missing `aria-label`, or a redirect that silently stopped firing. See `.claude/rules/architecture.md`, "Testing".

## UI kit

[DaisyUI](https://daisyui.com/) is wired in `app/assets/css/main.css` via `@plugin 'daisyui';` (Tailwind v4 CSS-first config, no JS config file). `LoginForm.vue` demonstrates the intended usage: `fieldset`/`fieldset-legend`/`label` for form fields, `input`/`input-error`, `btn`/`btn-primary`/`btn-ghost`, `alert`/`alert-error` — component classes, not raw utilities, for anything that's a form control, button, or alert. See `.claude/rules/architecture.md`, "UI kit: DaisyUI".

## Cliente de API

`HttpClient.ts` espera o envelope de erro da API (`../docs/api.md`): `{code, message, errors, request_id}` em toda resposta não-2xx, e autenticação Bearer batendo com login estilo Sanctum (`{user, token}`). `HttpClientError` carrega `statusCode`, `fieldErrors`, `code` e `requestId`.

- **Tipos gerados do contrato**: `npm run api:gen` lê `http://localhost:8000/docs/api.json` (backend rodando) e escreve `app/infrastructure/http/api-types.d.ts`, commitado — mudar o contrato da API sem rodar `api:gen` de novo quebra o typecheck do front. Ver `app/application/shared/services/pingService.ts` como referência de serviço tipado a partir de `operations['<nome>']`.
- **401 em requisição autenticada** chama o handler registrado por `httpClient.setUnauthorizedHandler()` — hoje só `app/plugins/auth.client.ts` registra (limpa `authStore`, navega pro `/login`). Um 401 sem token (ex.: login errado) não dispara isso — `HttpClient` só decide que a sessão expirou quando *tinha* token.
- **Token Bearer, não cookie httpOnly** — decisão registrada em `docs/adr/0005-token-bearer-no-client.md` (o app mobile também consome a mesma API com o mesmo token).
- `useEdition()` (`app/application/shared/composables/useEdition.ts`) guarda a edição ativa (store `edition`), a lista de edições da pessoa e o papel dela (`load`, `select`, `role`). Consome `GET /editions`. Ver `docs/LAYOUT.md`.
- **Telas de entrada** (`login`, `githubLogin`, `forgotPassword`, `resetPassword`, `invitation`) usam o layout `blank` e `auth: false`. As de cartão usam `<AuthCard>` (`app/application/shared/components/layout`). Depois de entrar, todas levam para `HOME_AFTER_LOGIN` (`app/application/shared/layout/redirect.ts`), o seletor de edições em `/comunidades`.
- **ids de usuário e de edição são UUID** (`string`), como no backend.
- **Edição encerrada é só leitura**: `useEdition().isReadOnly` fica verdadeiro com a edição ativa em `closed`, e o layout mostra o aviso (`EditionReadOnlyBanner`). Tela que altera dado esconde ou desabilita a ação quando `isReadOnly`.

## Roadmap (not built yet)

- No generator/skill scaffolding a new module — creating one is by hand, following `login`'s shape and `docs/ARCHITECTURE.md`.
- Cadastro aberto (`register`) não existe por decisão: a entrada no painel é por convite da organização.

## Documento de arquitetura

Tela nova que mude o que o painel ou o site mostram, ou mudança de como o front fala com a API (resolução da edição pelo host, autenticação, tema), atualiza `../docs/arquitetura/` no mesmo PR. Ver `../docs/arquitetura/README.md`. Os prints do painel saem de `CAPTURAR_TELAS=1 npx playwright test e2e/capturas-arquitetura.spec.ts`.
