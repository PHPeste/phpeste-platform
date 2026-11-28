class AppConfig {
  static const String flavor = String.fromEnvironment('FLAVOR', defaultValue: 'dev');
  static const String apiUrl = String.fromEnvironment('API_URL', defaultValue: 'http://localhost:8000/api/v1');
  static const bool useMocks = bool.fromEnvironment('USE_MOCKS');
  static const bool logApi = bool.fromEnvironment('LOG_API');

  static bool get isProduction => flavor == 'prod';
}
