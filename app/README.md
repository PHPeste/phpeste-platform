# app

App mobile da [PHPeste Platform](../README.md) para participante, palestrante e equipe de check-in. Flutter, clean architecture com BLoC, a partir do template [`base_clean_arch_bloc`](https://github.com/tecrodrigocastro/base_clean_arch_bloc). Ver [`CLAUDE.md`](CLAUDE.md).

> **Status:** base pronta (M01): template adaptado, ambientes `dev` e `prod`, rotas com guarda de sessão e CI. As telas do design entram nas issues M02–M23 do milestone "Marco 5 · App mobile".

```bash
flutter pub get
flutter run --flavor dev --dart-define-from-file=env/dev.json   # API local em 10.0.2.2:8000 (emulador Android)
flutter analyze
flutter test
```

No simulador iOS, a API local é `localhost`, que já é o padrão sem arquivo de ambiente: `flutter run` basta.

Design das telas: [claude.ai/artifact/3yAeWXmnxFSdActqPdqt7K](https://claude.ai/artifact/3yAeWXmnxFSdActqPdqt7K#page-dcb81aee8d83).
