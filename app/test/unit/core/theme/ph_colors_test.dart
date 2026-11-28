import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/contrast.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';

void main() {
  const sertao = PhColors.sertao;

  group('contraste', () {
    test('preto sobre branco é 21:1 e cores iguais são 1:1', () {
      expect(contrastRatio(const Color(0xFF000000), const Color(0xFFFFFFFF)), closeTo(21, 0.01));
      expect(contrastRatio(sertao.brand, sertao.brand), closeTo(1, 0.001));
    });

    test('pares de texto do tema Sertão passam de 4,5:1', () {
      final pairs = [
        (sertao.ink, sertao.bg), (sertao.ink, sertao.surface), (sertao.ink2, sertao.bg), (sertao.ink3, sertao.surface),
        (sertao.onBrand, sertao.brand), (sertao.onAccent, sertao.accent), (sertao.link, sertao.surface),
        (sertao.ok, sertao.okSoft), (sertao.warn, sertao.warnSoft), (sertao.danger, sertao.dangerSoft), (sertao.info, sertao.infoSoft),
      ];
      for (final (foreground, background) in pairs) {
        expect(contrastRatio(foreground, background), greaterThanOrEqualTo(4.5), reason: '$foreground sobre $background');
      }
    });

    test('só aceita cor #RRGGBB', () {
      expect(parseHexColor('#1F4E79'), const Color(0xFF1F4E79));
      expect(parseHexColor('1F4E79'), isNull);
      expect(parseHexColor('#FFF'), isNull);
      expect(parseHexColor('red;}'), isNull);
      expect(parseHexColor(null), isNull);
    });
  });

  group('withEdition', () {
    test('usa marca, destaque e apoio da edição quando são legíveis', () {
      final edition = sertao.withEdition(
        brand: const Color(0xFF1F4E79),
        onBrand: const Color(0xFFFFFFFF),
        accent: const Color(0xFF7FC8C2),
        onAccent: const Color(0xFF10263A),
        support: const Color(0xFF2E6B5E),
      );

      expect(edition.brand, const Color(0xFF1F4E79));
      expect(edition.onBrand, const Color(0xFFFFFFFF));
      expect(edition.accent, const Color(0xFF7FC8C2));
      expect(edition.terra, const Color(0xFF2E6B5E));
      expect(edition.ink, sertao.ink);
      expect(edition.ok, sertao.ok);
      expect(edition.danger, sertao.danger);
    });

    test('marca sem contraste com o texto ou com o fundo cai para o Sertão', () {
      final lowText = sertao.withEdition(brand: const Color(0xFF4FA3E0), onBrand: const Color(0xFFFFFFFF));
      final lowBackground = sertao.withEdition(brand: const Color(0xFFF7EFD9), onBrand: const Color(0xFF3A1607));

      expect(lowText.brand, sertao.brand);
      expect(lowText.onBrand, sertao.onBrand);
      expect(lowBackground.brand, sertao.brand);
    });

    test('destaque sem texto legível cai para o Sertão, sem mexer na marca', () {
      final edition = sertao.withEdition(brand: const Color(0xFF1F4E79), onBrand: const Color(0xFFFFFFFF), accent: const Color(0xFFFFEE58), onAccent: const Color(0xFFFFFFFF));

      expect(edition.brand, const Color(0xFF1F4E79));
      expect(edition.accent, sertao.accent);
    });

    test('sem cores da edição fica igual ao Sertão', () {
      final edition = sertao.withEdition();
      expect(edition.brand, sertao.brand);
      expect(edition.accent, sertao.accent);
      expect(edition.terra, sertao.terra);
    });
  });
}
