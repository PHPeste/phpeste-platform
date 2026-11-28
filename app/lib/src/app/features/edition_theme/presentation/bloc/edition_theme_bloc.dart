import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/entities/edition_branding_entity.dart';
import 'package:phpeste_app/src/app/features/edition_theme/domain/usecases/get_edition_branding_usecase.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';

part 'edition_theme_event.dart';
part 'edition_theme_state.dart';

class EditionThemeBloc extends Bloc<EditionThemeEvent, EditionThemeState> {
  final GetEditionBrandingUsecase _getBranding;

  EditionThemeBloc({required GetEditionBrandingUsecase getBranding})
      : _getBranding = getBranding,
        super(const EditionThemeState()) {
    on<EditionThemeRequested>((event, emit) async {
      final result = await _getBranding(event.editionId);
      result.fold(
        (branding) => emit(stateFor(branding)),
        (_) => emit(EditionThemeState(editionId: event.editionId)),
      );
    });
    on<EditionThemeCleared>((event, emit) => emit(const EditionThemeState()));
  }

  static EditionThemeState stateFor(EditionBrandingEntity branding) {
    Color? color(int? value) => value == null ? null : Color(value);
    return EditionThemeState(
      editionId: branding.editionId,
      colors: PhColors.sertao.withEdition(
        brand: color(branding.brand),
        onBrand: color(branding.onBrand),
        accent: color(branding.accent),
        onAccent: color(branding.onAccent),
        support: color(branding.support),
      ),
      logoUrl: branding.logoUrl,
    );
  }
}
