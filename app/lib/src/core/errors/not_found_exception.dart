import 'package:phpeste_app/src/core/errors/errors.dart';

class NotFoundException extends BaseException {
  NotFoundException({
    required super.message,
    super.statusCode = 404,
  });
}
