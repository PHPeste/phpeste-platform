import 'package:phpeste_app/src/app/features/edition_theme/domain/entities/edition_branding_entity.dart';
import 'package:result_dart/result_dart.dart';

abstract interface class IEditionBrandingRepository {
  AsyncResult<EditionBrandingEntity> branding(String editionId);
}
