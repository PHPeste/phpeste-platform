part of 'edition_theme_bloc.dart';

class EditionThemeState extends Equatable {
  final String? editionId;
  final PhColors colors;
  final String? logoUrl;

  const EditionThemeState({this.editionId, this.colors = PhColors.sertao, this.logoUrl});

  @override
  List<Object?> get props => [editionId, colors, logoUrl];
}
