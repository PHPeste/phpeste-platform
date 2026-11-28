import 'package:phpeste_app/app_widget.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/config/app_config.dart';
import 'package:phpeste_app/src/core/services/session_service.dart';
import 'package:flutter/foundation.dart';
import 'package:material_ui/material_ui.dart';
import 'package:intl/intl.dart';
import 'package:logger/logger.dart';
import 'package:marionette_flutter/marionette_flutter.dart';
import 'package:marionette_logger/marionette_logger.dart';

Future<void> main() async {
  /// Coleta os logs emitidos via `Logger` para que o marionette_mcp
  /// (agente de IA conectado ao app em runtime) consiga lê-los com `get_logs`.
  final logCollector = LoggerLogCollector();

  /// `MarionetteBinding` só roda em debug - em release o app usa o binding
  /// padrão do Flutter normalmente. Veja https://github.com/leancodepl/marionette_mcp
  if (kDebugMode) {
    MarionetteBinding.ensureInitialized(
      MarionetteConfiguration(logCollector: logCollector),
    );
  } else {
    WidgetsFlutterBinding.ensureInitialized();
  }

  Intl.defaultLocale = 'pt_BR';

  final logger = Logger(output: MultiOutput([ConsoleOutput(), logCollector]));

  setupDependencyInjector(
    loggerApi: AppConfig.logApi,
    useMocks: AppConfig.useMocks,
    logger: logger,
  );
  await injector<SessionService>().restore();
  runApp(const AppWidget());
}
