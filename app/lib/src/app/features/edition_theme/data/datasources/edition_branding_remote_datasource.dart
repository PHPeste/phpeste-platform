import 'package:phpeste_app/src/core/client_http/client_http.dart';
import 'package:phpeste_app/src/core/utils/endpoints.dart';

class EditionBrandingRemoteDatasource {
  final IRestClient _restClient;

  EditionBrandingRemoteDatasource({required IRestClient restClient}) : _restClient = restClient;

  Future<RestClientResponse> theme(String editionId) => _restClient.get(RestClientRequest(path: Endpoints.editionTheme(editionId)));
}
