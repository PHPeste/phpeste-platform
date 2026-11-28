import 'package:flutter_test/flutter_test.dart';
import 'package:phpeste_app/src/core/cache/cache.dart';
import 'package:phpeste_app/src/core/services/session_service.dart';

class MemoryCache implements ICache {
  final Map<String, dynamic> data = {};

  @override
  Future<bool> clean() async {
    data.clear();
    return true;
  }

  @override
  Future<dynamic> getData(String key) async => data[key];

  @override
  Future<bool> removeData(String key) async {
    data.remove(key);
    return true;
  }

  @override
  Future<bool> setData({required CacheParams params}) async {
    data[params.key] = params.value;
    return true;
  }
}

void main() {
  group('SessionService', () {
    test('restaura a sessão guardada e avisa quem observa', () async {
      final cache = MemoryCache()..data['token'] = 'tok';
      final session = SessionService(cache: cache);
      final changes = <bool>[];
      session.signedIn.addListener(() => changes.add(session.signedIn.value));

      await session.restore();
      await session.removeToken();
      await session.saveSession('novo');

      expect(changes, [true, false, true]);
      expect(await session.getToken(), 'novo');
    });

    test('token vazio não conta como sessão', () async {
      final session = SessionService(cache: MemoryCache()..data['token'] = '');
      await session.restore();
      expect(session.signedIn.value, isFalse);
      expect(await session.getToken(), isNull);
    });
  });
}
