import 'package:phpeste_app/src/app/features/edition_theme/domain/entities/edition_branding_entity.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/repositories/edition_branding_repository_interface.dart';
import 'package:phpeste_app/src/core/interfaces/usecase_interface.dart';
import 'package:result_dart/result_dart.dart';

class GetEditionBrandingUsecase implements UseCase<EditionBrandingEntity, String> {
  final IEditionBrandingRepository _repository;

  GetEditionBrandingUsecase({required IEditionBrandingRepository repository}) : _repository = repository;

  @override
  AsyncResult<EditionBrandingEntity> call(String editionId) => _repository.branding(editionId);
}
