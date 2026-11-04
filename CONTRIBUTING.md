# Contribuindo com a PHPeste Platform

Obrigado por ajudar. Este projeto é feito para o PHPeste e mantido em comunidade — qualquer pessoa pode propor melhorias.

## Antes de abrir uma issue ou PR

- Dá uma olhada no [board do projeto](https://github.com/orgs/PHPeste/projects/2) pra ver o que já está em andamento.
- Pra contexto de produto e arquitetura, leia o [README](README.md) e [`docs/produto.md`](docs/produto.md).
- As decisões de arquitetura ficam registradas em [`docs/adr/`](docs/adr/) — vale conferir antes de propor uma mudança estrutural, pra entender o porquê de uma decisão antes de revisitá-la.

## Rodando o projeto localmente

- **Backend** (`backend/`): `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate`, ou `infra/dev.sh up` pra subir tudo via Docker (MySQL, Redis, Horizon, scheduler, Mailpit).
- **Admin** (`front-admin/`): `npm install && cp .env.example .env && npm run dev`.

Veja a seção "Como rodar" do [README](README.md#como-rodar) para o passo a passo completo.

## Qualidade antes de abrir o PR

Backend:

```bash
cd backend
composer check   # pint + phpstan (nível 8) + rector --dry-run + pest
composer audit
```

Front-admin:

```bash
cd front-admin
npm run lint
npm run typecheck
npm test
npm run build
```

O CI (`.github/workflows/`) roda os mesmos comandos em todo PR — rodar localmente antes evita ida e volta.

## Convenções

- **Backend**: módulo novo é `php artisan make:module <Nome>` seguido de `composer update phpeste/<nome>` — nunca uma pasta solta colada em `app/`. Ver `backend/CLAUDE.md` para a estrutura de pastas de um módulo (`Models/Actions/Data/Events/Listeners/Contracts/Http`) e a regra de isolamento entre módulos.
- **Front-admin**: módulo novo segue o formato de `app/modules/login/` (types → services → composables → components → views). Ver `front-admin/CLAUDE.md` e `front-admin/docs/ARCHITECTURE.md`.
- Commits em português, description curta e no imperativo (ex.: "Adiciona X", "Corrige Y").
- PRs pequenos e focados batem mais rápido do que um PR gigante — quando der, prefira quebrar em commits/PRs por assunto.

## Dúvidas

Abra uma issue ou comente na issue existente — não tem burocracia.
