import 'package:flutter_test/flutter_test.dart';
import 'package:phpeste_app/routes.dart';

void main() {
  group('sessionRedirect', () {
    test('sem sessão, rota privada vai para Entrar guardando o destino', () {
      expect(sessionRedirect(signedIn: false, location: Uri.parse('/perfil')), '/entrar?de=%2Fperfil');
      expect(sessionRedirect(signedIn: false, location: Uri.parse('/inicio?aba=2')), '/entrar?de=%2Finicio%3Faba%3D2');
    });

    test('sem sessão, Entrar não redireciona', () {
      expect(sessionRedirect(signedIn: false, location: Uri.parse('/entrar')), isNull);
    });

    test('com sessão, Entrar volta para o destino guardado ou para o Início', () {
      expect(sessionRedirect(signedIn: true, location: Uri.parse('/entrar?de=%2Fperfil')), '/perfil');
      expect(sessionRedirect(signedIn: true, location: Uri.parse('/entrar')), AppRoutes.home);
    });

    test('destino de fora do app é ignorado', () {
      expect(sessionRedirect(signedIn: true, location: Uri.parse('/entrar?de=https://evil.dev')), AppRoutes.home);
      expect(sessionRedirect(signedIn: true, location: Uri.parse('/entrar?de=//evil.dev')), AppRoutes.home);
    });

    test('com sessão, rota privada segue', () {
      expect(sessionRedirect(signedIn: true, location: Uri.parse('/inicio')), isNull);
    });
  });
}
