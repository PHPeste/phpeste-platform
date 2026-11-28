import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/app/features/auth/presentation/pages/login_page.dart';
import 'package:phpeste_app/src/app/features/base/presentation/pages/base_page.dart';
import 'package:phpeste_app/src/app/features/home/presentation/pages/home_page.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/services/session_service.dart';

class AppRoutes {
  static const String login = '/entrar';
  static const String home = '/inicio';
  static const String profile = '/perfil';

  static const Set<String> public = {login};
}

String? sessionRedirect({required bool signedIn, required Uri location}) {
  final isPublic = AppRoutes.public.contains(location.path);

  if (!signedIn && !isPublic) {
    final destination = location.toString();
    return Uri(path: AppRoutes.login, queryParameters: destination == '/' ? null : {'de': destination}).toString();
  }

  if (signedIn && location.path == AppRoutes.login) {
    final destination = location.queryParameters['de'];
    return destination != null && destination.startsWith('/') && !destination.startsWith('//') ? destination : AppRoutes.home;
  }

  return null;
}

GoRouter createRouter(SessionService session) => GoRouter(
      initialLocation: AppRoutes.home,
      refreshListenable: session.signedIn,
      redirect: (context, state) => sessionRedirect(signedIn: session.signedIn.value, location: state.uri),
      routes: [
        StatefulShellRoute.indexedStack(
          builder: (context, state, shell) => BasePage(shell: shell),
          branches: [
            StatefulShellBranch(
              routes: [
                GoRoute(path: AppRoutes.home, name: 'home', builder: (context, state) => const HomePage()),
              ],
            ),
            StatefulShellBranch(
              routes: [
                GoRoute(path: AppRoutes.profile, name: 'profile', builder: (context, state) => const Placeholder()),
              ],
            ),
          ],
        ),
        GoRoute(path: '/', redirect: (context, state) => AppRoutes.home),
        GoRoute(path: AppRoutes.login, name: 'login', builder: (context, state) => const LoginPage()),
      ],
    );

final GoRouter router = createRouter(injector<SessionService>());
