import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:mocktail/mocktail.dart';
import 'package:phpeste_app/src/app/features/edition_theme/data/datasources/edition_branding_local_datasource.dart';
import 'package:phpeste_app/src/app/features/edition_theme/data/datasources/edition_branding_remote_datasource.dart';
import 'package:phpeste_app/src/app/features/edition_theme/data/models/edition_branding_model.dart';
import 'package:phpeste_app/src/app/features/edition_theme/data/repositories/edition_branding_repository_impl.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/entities/edition_branding_entity.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/repositories/edition_branding_repository_interface.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/usecases/get_edition_branding_usecase.dart';
import 'package:phpeste_app/src/app/features/edition_theme/presentation/bloc/edition_theme_bloc.dart';
import 'package:phpeste_app/src/core/cache/cache.dart';
import 'package:phpeste_app/src/core/client_http/client_http.dart';
import 'package:phpeste_app/src/core/errors/errors.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:result_dart/result_dart.dart';

class MockRemote extends Mock implements EditionBrandingRemoteDatasource {}

class MockRepository extends Mock implements IEditionBrandingRepository {}

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

Map<String, dynamic> payload({Map<String, dynamic>? colors, Map<String, dynamic>? logos}) => {
      'data': {
        'edition_id': 'ed-1',
        'theme': {
          'colors': colors ?? {'brand': '#1F4E79', 'on_brand': '#FFFFFF', 'accent': '#7FC8C2', 'on_accent': '#10263A', 'support': '#2E6B5E', 'ink': '#000000'},
          'fonts': {'heading': 'Inter', 'body': 'Inter'},
          'radius': 6,
          'logos': logos ?? {'primary': 'https://cdn.exemplo.dev/logo.png', 'dark': null, 'favicon': null, 'app_icon': null},
        },
        'published_at': '2027-01-10T12:00:00Z',
      },
    };

RestClientResponse response(int status, dynamic data) => RestClientResponse(request: RestClientRequest(path: '/theme'), data: data, statusCode: status);

void main() {
  group('EditionBrandingModel', () {
    test('lê marca, destaque, apoio e logo do tema publicado', () {
      final model = EditionBrandingModel.fromApi(payload());
      expect(model.editionId, 'ed-1');
      expect(model.brand, 0xFF1F4E79);
      expect(model.onAccent, 0xFF10263A);
      expect(model.support, 0xFF2E6B5E);
      expect(model.logoUrl, 'https://cdn.exemplo.dev/logo.png');
    });

    test('ignora cor fora de #RRGGBB e logo sem https', () {
      final model = EditionBrandingModel.fromApi(payload(colors: {'brand': 'red;}', 'accent': '#FFF'}, logos: {'primary': 'javascript:alert(1)'}));
      expect(model.brand, isNull);
      expect(model.accent, isNull);
      expect(model.logoUrl, isNull);
    });

    test('ida e volta pelo armazenamento local', () {
      final model = EditionBrandingModel.fromApi(payload());
      expect(EditionBrandingModel.fromJson(model.toJson()), model);
    });
  });

  group('EditionBrandingRepositoryImpl', () {
    late MockRemote remote;
    late MemoryCache cache;
    late EditionBrandingRepositoryImpl repository;

    setUp(() {
      remote = MockRemote();
      cache = MemoryCache();
      repository = EditionBrandingRepositoryImpl(remote: remote, local: EditionBrandingLocalDatasource(cache: cache));
    });

    test('busca na API e guarda para abrir sem internet', () async {
      when(() => remote.theme('ed-1')).thenAnswer((_) async => response(200, payload()));

      final result = await repository.branding('ed-1');

      expect(result.getOrNull()?.brand, 0xFF1F4E79);
      expect(cache.data['edition_branding.ed-1'], isA<String>());
    });

    test('sem internet, devolve o tema guardado', () async {
      when(() => remote.theme('ed-1')).thenAnswer((_) async => response(200, payload()));
      await repository.branding('ed-1');
      when(() => remote.theme('ed-1')).thenThrow(RestClientException(message: 'offline', error: null));

      final result = await repository.branding('ed-1');

      expect(result.getOrNull()?.logoUrl, 'https://cdn.exemplo.dev/logo.png');
    });

    test('edição não publicada e nada guardado é falha', () async {
      when(() => remote.theme('ed-2')).thenAnswer((_) async => response(404, {'code': 'not_found'}));

      final result = await repository.branding('ed-2');

      expect(result.exceptionOrNull(), isA<NotFoundException>());
    });
  });

  group('EditionThemeBloc', () {
    late MockRepository repository;

    setUp(() => repository = MockRepository());

    blocTest<EditionThemeBloc, EditionThemeState>(
      'aplica as cores legíveis da edição',
      build: () {
        when(() => repository.branding('ed-1')).thenAnswer((_) async => Success(EditionBrandingModel.fromApi(payload())));
        return EditionThemeBloc(getBranding: GetEditionBrandingUsecase(repository: repository));
      },
      act: (bloc) => bloc.add(const EditionThemeRequested('ed-1')),
      verify: (bloc) {
        expect(bloc.state.editionId, 'ed-1');
        expect(bloc.state.colors.brand, const Color(0xFF1F4E79));
        expect(bloc.state.colors.ink, PhColors.sertao.ink);
        expect(bloc.state.logoUrl, 'https://cdn.exemplo.dev/logo.png');
      },
    );

    blocTest<EditionThemeBloc, EditionThemeState>(
      'sem tema da edição fica no Sertão',
      build: () {
        when(() => repository.branding('ed-1')).thenAnswer((_) async => Failure(NotFoundException(message: 'x')));
        return EditionThemeBloc(getBranding: GetEditionBrandingUsecase(repository: repository));
      },
      act: (bloc) => bloc.add(const EditionThemeRequested('ed-1')),
      verify: (bloc) {
        expect(bloc.state.editionId, 'ed-1');
        expect(bloc.state.colors.brand, PhColors.sertao.brand);
        expect(bloc.state.logoUrl, isNull);
      },
    );

    blocTest<EditionThemeBloc, EditionThemeState>(
      'trocar de conta volta ao Sertão',
      build: () => EditionThemeBloc(getBranding: GetEditionBrandingUsecase(repository: repository)),
      seed: () => EditionThemeBloc.stateFor(const EditionBrandingEntity(editionId: 'ed-1', brand: 0xFF1F4E79, onBrand: 0xFFFFFFFF)),
      act: (bloc) => bloc.add(const EditionThemeCleared()),
      expect: () => [const EditionThemeState()],
    );
  });
}
