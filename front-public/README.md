# front-public

Área pública da PHPeste Platform (Nuxt 4, SSR). Cada endereço abre a sua edição com a identidade publicada, já na primeira renderização. Ver [`CLAUDE.md`](CLAUDE.md) e [`../docs/site-proprio.md`](../docs/site-proprio.md).

| Módulo | Páginas |
| --- | --- |
| `proposals` | `/cfp` (proposta em 3 passos), `/palestrante/propostas` (status, decisão e confirmação) |
| `checkout` | `/ingressos` (escolha, cupom e dados de quem vai), `/checkout/pedido/{id}` (reserva, Pix ou cartão, ingressos com QR) |
| `account` | `/conta/ingressos` (QR que abre sem internet, crachá, transferência), `/conta/pedidos` (pedidos e reembolsos) |
| `login` | `/login` |

```bash
npm install
npx playwright install chromium
cp .env.example .env
NUXT_TRUST_FORWARDED_HOST=true npm run dev   # escolha a edição pelo header X-Forwarded-Host
npm run lint && npm run typecheck && npm test
npm run test:e2e                              # Playwright com a API simulada
```
