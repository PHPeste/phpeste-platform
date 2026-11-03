# PHPeste Platform

Plataforma única para organizar o [PHPeste](https://phpeste.dev), o evento anual, comunitário e itinerante de PHP do Nordeste. Cada ano a comunidade anfitriã abre uma nova edição na plataforma, configura a identidade visual e cuida de fornecedores, CFP, programação, ingressos e check-in sem reinventar ferramentas.

A primeira edição a usar a plataforma é o PHPeste 2027, em João Pessoa (PB), organizado pela comunidade PHP-PB.

> **Status:** em planejamento (Marco 0). Ainda não há código executável; as seções "Como rodar" serão preenchidas conforme cada parte entrar no repositório.

## O que a plataforma cobre

- **Comunidades e edições:** cada edição tem equipe, papéis, status e tema próprios.
- **Identidade configurável:** cores, logo, fontes e textos por edição, aplicados ao site, ao admin e ao app sem novo deploy.
- **CFP:** submissão, avaliação por curadores, decisão em lote e confirmação dos palestrantes.
- **Programação:** salas, sessões e grade com validação de conflitos.
- **Ingressos e checkout:** tipos, lotes, cupons, Pix, cartão e boleto, com webhooks idempotentes.
- **Check-in:** leitura de QR no credenciamento.
- **App do participante:** programação, agenda pessoal e ingresso com QR.
- **Fornecedores, patrocinadores, despesas e tarefas** da organização.

## Princípios

- **Uma instalação, um banco.** Tudo que pertence a uma edição carrega `edition_id`; usuários, perfis de palestrante e fornecedores são globais e preservam o histórico entre edições.
- **A plataforma não toca no dinheiro.** Cada comunidade conecta as chaves do próprio gateway e recebe direto na sua conta.
- **API primeiro.** Admin, site e app consomem a mesma API, e nenhuma regra de negócio vive só em um cliente.
- **Módulos independentes.** Os módulos conversam por eventos de domínio e contratos, nunca por chamadas diretas entre models.

## Stack

| Parte | Tecnologia |
| --- | --- |
| Backend | Laravel 13, PHP 8.4, modularizado com [internachi/modular](https://github.com/InterNACHI/modular) |
| Documentação da API | [Scramble](https://github.com/dedoc/scramble) (OpenAPI) |
| Admin | Nuxt, a partir do [base_nuxt_modular](https://github.com/tecrodrigocastro/base_nuxt_modular) |
| Site público e checkout | Nuxt (SSR) |
| App do participante | Flutter, a partir do [base_clean_arch_bloc](https://github.com/tecrodrigocastro/base_clean_arch_bloc) |
| Autenticação | Laravel Sanctum (token Bearer) |
| Filas e cache | Redis |
| Qualidade | Pint, Larastan, Pest e Playwright |

## Estrutura do repositório

```text
phpeste-platform/
├── infra/          # Docker Compose (dev e produção), CI/CD, proxy, backups
├── docs/           # planejamento, ADRs e guia para comunidades anfitriãs
├── backend/        # Laravel: API, filas, webhooks (módulos em app-modules/)
├── front-admin/    # Nuxt: painel da organização
├── front-public/   # Nuxt: checkout, CFP, conta do participante, site template
└── app/            # Flutter: app do participante
```

## Módulos do backend

Cada módulo vive em `backend/app-modules/<nome>` com namespace `PHPeste\<Nome>`.

| Módulo | Responsabilidade |
| --- | --- |
| Identity | Usuários, login, perfis e papéis por edição |
| Community | Comunidades, edições, equipe e onboarding de nova edição |
| Branding | Tema, domínio e tokens para site e app |
| Cfp | Chamada, trilhas, propostas, avaliação e decisão |
| Program | Salas, sessões, grade e agenda pessoal |
| Ticketing | Tipos, lotes, cupons, pedidos, ingressos e QR |
| Payments | Interface de gateway, drivers (Asaas, Mercado Pago), webhooks e estornos |
| CheckIn | Leitura de QR, credenciamento e equipe |
| Notifications | E-mail transacional e push |
| Sponsors | Cotas, contrapartidas e contratos |
| Suppliers | Cadastro de fornecedores e contratos por edição |
| Finance | Orçamento, despesas e comprovantes |
| Tasks | Kanban da organização por edição |
| Engagement | Feedback de sessão, sorteio e certificados |
| Audit | Trilha de ações sensíveis |

Para criar um módulo novo:

```bash
php artisan make:module <nome>
composer update modules/<nome>
```

## Como rodar

As instruções de cada parte serão adicionadas junto com o código.

- **Backend** (`backend/`): a definir no Marco 0.
- **Admin e site público** (`front-admin/`, `front-public/`): a definir no Marco 0.
- **App** (`app/`): a definir no Marco 3.

## Documentação

Tudo o que não cabe neste README fica em [`docs/`](docs/):

- Planejamento de produto e de backend
- ADRs com o porquê de cada decisão de arquitetura
- Guia para a comunidade anfitriã: criar a edição, conectar o gateway, tematizar e abrir o CFP


## Segurança e privacidade

Credenciais de gateway são criptografadas e nunca devolvidas pela API. Webhooks têm assinatura validada e são idempotentes. Cada edição tem política de privacidade própria, e os dados de uma edição são isolados das demais. Para reportar uma vulnerabilidade, a definir.

## Contribuindo

A definir: guia de contribuição, código de conduta e licença.
