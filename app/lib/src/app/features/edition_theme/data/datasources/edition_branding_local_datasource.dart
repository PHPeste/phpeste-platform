import 'package:phpeste_app/src/app/features/edition_theme/data/models/edition_branding_model.dart';
import 'package:phpeste_app/src/core/cache/cache.dart';

class EditionBrandingLocalDatasource {
  final ICache _cache;

  EditionBrandingLocalDatasource({required ICache cache}) : _cache = cache;

  String _key(String editionId) => 'edition_branding.$editionId';

  Future<void> save(EditionBrandingModel model) => _cache.setData(params: CacheParams(key: _key(model.editionId), value: model.toJson()));

  Future<EditionBrandingModel?> read(String editionId) async {
    final value = await _cache.getData(_key(editionId));
    if (value is! String || value.isEmpty) return null;
    try {
      return EditionBrandingModel.fromJson(value);
    } catch (_) {
      return null;
    }
  }
}
