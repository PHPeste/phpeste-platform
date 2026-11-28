import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/cache/cache.dart';

class SessionService {
  static const String _tokenKey = 'token';

  final ICache _cache;

  final ValueNotifier<bool> signedIn = ValueNotifier(false);

  SessionService({required ICache cache}) : _cache = cache;

  Future<void> restore() async {
    signedIn.value = await getToken() != null;
  }

  Future<bool> saveSession(String value) async {
    final saved = await _cache.setData(params: CacheParams(key: _tokenKey, value: value));
    signedIn.value = saved;
    return saved;
  }

  Future<String?> getToken() async {
    final response = await _cache.getData(_tokenKey);

    if (response == null || response is! String || response.isEmpty) {
      return null;
    }

    return response;
  }

  Future<bool> removeToken() async {
    final removed = await _cache.removeData(_tokenKey);
    signedIn.value = false;
    return removed;
  }
}
