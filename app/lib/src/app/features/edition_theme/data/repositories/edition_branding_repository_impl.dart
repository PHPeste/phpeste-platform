import 'package:phpeste_app/src/app/features/edition_theme/data/datasources/edition_branding_local_datasource.dart';
import 'package:phpeste_app/src/app/features/edition_theme/data/datasources/edition_branding_remote_datasource.dart';
import 'package:phpeste_app/src/app/features/edition_theme/data/models/edition_branding_model.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/entities/edition_branding_entity.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/repositories/edition_branding_repository_interface.dart';
import 'package:phpeste_app/src/core/client_http/client_http.dart';
import 'package:phpeste_app/src/core/errors/errors.dart';
import 'package:result_dart/result_dart.dart';

class EditionBrandingRepositoryImpl implements IEditionBrandingRepository {
  final EditionBrandingRemoteDatasource _remote;
  final EditionBrandingLocalDatasource _local;

  EditionBrandingRepositoryImpl({required EditionBrandingRemoteDatasource remote, required EditionBrandingLocalDatasource local})
      : _remote = remote,
        _local = local;

  @override
  AsyncResult<EditionBrandingEntity> branding(String editionId) async {
    try {
      final response = await _remote.theme(editionId);
      if (response.statusCode == 200 && response.data is Map<String, dynamic>) {
        final model = EditionBrandingModel.fromApi(response.data as Map<String, dynamic>);
        await _local.save(model);
        return Success(model);
      }
      return await _saved(editionId, NotFoundException(message: 'Tema da edição não encontrado.', statusCode: response.statusCode));
    } on RestClientException catch (e) {
      return await _saved(editionId, DefaultException(message: e.message, statusCode: e.statusCode));
    } catch (e) {
      return await _saved(editionId, DefaultException(message: 'Erro inesperado: $e'));
    }
  }

  AsyncResult<EditionBrandingEntity> _saved(String editionId, BaseException error) async {
    final saved = await _local.read(editionId);
    return saved != null ? Success(saved) : Failure(error);
  }
}
