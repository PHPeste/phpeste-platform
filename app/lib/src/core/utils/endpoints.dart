import 'package:phpeste_app/src/core/config/app_config.dart';

class Endpoints {
  static const String baseUrl = AppConfig.apiUrl;
  static const String login = '$baseUrl/auth/login';
  static const String register = '$baseUrl/auth/register';
}
