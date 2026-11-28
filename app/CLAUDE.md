# app

App Flutter do PHPeste para quem vive a edição: participante, palestrante e equipe de check-in. Um app só, com a identidade de cada edição vinda da API de Branding. Parte do template [`base_clean_arch_bloc`](https://github.com/tecrodrigocastro/base_clean_arch_bloc) (clean architecture com BLoC), como o painel e o site partem do `base_nuxt_modular`. Fala HTTP com `../backend` (`/api/v1`, token Bearer), nunca toca banco.

Design: [claude.ai/artifact/3yAeWXmnxFSdActqPdqt7K](https://claude.ai/artifact/3yAeWXmnxFSdActqPdqt7K#page-dcb81aee8d83). Ler a prancheta da tela (`project/<Tela>.dc.html`) antes de cada issue com UI. Telas em 390 × 844. Issues no milestone "Marco 5 · App mobile" (M01–M23).

As regras de camadas, nomes e SOLID do template estão em `.claude/rules/` e valem aqui. A skill `new-feature` (`.claude/skills/new-feature/`) gera uma feature inteira pelo brick do Mason (`mason get`, depois `mason make feature`); prefira ela a escrever uma feature à mão.

## Stack

- Estado: `flutter_bloc` + `equatable`
- Injeção: `get_it` (`lib/src/core/DI/dependency_injector.dart`)
- Rotas: `go_router` (`lib/routes.dart`)
- HTTP: `dio`, atrás de `lib/src/core/client_http/` (feature nunca importa `dio`)
- Erros: `result_dart` (`Success`/`Failure`, nada de exceção atravessando camada)
- Validação: `lucid_validation`
- Armazenamento local: `shared_preferences`, atrás de `lib/src/core/cache/`
- Widgets: `material_ui` (o pacote oficial que substitui `package:flutter/material.dart`, obsoleto desde o Flutter 3.47). Nunca importe `flutter/material.dart`

## Estrutura

```
lib/
├── main.dart              # binding (Marionette em debug), DI, sessão restaurada antes do runApp
├── app_widget.dart
├── routes.dart            # AppRoutes, sessionRedirect, router
└── src/
    ├── app/features/{feature}/
    │   ├── domain/        # entidades, dtos, interfaces de repositório, usecases, validators; Dart puro
    │   ├── data/          # models (estendem entidades), datasources, repositórios
    │   ├── infrastructure/ # interceptors e serviços da feature
    │   └── presentation/
    │       ├── bloc/
    │       ├── pages/
    │       ├── widgets/
    │       └── texts/     # {feature}_texts.dart: textos da tela em pt-BR
    └── core/              # config, DI, cache, client_http, errors, extensions, services, utils
```

`auth` é a feature de referência (todas as camadas e testes). `home` e `base` (barra inferior) são só apresentação.

## Ambientes

| Flavor | Configuração | Android | iOS |
| --- | --- | --- | --- |
| `dev` | `env/dev.json` (API em `10.0.2.2:8000`, o `localhost` do emulador Android; logs da API) | `dev.phpeste.app.dev`, "PHPeste Dev" | `dev.phpeste.app` |
| `prod` | `env/prod.json` (`https://api.phpeste.dev/api/v1`) | `dev.phpeste.app`, "PHPeste" | `dev.phpeste.app` |

`AppConfig` (`lib/src/core/config/app_config.dart`) lê `FLAVOR`, `API_URL`, `USE_MOCKS` e `LOG_API` do `--dart-define-from-file`. Sem o arquivo, a API é `http://localhost:8000/api/v1`. `USE_MOCKS=true` usa o login simulado do template (`teste@teste.com`, `123456Aa!`). No iOS o identificador ainda é um só: os esquemas por flavor dependem do Xcode e entram na publicação (M22).

## Rotas e sessão

`SessionService.signedIn` (`ValueNotifier`) é restaurado do armazenamento antes do `runApp` e o `GoRouter` observa ele (`refreshListenable`). `sessionRedirect` manda rota privada sem sessão para `/entrar?de=<destino>` e, com sessão, devolve para o destino (só caminho interno) ou para `/inicio`. Por isso tela nenhuma navega depois de entrar ou sair: basta salvar ou remover o token. Rota nova entra em `AppRoutes`; rota aberta sem sessão entra em `AppRoutes.public`. Os links que abrem o app (B56, M21) chegam por esse mesmo roteador.

## Comandos

```bash
flutter pub get
flutter analyze
flutter test
flutter test test/unit/core/routes_test.dart      # um arquivo
flutter run --flavor dev --dart-define-from-file=env/dev.json
flutter build apk --flavor prod --dart-define-from-file=env/prod.json
flutter build ios --no-codesign --dart-define-from-file=env/prod.json
```

Flutter fixado em 3.50.0 (`.fvmrc` e CI). O CI (`../.github/workflows/app-ci.yml`) roda análise, testes e os builds de Android (dev e prod) e iOS sem assinatura em todo PR que mexe em `app/`.

Depois do `flutter run` em debug, o Marionette MCP (`MarionetteBinding` em `lib/main.dart`) deixa um agente conectado ao `ws://` impresso tocar, digitar, tirar print e ler logs, para conferir uma mudança de tela no app rodando.

## Regras

- Dependência: `presentation -> domain <- data <- infrastructure`. `domain/` não importa Flutter, `dio` nem outra camada.
- Repositório e usecase devolvem `AsyncResult<T>`; só `data/` captura exceção e converte em `BaseException` (`lib/src/core/errors/`).
- Entidades imutáveis (`final`, `const`, `Equatable`); DTOs são classes mutáveis com `set{Campo}()` e `empty()`, como `LoginParams`.
- Texto que a pessoa lê fica em `presentation/texts/{feature}_texts.dart`, nunca solto na página.
- Alvo de toque de 44 px ou mais e estado nunca só por cor (design, prancheta 01).
- Dependência nova é proposta no PR e aprovada antes de entrar.
- Tela nova ou mudança de como o app fala com a API atualiza `../docs/arquitetura/` no mesmo PR.
