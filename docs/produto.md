# Produto — PHPeste Platform

Plataforma única e reutilizável para organizar o PHPeste todo ano: cada comunidade anfitriã configura sua edição (identidade, ingressos, CFP, programação) sem reinventar ferramentas. Primeiro uso real: PHPeste 2027, em João Pessoa (PB), organizado pela comunidade PHP-PB.

## Tenancy

Uma instalação, um banco. Tudo que pertence a uma edição carrega `edition_id` (global scope + policies). Usuários, perfis de palestrante e fornecedores são globais e preservam o histórico entre edições.

## Módulos do backend (`backend/app-modules/`)

| Módulo | Responsabilidade | Fase | Situação |
| --- | --- | --- | --- |
| Identity | Usuários, login, perfis, papéis por edição | MVP | Pronto (senha, GitHub, recuperação de senha, perfil de palestrante) |
| Community | Comunidades, edições, membros da organização, onboarding de nova edição | MVP | Pronto |
| Branding | Tema (cores, logo, fontes, textos), domínio da edição, tokens para site e app | MVP | Pronto (inclui site próprio) |
| Cfp | Chamada, trilhas, formatos, propostas, avaliação, decisão e notificação | MVP | Pronto |
| Program | Salas, sessões, grade, palestrantes publicados, agenda pessoal | MVP | Marco 3 |
| Ticketing | Tipos de ingresso, lotes, cupons, pedidos, ingressos, QR, transferência | MVP | Pronto (inclui importação e conciliação) |
| Payments | Interface de gateway, drivers (Asaas, Mercado Pago), webhooks idempotentes, estornos | MVP | Pronto |
| CheckIn | Leitura de QR, credenciamento, equipe e dispositivos | MVP | Marco 3 (o QR já é conferido pelo contrato `TicketVerifier`) |
| Notifications | E-mail transacional, push, modelos por edição | MVP (e-mail) | E-mail pronto (modelos editáveis, webhooks do Resend); push no Marco 3 |
| Audit | Log de atividade e trilha de ações sensíveis (pagamento, check-in) | MVP | Pronto |
| Sponsors | Cotas, contrapartidas, contratos, entregas | Fase 2 | Pronto (antecipado para o Marco 2) |
| Suppliers | Cadastro de fornecedores e contratos por edição | Fase 2 | Pronto (antecipado para o Marco 2) |
| Finance | Orçamento, despesas, comprovantes, resultado da edição | Fase 2 | Pronto (antecipado para o Marco 2) |
| Tasks | Kanban da organização por edição | Fase 2 | Pronto (antecipado para o Marco 2) |
| Engagement | Feedback de sessão, sorteio, certificados, networking | Fase 3 | Marco 4 (só o esqueleto) |

Os 15 módulos existem no backend (`internachi/modular`, namespace `PHPeste\<Nome>`). Program, CheckIn e Engagement ainda são só o esqueleto.

## Marcos

| Marco | Situação | O que entrou |
| --- | --- | --- |
| 0 · Fundação | Concluído | esqueleto do backend, módulos, Docker, convenções da API, segurança base; front-admin com tema Sertão, layout e cliente de API |
| 1 · Núcleo e CFP | Concluído | contas, comunidades, edições, equipe, identidade, domínios, e-mail, auditoria, CFP completo; painel do núcleo e do CFP; front-public com resolução pelo host e formulário do CFP |
| 2 · Ingressos | Concluído | ingressos, cupons, pedidos, Asaas, Mercado Pago, webhooks, reembolso, conciliação, QR, importação, patrocínio, fornecedores, finanças, tarefas; painel de tudo isso; checkout e conta do participante no site |
| 3 · Evento pronto | A fazer | programação e grade, check-in com modo offline, push, API pública e checkout embutível, site da edição (template, SEO, LGPD), área do palestrante com viagem e hospedagem |
| 4 · Operação | A fazer | feedback, certificados e sorteio, LGPD do titular, relatórios, teste de carga e backups, dados de demonstração, acessibilidade e e2e dos fluxos críticos |
| 5 · App mobile | Em andamento | app Flutter para participante, palestrante e equipe de check-in (M01–M23); base pronta (M01) |

## Front-admin

Nuxt a partir do [`base_nuxt_modular`](https://github.com/tecrodrigocastro/base_nuxt_modular), módulo por domínio. Telas prontas: login (senha e GitHub), recuperação de senha, convite, comunidades e nova edição, visão geral, equipe, identidade, configurações, meu perfil, CFP (configuração e avaliação), palestrantes, ingressos, pedidos e reembolso, pagamentos, participantes, patrocinadores, fornecedores, finanças, tarefas e comunicação. Faltam a grade (A13) e o check-in no desktop e no celular (A18), no Marco 3.

## Front-public

Nuxt SSR, a edição e o tema resolvidos pelo host (ADR 0008), com modo site próprio. Prontos: formulário do CFP e área do palestrante (P01, P03), checkout com Pix e cartão (P04, P05) e conta do participante com ingressos que abrem sem internet, crachá, transferência e pedidos (P06). Faltam o site template da edição, o checkout embutível, SEO, termos e LGPD, descadastro e viagem do palestrante (Marco 3). 

## App mobile

Flutter, a partir do [`base_clean_arch_bloc`](https://github.com/tecrodrigocastro/base_clean_arch_bloc), um app só para os três papéis, com a identidade de cada edição vinda da API ([design](https://claude.ai/artifact/3yAeWXmnxFSdActqPdqt7K#page-dcb81aee8d83)). A compra de ingresso continua no site; o app mostra, transfere e usa o ingresso. Decisões de 28/11/2026: transferência de ingresso passa a ter aceite de quem recebe (B54, também no site com a P12); no celular, o check-in da equipe é feito pelo app, e o painel (A18) fica com a mesa de credenciamento e o PWA como plano B. O que o app precisa do backend está no Marco 3 (B50–B56) e nas issues de programação, check-in, push e LGPD.

## Decisões de arquitetura — ver histórico da conversa de planejamento (3 nov 2026)

- Dinheiro: cada edição usa as credenciais do próprio gateway; a plataforma não recebe nem repassa.
- Auth: Sanctum, token Bearer, um fluxo só para admin e app.
- Filas: Redis + Horizon.
- Sem Filament: o admin é o Nuxt em `front-admin/`, consumindo a API do backend.

## Decisões em aberto

1. Controlador dos dados (LGPD) — comunidade controladora, PHPeste operador (a validar com advogado antes das vendas).
2. Um Nuxt ou dois — decidido: dois (`front-admin` SPA, `front-public` SSR, ADR 0008).
3. Gateway inicial — decidido: Asaas e Mercado Pago, os dois prontos; a comunidade escolhe o dela ([`guia-gateways.md`](guia-gateways.md), ADR 0009).
4. Domínio e subdomínios das edições — decidido e pronto: subdomínio padrão com opção de domínio próprio (verificação no painel, `docs/deploy/dominios-e-tls.md`). A comunidade também pode manter o próprio site e usar a plataforma só para a área de palestrante e participante, num subdomínio dela — ver [`site-proprio.md`](site-proprio.md).
5. Migração da suite da Rapadura (despesas e kanban) — conversar com a comunidade.
6. Dados históricos de edições anteriores — começar do zero.
