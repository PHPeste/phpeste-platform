import 'package:flutter_test/flutter_test.dart';
import 'package:phpeste_app/app_widget.dart';
import 'package:phpeste_app/src/app/features/auth/presentation/texts/auth_texts.dart';
import 'package:phpeste_app/src/app/features/home/presentation/texts/home_texts.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/services/session_service.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  testWidgets('sem sessão abre em Entrar e, ao entrar, vai sozinho para o Início', (tester) async {
    SharedPreferences.setMockInitialValues({});
    setupDependencyInjector(useMocks: true);
    final session = injector<SessionService>();
    await session.restore();

    await tester.pumpWidget(const AppWidget());
    await tester.pumpAndSettle();
    expect(find.text(AuthTexts.email), findsOneWidget);

    await tester.runAsync(() => session.saveSession('tok'));
    await tester.pumpAndSettle();
    expect(find.text(HomeTexts.title), findsWidgets);
    expect(find.text(AuthTexts.email), findsNothing);
  });
}
