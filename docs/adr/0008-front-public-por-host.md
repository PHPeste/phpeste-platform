# 0008 · front-public a partir do base_nuxt_modular, com a edição resolvida pelo host

**Status:** aceito

## Contexto

O mesmo código serve a área pública de todas as edições: o site da plataforma ou, quando a comunidade mantém o próprio site, só a área de palestrante e participante num subdomínio dela (`docs/site-proprio.md`). Cada endereço precisa abrir com a identidade da sua edição já na primeira pintura, sem rebuild por edição e sem vazar uma edição em outra.

## Decisão

- `front-public/` criado a partir do template [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular), como o front-admin (ADR 0004), com SSR ligado e o mesmo tema Sertão (`--ph-*`, DaisyUI), ESLint (`@nuxt/eslint`) e CI própria.
- **Edição pelo host, no servidor**: `server/middleware/edition.ts` lê o host (o `X-Forwarded-Host` só com `NUXT_TRUST_FORWARDED_HOST=true`, atrás do Caddy), chama `GET /public/resolve` e o tema publicado (`GET /public/editions/{id}/theme`) e guarda o resultado em `event.context.edition`.
- **Tema sem flash**: `server/plugins/editionHead.ts` injeta no `<head>` as variáveis CSS, a fonte e o favicon da edição, mais os dados da edição em JSON, no hook `render:html`. Vale tanto para página com SSR quanto para página só do navegador (`ssr: false`), que recebe o mesmo `<head>`.
- **Cache só do que é por host**: resolução e tema ficam em cache no servidor por `NUXT_EDITION_CACHE_SECONDS` (60 s por padrão, com `swr`), chaveados pelo host e pela edição. O HTML **não** fica em cache (`Cache-Control: private, no-store`), porque o mesmo caminho serve edições diferentes conforme o host.
- **Roteamento**: host que não é o principal vai para o principal com 301, mantendo o caminho. No site próprio, tudo que não é da área de palestrante e participante (`AREA_PREFIXES`) vai para o site da comunidade com 302.
- **Domínio desconhecido**: página neutra com 404, sem listar nem citar edições. API fora do ar: 503 com "tente de novo".
- As regras (normalizar host, decidir redirecionamento, gerar CSS só com cor `#RRGGBB`, fonte e URL https validadas) vivem em `shared/edition`, puras e testadas sem Nuxt.

## Consequências

- Uma instalação serve qualquer quantidade de edições e domínios. Publicar um tema novo vale no site em até `NUXT_EDITION_CACHE_SECONDS`.
- O servidor do Nuxt precisa alcançar a API (`NUXT_API_INTERNAL_URL`, na rede interna em produção).
- Não dá para cachear HTML numa CDN pelo caminho. Se for preciso, a chave do cache tem que incluir o host.
