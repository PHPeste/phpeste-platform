import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/contrast.dart';

class PhColors extends ThemeExtension<PhColors> {
  static const double textMinimum = 4.5;

  final Color bg;
  final Color surface;
  final Color surface2;
  final Color line;
  final Color ink;
  final Color ink2;
  final Color ink3;
  final Color link;
  final Color brand;
  final Color brandHover;
  final Color onBrand;
  final Color accent;
  final Color accentSoft;
  final Color onAccent;
  final Color terra;
  final Color ok;
  final Color okSoft;
  final Color warn;
  final Color warnSoft;
  final Color danger;
  final Color dangerSoft;
  final Color info;
  final Color infoSoft;

  const PhColors({
    required this.bg,
    required this.surface,
    required this.surface2,
    required this.line,
    required this.ink,
    required this.ink2,
    required this.ink3,
    required this.link,
    required this.brand,
    required this.brandHover,
    required this.onBrand,
    required this.accent,
    required this.accentSoft,
    required this.onAccent,
    required this.terra,
    required this.ok,
    required this.okSoft,
    required this.warn,
    required this.warnSoft,
    required this.danger,
    required this.dangerSoft,
    required this.info,
    required this.infoSoft,
  });

  static const PhColors sertao = PhColors(
    bg: Color(0xFFFBF4E4),
    surface: Color(0xFFFFFBF2),
    surface2: Color(0xFFF5EAD0),
    line: Color(0xFFE4D3AB),
    ink: Color(0xFF3A1607),
    ink2: Color(0xFF6F4A38),
    ink3: Color(0xFF7F5A47),
    link: Color(0xFF9C4220),
    brand: Color(0xFF6B2A12),
    brandHover: Color(0xFF55200C),
    onBrand: Color(0xFFFFF8E8),
    accent: Color(0xFFE8B544),
    accentSoft: Color(0xFFF6E3B0),
    onAccent: Color(0xFF3A1607),
    terra: Color(0xFFB4532A),
    ok: Color(0xFF4B6A24),
    okSoft: Color(0xFFE6EBCF),
    warn: Color(0xFF8A5A00),
    warnSoft: Color(0xFFF8E7B9),
    danger: Color(0xFFA12B1F),
    dangerSoft: Color(0xFFF6D9D2),
    info: Color(0xFF2F5D6B),
    infoSoft: Color(0xFFD9E7E9),
  );

  PhColors withEdition({Color? brand, Color? onBrand, Color? accent, Color? onAccent, Color? support}) {
    final readableBrand = brand != null &&
        onBrand != null &&
        contrastRatio(onBrand, brand) >= textMinimum &&
        contrastRatio(brand, bg) >= 3 &&
        contrastRatio(brand, surface) >= 3;
    final readableAccent = accent != null && onAccent != null && contrastRatio(onAccent, accent) >= textMinimum;
    final readableSupport = support != null && contrastRatio(readableBrand ? onBrand : this.onBrand, support) >= textMinimum;

    return copyWith(
      brand: readableBrand ? brand : null,
      brandHover: readableBrand ? Color.lerp(brand, const Color(0xFF000000), 0.2) : null,
      onBrand: readableBrand ? onBrand : null,
      accent: readableAccent ? accent : null,
      accentSoft: readableAccent ? Color.lerp(accent, surface, 0.6) : null,
      onAccent: readableAccent ? onAccent : null,
      terra: readableSupport ? support : null,
    );
  }

  @override
  PhColors copyWith({
    Color? bg,
    Color? surface,
    Color? surface2,
    Color? line,
    Color? ink,
    Color? ink2,
    Color? ink3,
    Color? link,
    Color? brand,
    Color? brandHover,
    Color? onBrand,
    Color? accent,
    Color? accentSoft,
    Color? onAccent,
    Color? terra,
    Color? ok,
    Color? okSoft,
    Color? warn,
    Color? warnSoft,
    Color? danger,
    Color? dangerSoft,
    Color? info,
    Color? infoSoft,
  }) {
    return PhColors(
      bg: bg ?? this.bg,
      surface: surface ?? this.surface,
      surface2: surface2 ?? this.surface2,
      line: line ?? this.line,
      ink: ink ?? this.ink,
      ink2: ink2 ?? this.ink2,
      ink3: ink3 ?? this.ink3,
      link: link ?? this.link,
      brand: brand ?? this.brand,
      brandHover: brandHover ?? this.brandHover,
      onBrand: onBrand ?? this.onBrand,
      accent: accent ?? this.accent,
      accentSoft: accentSoft ?? this.accentSoft,
      onAccent: onAccent ?? this.onAccent,
      terra: terra ?? this.terra,
      ok: ok ?? this.ok,
      okSoft: okSoft ?? this.okSoft,
      warn: warn ?? this.warn,
      warnSoft: warnSoft ?? this.warnSoft,
      danger: danger ?? this.danger,
      dangerSoft: dangerSoft ?? this.dangerSoft,
      info: info ?? this.info,
      infoSoft: infoSoft ?? this.infoSoft,
    );
  }

  @override
  PhColors lerp(covariant ThemeExtension<PhColors>? other, double t) {
    if (other is! PhColors) return this;
    Color mix(Color a, Color b) => Color.lerp(a, b, t)!;
    return PhColors(
      bg: mix(bg, other.bg),
      surface: mix(surface, other.surface),
      surface2: mix(surface2, other.surface2),
      line: mix(line, other.line),
      ink: mix(ink, other.ink),
      ink2: mix(ink2, other.ink2),
      ink3: mix(ink3, other.ink3),
      link: mix(link, other.link),
      brand: mix(brand, other.brand),
      brandHover: mix(brandHover, other.brandHover),
      onBrand: mix(onBrand, other.onBrand),
      accent: mix(accent, other.accent),
      accentSoft: mix(accentSoft, other.accentSoft),
      onAccent: mix(onAccent, other.onAccent),
      terra: mix(terra, other.terra),
      ok: mix(ok, other.ok),
      okSoft: mix(okSoft, other.okSoft),
      warn: mix(warn, other.warn),
      warnSoft: mix(warnSoft, other.warnSoft),
      danger: mix(danger, other.danger),
      dangerSoft: mix(dangerSoft, other.dangerSoft),
      info: mix(info, other.info),
      infoSoft: mix(infoSoft, other.infoSoft),
    );
  }
}

extension PhColorsContext on BuildContext {
  PhColors get ph => Theme.of(this).extension<PhColors>() ?? PhColors.sertao;
}
