part of 'edition_theme_bloc.dart';

sealed class EditionThemeEvent extends Equatable {
  const EditionThemeEvent();

  @override
  List<Object?> get props => [];
}

class EditionThemeRequested extends EditionThemeEvent {
  final String editionId;

  const EditionThemeRequested(this.editionId);

  @override
  List<Object?> get props => [editionId];
}

class EditionThemeCleared extends EditionThemeEvent {
  const EditionThemeCleared();
}
