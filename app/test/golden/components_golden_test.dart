@Tags(['golden'])
library;

import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/app/features/component_catalog/presentation/pages/component_catalog_page.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';
import 'package:phpeste_app/src/core/ui/ph_app_bar.dart';
import 'package:phpeste_app/src/core/ui/ph_button.dart';
import 'package:phpeste_app/src/core/ui/ph_states.dart';

Future<void> screen(WidgetTester tester, Widget child, {PhColors colors = PhColors.sertao, Size size = const Size(390, 844)}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(MaterialApp(debugShowCheckedModeBanner: false, theme: PhTheme.build(colors), home: child));
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  testWidgets('catálogo no tema Sertão', (tester) async {
    await screen(tester, const ComponentCatalogPage(), size: const Size(390, 1900));
    await expectLater(find.byType(ComponentCatalogPage), matchesGoldenFile('goldens/catalogo_sertao.png'));
  });

  testWidgets('catálogo com o tema de uma edição', (tester) async {
    await screen(tester, const ComponentCatalogPage(), size: const Size(390, 1900));
    await tester.tap(find.byType(Switch));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await expectLater(find.byType(ComponentCatalogPage), matchesGoldenFile('goldens/catalogo_edicao.png'));
  });

  testWidgets('estados: sem ingresso, offline e erro', (tester) async {
    await screen(
      tester,
      Scaffold(
        appBar: const PhAppBar(title: 'Meu ingresso'),
        body: Column(children: [
          const PhOfflineBanner(),
          Expanded(
            child: PhMessageState(
              icon: Icons.confirmation_number_outlined,
              title: 'Você ainda não tem ingresso',
              text: 'Quando a compra for confirmada, o QR aparece aqui. Comprou com outro e-mail?',
              actions: [PhButton(label: 'Entrar com outro e-mail', onPressed: () {})],
            ),
          ),
          SizedBox(height: 300, child: PhErrorState(onRetry: () {})),
        ]),
      ),
    );
    await expectLater(find.byType(Scaffold), matchesGoldenFile('goldens/estados.png'));
  });
}
