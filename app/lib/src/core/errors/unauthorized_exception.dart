import 'package:phpeste_app/src/core/errors/base_exception.dart';

class UnauthorizedException extends BaseException {
  UnauthorizedException({
    required super.message,
  });
}
