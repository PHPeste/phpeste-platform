import 'package:phpeste_app/src/core/client_http/client_http.dart';
import 'package:phpeste_app/src/core/errors/errors.dart';

class RestClientException extends BaseException implements RestClientHttpMessage {
  dynamic error;
  RestClientResponse? response;

  RestClientException({
    required super.message,
    super.statusCode,
    super.data,
    required this.error,
    this.response,
    super.stackTracing,
  });
}
