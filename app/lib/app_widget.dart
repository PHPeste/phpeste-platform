import 'package:phpeste_app/routes.dart';
import 'package:phpeste_app/src/app/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/config/app_config.dart';
import 'package:material_ui/material_ui.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class AppWidget extends StatelessWidget {
  const AppWidget({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiBlocProvider(
      providers: [
        BlocProvider(
          create: (context) => injector<AuthBloc>(),
        ),
      ],
      child: MaterialApp.router(
        title: 'PHPeste',
        debugShowCheckedModeBanner: !AppConfig.isProduction,
        theme: ThemeData(
          colorScheme: ColorScheme.fromSeed(seedColor: Colors.deepPurple),
          useMaterial3: true,
        ),
        routerConfig: router,
      ),
    );
  }
}
