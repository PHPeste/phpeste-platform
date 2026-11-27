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
| `/cfp` | Formulário em 3 passos (sobre você, sobre a palestra, revisão e envio). Entrar ou criar conta no passo 1 (`AuthPanel` e `useSiteAuth`, em `app/application/shared/`, sobre o `useAuthSubmit` do template; os mesmos da conta do participante). Rascunho guardado no navegador (`phpeste.cfp.draft.{edição}`) e salvo na plataforma 1,2 s depois de parar de digitar. `?proposta={id}` edita uma proposta enquanto o CFP está aberto. Fora da janela, mostra o CFP encerrado |
| `/palestrante/propostas` | Propostas da pessoa na edição, com status, decisão e confirmação |
| `/palestrante/propostas/{id}` | Detalhe (link dos e-mails de aprovação, lembrete e convite): confirmar ou recusar a participação, editar ou retirar enquanto o CFP está aberto |

## Checkout (`app/modules/checkout/`)

| Página | O quê |
| --- | --- |
| `/ingressos` | Passo 1: tipos com preço, lote, quanto resta e próximo lote; quantidade entre o mínimo e o máximo do tipo; cupom conferido na hora (`POST /public/editions/{id}/coupons/check`) e de novo quando a quantidade muda; resumo com subtotal, desconto e taxa "calculada ao reservar" nos tipos com `pass_fee_to_buyer`. Passo 2: quem paga (CPF só quando há valor, com máscara e dígitos conferidos) e um bloco por ingresso com as perguntas do tipo; erros num resumo com links para o campo, inclusive os 422 do backend (`items.{i}.attendees.{j}.…`). Reservar manda `Idempotency-Key`, repetida enquanto o corpo for o mesmo. `?reserva=expirada` ou `cancelada` mostra o aviso e recupera a última escolha (`phpeste.checkout.cart.{edição}`) |
| `/checkout/pedido/{id}` | O mesmo caminho que o backend usa na volta do gateway e no lembrete de Pix. Lê o pedido com o token guardado no navegador na reserva (`phpeste.checkout.order.{id}`, header `X-Order-Token`); sem ele, mostra "não encontramos este pedido neste navegador". Contagem regressiva (`role="timer"`, aviso falado com 5 e com 1 minuto), itens, desconto, taxa e total do backend, desistir da reserva. Reserva expirada (no backend ou com o tempo zerado na tela) ou cancelada volta para `/ingressos`. Pagamento (`usePayment`): quem recebe (`/public/editions/{id}/payments/receiver`), Pix com QR, copia e cola e validade, cartão na página do gateway (Asaas, com as parcelas escolhidas antes) ou no formulário seguro do Mercado Pago (`services/mercadoPago.ts` carrega o SDK; número, validade e código ficam nos campos do próprio Mercado Pago e só o `card_token` vai para a API). Clique repetido não sai do navegador enquanto a primeira cobrança está a caminho, e a mesma escolha repete a `Idempotency-Key`. O status é consultado com espera crescente (`POLL_DELAYS`, de 2 s a 30 s). Pago: ingressos com QR (desenhado no navegador com `uqr`) e o aviso de que o comprovante não é nota fiscal |

## Conta do participante (`app/modules/account/`)

| Página | O quê |
| --- | --- |
| `/conta/ingressos` | Link dos e-mails de ingresso (`/conta` redireciona para cá). Ingressos da pessoa (`/attendee/editions/{id}/tickets`: os que estão no nome dela ou, com o e-mail verificado, no e-mail dela) com QR (`components/TicketQr.vue`, compartilhado com o checkout), o que falta no crachá, salvar a imagem (PNG desenhado no navegador) e imprimir (`print:` esconde o resto da página). Com o e-mail ainda não confirmado, avisa e reenvia a confirmação |
| `/conta/ingressos/{id}` | QR grande, reenviar por e-mail, dados do crachá (nome, nome no crachá, empresa, camiseta e acessibilidade) e transferir por e-mail com confirmação. Transferido, o QR some da tela e da cópia guardada na hora (o backend já recusa o QR antigo como `replaced`) |
| `/conta/pedidos` | Pedidos de quem comprou (`/attendee/editions/{id}/orders`), com itens, cupom, taxa, total e reembolsos (valor, motivo, status); pedido aguardando pagamento leva de volta a `/checkout/pedido/{id}` |

QR sem internet: cada carga bem-sucedida guarda os ingressos em `phpeste.account.tickets.{edição}.{pessoa}` e, sem conexão (`statusCode` nulo), a tela mostra essa cópia com a hora em que foi salva. `public/sw.js` guarda só as páginas `/conta/ingressos` e os arquivos `/_nuxt/` que elas carregaram (a página manda a lista para o service worker em `keepTicketsOffline()`, fora do modo dev), então a página abre mesmo recarregada sem internet. Sair da conta apaga tudo que começa com `phpeste.account.` (`forgetAccountData`). O middleware da edição ignora `/sw.js`.

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

## Documento de arquitetura

Tela nova que mude o que o painel ou o site mostram, ou mudança de como o front fala com a API (resolução da edição pelo host, autenticação, tema), atualiza `../docs/arquitetura/` no mesmo PR. Ver `../docs/arquitetura/README.md`.
