# front-public

Área pública da PHPeste Platform: o site da edição gerado pela plataforma ou, no modo site próprio, só a área de palestrante e participante num subdomínio da comunidade (`../docs/site-proprio.md`). Nuxt 4 com SSR, módulo-por-feature (`app/modules/{name}/`: types → services → composables → components → views), a partir do template [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular), como o `../front-admin`. Fala HTTP com `../backend`, nunca toca banco.

Regras de camadas em [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md). `app/modules/login/` é o módulo de referência do template. Decisão de arquitetura em `../docs/adr/0008-front-public-por-host.md`.

## Edição pelo endereço

| Peça | O que faz |
| --- | --- |
| `shared/edition/index.ts` | Regras puras: normalizar host, `route()` (301 para o domínio principal, 302 do site próprio para o site da comunidade fora de `AREA_PREFIXES`), `themeCss()` (só cor `#RRGGBB`, fonte e raio válidos) |
| `server/utils/editionApi.ts` | `resolveEdition(event, host)`: `GET /public/resolve` e o tema publicado, com cache por host e por edição (`NUXT_EDITION_CACHE_SECONDS`) |
| `server/middleware/edition.ts` | Resolve a edição de cada página, redireciona quando precisa, 404 para host desconhecido e 503 com a API fora do ar |
| `server/plugins/editionHead.ts` | Injeta no `<head>` o tema (variáveis `--ph-*`), a fonte, o favicon e `#edition-data` (JSON), também nas páginas `ssr: false` |
| `app/plugins/edition.ts` + `useCurrentEdition()` | A edição no app: do contexto no servidor, do `#edition-data` no navegador |
| `app/app.vue` | Sem edição, mostra `UnknownSite` (página neutra, sem citar edições) |

HTML nunca fica em cache (o mesmo caminho serve edições diferentes por host).

## Área do palestrante (`app/modules/proposals/`)

| Página | O quê |
| --- | --- |
| `/cfp` | Formulário em 3 passos (sobre você, sobre a palestra, revisão e envio). Entrar ou criar conta no passo 1 (`useSpeakerAuth`, sobre o `useAuthSubmit` do template). Rascunho guardado no navegador (`phpeste.cfp.draft.{edição}`) e salvo na plataforma 1,2 s depois de parar de digitar. `?proposta={id}` edita uma proposta enquanto o CFP está aberto. Fora da janela, mostra o CFP encerrado |
| `/palestrante/propostas` | Propostas da pessoa na edição, com status, decisão e confirmação |
| `/palestrante/propostas/{id}` | Detalhe (link dos e-mails de aprovação, lembrete e convite): confirmar ou recusar a participação, editar ou retirar enquanto o CFP está aberto |

Componente que depende da sessão observa `isAuthenticated` em vez de esperar um evento do `AuthPanel`: o painel some assim que a sessão existe, e um evento emitido depois disso se perde. Páginas de conta, palestrante e checkout rodam só no navegador (`routeRules`).

## Comandos

```bash
npm install
npx playwright install chromium
cp .env.example .env    # NUXT_PUBLIC_ADMIN_URL aponta o "Esqueci minha senha" para o painel
npm run dev          # http://localhost:3000, resolve a edição pelo host (cadastre o domínio no painel)
npm test
npm run test:e2e     # sobe uma API simulada (e2e/support/mockApi.ts) e testa vários hosts pelo X-Forwarded-Host
npm run typecheck
npm run lint
npm run build
```

Para testar vários hosts no dev: `NUXT_TRUST_FORWARDED_HOST=true npm run dev` e `curl -H 'X-Forwarded-Host: <host>' http://localhost:3000/`.

## Regras

- O `X-Forwarded-Host` só é aceito com `NUXT_TRUST_FORWARDED_HOST=true`, e só atrás de um proxy que o reescreve (Caddy). Sem proxy, ele deixaria qualquer um escolher a edição.
- Tudo que vai para o `<head>` a partir do tema passa pelas validações de `shared/edition` (cor, fonte, URL https); nunca injete valor da API sem validar.
- Página nova de conta, palestrante ou checkout entra em `AREA_PREFIXES` (para funcionar no site próprio) e, se depende de sessão, em `routeRules` com `ssr: false`.
- Os mesmos padrões do painel valem aqui: services não traduzem resposta, views não chamam service, e mudança de tela se verifica com `npm run test:e2e`.
