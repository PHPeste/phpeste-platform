# Layout base do painel (issue #52)

Estrutura que envolve todas as telas autenticadas, igual aos componentes Sidebar e Topbar do design.

## Peças

| Peça | Onde |
| --- | --- |
| Layout autenticado (menu + barra + conteúdo) | `app/layouts/default.vue` |
| Layout sem moldura (login, catálogo dev) | `app/layouts/blank.vue` |
| Menu lateral de 264 px (`SideNav`), gaveta em telas estreitas (`MobileMenu`) | `app/application/shared/components/layout/` |
| Barra superior: `AppTopbar`, `EditionSwitcher`, `UserMenu` | idem |
| Itens do menu, grupos e papéis que enxergam cada um | `app/application/shared/layout/navigation.ts` |
| Decisão de acesso por rota (pura, testada) | `app/application/shared/layout/access.ts` |
| Guarda global (login + 403) | `app/middleware/auth.global.ts` |
| Edição atual e papel | `app/application/shared/composables/useEdition.ts` |
| Permissões derivadas do papel | `app/application/shared/composables/usePermissions.ts` |
| Página de erro global (404, 403, 500) | `app/error.vue` |
| Textos | `app/modules/shell/locales/pt-br` |

## Como adicionar uma tela ao menu

1. Inclua o item em `NAV_ITEMS` (`navigation.ts`) com rota, grupo, ícone e os papéis que podem vê-lo.
2. Adicione o rótulo em `app/modules/shell/locales/pt-br` (`nav.<key>`).
3. Crie a página em `app/pages/`. O menu, o item ativo e o 403 já passam a valer para ela.

## Regras

- **Guarda de rota:** sem token, vai para `/login?redirect=<rota>` e volta depois de entrar. Páginas públicas declaram `definePageMeta({ auth: false })`.
- **Papéis:** `organizador` vê tudo; os demais veem só os itens listados em `roles`. Item não permitido não aparece e a rota devolve 403.
- **Troca de edição:** guarda o id da edição num cookie, descarta o cache de dados (`clearNuxtData`) e recarrega (`refreshNuxtData`) mantendo a mesma rota. Composables de dados devem usar `useAsyncData`/`useFetch` para serem recarregados.
- **SPA:** o painel roda com `ssr: false`. A sessão vive no navegador, então não há o que renderizar no servidor, e assim o 403 da guarda roda antes de qualquer 404.
- **Acessibilidade:** `<nav aria-label="Principal">`, `aria-current="page"` no item ativo, link "Ir para o conteúdo", seletor e menu do usuário fecham com Esc devolvendo o foco, gaveta é `<dialog>` nativo.

## Dependência da API

`useEdition` consome `GET /editions` (lista de `{ id, slug, name, city, state, year, site_url, role }`), tipado à mão em `editionService` até o endpoint existir no OpenAPI (`npm run api:gen`). Sem edição carregada só a visão geral aparece. O token segue Bearer no cliente (ADR 0005).
