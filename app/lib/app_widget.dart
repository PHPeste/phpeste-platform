import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/routes.dart';
import 'package:phpeste_app/src/app/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:phpeste_app/src/app/features/edition_theme/presentation/bloc/edition_theme_bloc.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/config/app_config.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';

class AppWidget extends StatelessWidget {
  const AppWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider(create: (context) => injector<AuthBloc>()),
        BlocProvider.value(value: injector<EditionThemeBloc>()),
      ],
      child: BlocBuilder<EditionThemeBloc, EditionThemeState>(
        buildWhen: (previous, current) => previous.colors != current.colors,
        builder: (context, state) => MaterialApp.router(
          title: 'PHPeste',
          debugShowCheckedModeBanner: !AppConfig.isProduction,
          theme: PhTheme.build(state.colors),
          routerConfig: router,
        ),
      ),
    );
  }
}
