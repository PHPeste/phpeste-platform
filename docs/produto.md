# Produto — PHPeste Platform

Plataforma única e reutilizável para organizar o PHPeste todo ano: cada comunidade anfitriã configura sua edição (identidade, ingressos, CFP, programação) sem reinventar ferramentas. Primeiro uso real: PHPeste 2027, em João Pessoa (PB), organizado pela comunidade PHP-PB.

## Tenancy

Uma instalação, um banco. Tudo que pertence a uma edição carrega `edition_id` (global scope + policies). Usuários, perfis de palestrante e fornecedores são globais e preservam o histórico entre edições.

## Módulos do backend (`backend/app-modules/`)

| Módulo | Responsabilidade | Fase |
| --- | --- | --- |
| Identity | Usuários, login, perfis, papéis por edição | MVP |
| Community | Comunidades, edições, membros da organização, onboarding de nova edição | MVP |
| Branding | Tema (cores, logo, fontes, textos), domínio da edição, tokens para site e app | MVP |
| Cfp | Chamada, trilhas, formatos, propostas, avaliação, decisão e notificação | MVP |
| Program | Salas, sessões, grade, palestrantes publicados, agenda pessoal | MVP |
| Ticketing | Tipos de ingresso, lotes, cupons, pedidos, ingressos, QR, transferência | MVP |
| Payments | Interface de gateway, drivers (Asaas, Mercado Pago), webhooks idempotentes, estornos | MVP |
| CheckIn | Leitura de QR, credenciamento, equipe e dispositivos | MVP |
| Notifications | E-mail transacional, push, modelos por edição | MVP (e-mail) |
| Audit | Log de atividade e trilha de ações sensíveis (pagamento, check-in) | MVP |
| Sponsors | Cotas, contrapartidas, contratos, entregas | Fase 2 |
| Suppliers | Cadastro de fornecedores e contratos por edição | Fase 2 |
| Finance | Orçamento, despesas, comprovantes, resultado da edição | Fase 2 |
| Tasks | Kanban da organização por edição | Fase 2 |
| Engagement | Feedback de sessão, sorteio, certificados, networking | Fase 3 |

Os 10 módulos do MVP já existem como scaffold (`internachi/modular`, namespace `PHPeste\<Nome>`) no backend. Os de fase 2/3 ainda não foram criados.

## Front-admin

Nuxt a partir do [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular), módulo por domínio. Próxima ordem de módulos a construir:

1. edição (dados, status, equipe e papéis) e identidade (tema com prévia ao vivo)
2. CFP (configuração, fila de avaliação, decisão em lote) e grade (arrastar e soltar)
3. ingressos (tipos, lotes, cupons), pedidos (busca, reembolso, reenvio) e gateway (conectar credenciais e testar)
4. participantes e check-in (lista, exportação CSV, estatísticas de venda)

`front-public` (checkout, CFP público, site da edição) e `app` (Flutter) ainda não foram iniciados — ver decisões em aberto abaixo.

## Decisões de arquitetura — ver histórico da conversa de planejamento (3 nov 2026)

- Dinheiro: cada edição usa as credenciais do próprio gateway; a plataforma não recebe nem repassa.
- Auth: Sanctum, token Bearer, um fluxo só para admin e app.
- Filas: Redis + Horizon.
- Sem Filament: o admin é o Nuxt em `front-admin/`, consumindo a API do backend.

## Decisões em aberto

1. Controlador dos dados (LGPD) — comunidade controladora, PHPeste operador (a validar com advogado antes das vendas).
2. Um Nuxt ou dois — decidido: dois (`front-admin` SPA, `front-public` SSR), `front-public` ainda não criado.
3. Gateway inicial — Asaas primeiro.
4. Domínio e subdomínios das edições — subdomínio padrão com opção de domínio próprio.
5. Migração da suite da Rapadura (despesas e kanban) — conversar com a comunidade.
6. Dados históricos de edições anteriores — começar do zero.
