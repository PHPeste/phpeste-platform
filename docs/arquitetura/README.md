# Arquitetura da PHPeste Platform

**[PHPeste_Arquitetura.pdf](PHPeste_Arquitetura.pdf)** (19 páginas, A4 paisagem). O mesmo conteúdo, para abrir no navegador: [`fonte/arquitetura.html`](fonte/arquitetura.html).

| Página | Conteúdo |
|--------|----------|
| 1 | Capa, resumo e legenda |
| 2 | Visão geral do sistema: painel, site, API, fila, banco, serviços externos |
| 3 | Tenancy: comunidade, edição como tenant e o que é global |
| 4 | Como uma requisição encontra a edição (rotas, middleware, jobs, eventos, host) |
| 5 | Ciclo de vida da edição, transições, clonagem e Visão geral |
| 6 | Os 15 módulos do backend |
| 7 | Como os módulos conversam: contratos e pontos de extensão |
| 8 | Banco de dados por domínio (69 tabelas) |
| 9 | Papéis e permissões |
| 10 | Ingresso: da compra ao QR |
| 11 | CFP: da proposta à grade |
| 12 | Dinheiro: patrocínio, fornecedores e finanças |
| 13 | Fila, rotinas agendadas, e-mail e auditoria |
| 14 | Segurança |
| 15 | Desenvolvimento, CI e deploy |
| 16 | Telas: o design do painel (`docs/design/`) |
| 17 | Telas: o painel em uso (capturas do front-admin) |
| 18 | Telas: vendas e organização (cresce a cada tela do Marco 2) |
| 19 | Onde estamos e o que falta |

Retrato de 23/11/2026, com a A19 (tela de patrocinadores).

## Como atualizar

O documento é gerado: não edite o HTML nem o PDF à mão. Os diagramas são SVG escritos em `fonte/gerar.py`, e os números (tabelas, rotas, contratos, permissões) estão escritos lá. A partir da raiz do repositório:

```bash
python3 docs/arquitetura/fonte/gerar.py      # gera fonte/arquitetura.html
node docs/arquitetura/fonte/imprimir.mjs     # gera o PDF (usa o Playwright do front-admin/)
```

Para salvar também um PNG de cada página (bom para revisar o layout): `node docs/arquitetura/fonte/imprimir.mjs /tmp/paginas`.

Os prints do painel (`telas/`) saem do front-admin rodando com a API simulada:

```bash
cd front-admin && CAPTURAR_TELAS=1 npx playwright test e2e/capturas-arquitetura.spec.ts
```

Os números do documento conferem com o código assim:

```bash
cd backend
grep -ho "Schema::create('[a-z_]*'" app-modules/*/database/migrations/*.php database/migrations/*.php | sort -u | wc -l   # tabelas
php artisan route:list --json | python3 -c "import sys,json; print(sum(1 for r in json.load(sys.stdin) if r['uri'].startswith('api/')))"   # rotas da API
ls app-modules/*/src/Contracts/*.php | wc -l   # contratos
```

## Quando atualizar

No mesmo PR que muda a arquitetura: módulo novo ou que deixa de ser casca, tabela nova, contrato ou ponto de extensão novo, papel ou permissão nova, rotina agendada nova, serviço externo novo, mudança de fluxo (compra, CFP, dinheiro), ou tela nova que valha um print. Atualize também a data e o commit em `RETRATO`, no topo do `gerar.py`.
