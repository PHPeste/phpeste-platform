# 0004 · Bootstrap do front-admin a partir do base_nuxt_modular

**Status:** aceito

## Contexto

O painel da organização precisa de um ponto de partida com convenção de módulos, testes e autenticação já resolvidos, para não reinventar estrutura a cada tela nova.

## Decisão

- `front-admin/` criado a partir do template [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular): Nuxt 4, módulo-por-feature (`app/modules/<nome>/types → services → composables → components → views`), Pinia só para a store de auth, Tailwind v4 + DaisyUI.
- `NUXT_PUBLIC_API_URL` aponta para `http://localhost:8000/api/v1` (o template original usava `NUXT_PUBLIC_API_BASE`; renomeado pra bater com o resto da documentação da plataforma).
- Fala HTTP com `backend` via Sanctum (Bearer token) — nunca toca banco ou `app-modules/*` diretamente.
- Tema "Sertão" (fonte Lexend, tokens CSS `--ph-*`) conforme `docs/design/tema-sertao-tokens.png`, aplicado em `app/assets/css/main.css`. Marca, destaque, detalhe, logo, fonte de título e raio são sobrescrevíveis por edição (`edition_settings.theme`); cores de estado e de texto ficam fixas para manter o contraste mínimo de 4,5:1.
- Lint via `@nuxt/eslint` (inclui regras de formatação do `@stylistic`) — sem Prettier separado, para não manter duas ferramentas de formatação competindo.
- CI (`.github/workflows/front-admin-ci.yml`): lint, typecheck, testes unitários e build.
- Sem proxy de dev: `backend/config/cors.php` (padrão do Laravel) já permite a origem do `front-admin` em desenvolvimento; `HttpClient.ts` chama `NUXT_PUBLIC_API_URL` direto.

## Consequências

- Próximo módulo a construir segue o `app/modules/login/` como referência de camadas e testes.
