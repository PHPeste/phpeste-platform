# front-admin

Painel da organização da [PHPeste Platform](../README.md). Nuxt 4, módulo-por-feature (`app/modules/{name}/`: types → services → composables → components → views), a partir do template [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular).

> Design/protótipo das telas: [claude.ai/artifact/3B7ZWqjBQPDFZ5vhGgNs4N](https://claude.ai/artifact/3B7ZWqjBQPDFZ5vhGgNs4N#page-dcb81aee8d83) · tokens do tema em [`../docs/design/tema-sertao-tokens.png`](../docs/design/tema-sertao-tokens.png).

> **Status:** `login` é o módulo de referência completo (types, service + error class, composable, component, view, testes em cada camada — unitário e e2e). 40 testes unitários e 6 e2e (Playwright) passam, typecheck passa, `npm run build` gera um build de produção funcional.

```bash
npm install
npx playwright install chromium   # uma vez — baixa o browser que o Playwright usa
cp .env.example .env
npm run dev
npm run lint        # eslint (via @nuxt/eslint)
npm run typecheck    # nuxt typecheck (vue-tsc)
npm test             # vitest — mocka a fronteira do composable/service
npm run test:e2e     # playwright — browser real, mocka só a rede
```

## A ideia

Cada funcionalidade é um módulo em `app/modules/{name}/`, com separação estrita de camadas: `types/` (só interfaces) → `services/` (chamada crua ao backend, sem traduzir a resposta) → `composables/` (estado + tradução + mapeamento de erro) → `components/` (cada um faz suas próprias chamadas) → `views/` (só orquestração, nunca chama service direto). Regras completas: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

`login` é o módulo de referência — imite o formato dele ao criar um novo. `app/application/shared/` guarda composables/services cross-cutting (como `useLogout`) que mais de um módulo precisa mas que não são estado global; `app/stores/` (Pinia) é só para estado genuinamente global — `authStore` é o único hoje.

## Estrutura

```
app/
  application/
    locales/pt-br/index.ts        # agrega o locale de cada módulo — um import+chave por módulo
    shared/
      composables/                 # cross-cutting, não é de um módulo só (useLogout, useAuthSubmit)
      services/                    # chamada ao backend que não é de um módulo só (authService)
  infrastructure/
    http/HttpClient.ts             # único lugar que chama fetch() — auth Bearer, erros no formato Laravel
  modules/
    login/                         # módulo de referência
      types/index.ts
      services/{loginService,loginError}.ts
      composables/useLogin.ts
      components/LoginForm.vue
      views/LoginView.vue
      locales/pt-br/index.ts
      router/index.ts              # documental — o roteamento por arquivo do Nuxt resolve a navegação hoje
  stores/
    authStore.ts                   # o único store (Pinia) global de verdade — dados da sessão
  pages/
    login.vue                      # liga o módulo login no roteamento por arquivo do Nuxt
    index.vue
  plugins/
    auth.client.ts                 # hidrata o authStore a partir do localStorage ao iniciar
e2e/
  login.spec.ts                    # Playwright — browser real, mocka só a fronteira de rede
```

## Regras principais

- `services/` de um módulo nunca traduz a resposta da API — só o composable faz isso.
- `views/` de um módulo nunca chama service direto.
- Sem store Pinia por módulo — estado global é só pra coisa lida de verdade em partes não relacionadas do app.
- Todo módulo com view é registrado no roteamento por arquivo do Nuxt e em `app/application/locales/pt-br/index.ts`.

Racional completo: [`.claude/rules/architecture.md`](.claude/rules/architecture.md). Tabela de nomenclatura: [`.claude/rules/naming-conventions.md`](.claude/rules/naming-conventions.md).

## Tema Sertão e UI kit

[DaisyUI](https://daisyui.com/) vem via sintaxe CSS-first do Tailwind v4 (`@plugin 'daisyui';` em `app/assets/css/main.css`, sem arquivo de config). Os tokens do tema Sertão (`--ph-*`, fonte Lexend) estão no mesmo arquivo — ver `CLAUDE.md`. `LoginForm.vue` é a referência de marcação — classes de componente (`fieldset`/`label`/`input`/`btn`/`alert`), não utilitárias cruas, para controle de formulário, botão e alerta.

## Testes

Duas camadas: `npm test` (Vitest) mocka a fronteira composable/service e roda rápido — um teste por camada de cada módulo. `npm run test:e2e` (Playwright, `e2e/*.spec.ts`) dirige um browser de verdade contra um servidor Nuxt efêmero, mockando só a rede (`page.route()`), pegando bug real de hidratação/acessibilidade/navegação que o teste de componente não pega. Mudança de tela ou de fluxo de formulário não está verificada só com `npm test` — ver [`.claude/rules/architecture.md`](.claude/rules/architecture.md), "Testing".

## Por que o formato de erro do backend é opinativo

`HttpClient.ts` espera corpo JSON `{message, errors}` no estilo Laravel em respostas não-2xx, e autenticação Bearer batendo com uma resposta de login estilo Sanctum (`{user, token}`) — feito pra conversar com `../backend` desta plataforma.
