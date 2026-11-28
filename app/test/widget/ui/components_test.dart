import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';
import 'package:phpeste_app/src/core/ui/ph_app_bar.dart';
import 'package:phpeste_app/src/core/ui/ph_avatar.dart';
import 'package:phpeste_app/src/core/ui/ph_badge.dart';
import 'package:phpeste_app/src/core/ui/ph_bottom_nav.dart';
import 'package:phpeste_app/src/core/ui/ph_button.dart';
import 'package:phpeste_app/src/core/ui/ph_chip.dart';
import 'package:phpeste_app/src/core/ui/ph_states.dart';

Widget wrap(Widget child, {PhColors colors = PhColors.sertao}) => MaterialApp(theme: PhTheme.build(colors), home: Scaffold(body: Center(child: child)));

void expectTouchTarget(WidgetTester tester, Finder finder) {
  final size = tester.getSize(finder);
  expect(size.height, greaterThanOrEqualTo(PhTheme.minTouch), reason: 'altura de ${finder.describeMatch(Plurality.one)}');
  expect(size.width, greaterThanOrEqualTo(PhTheme.minTouch), reason: 'largura de ${finder.describeMatch(Plurality.one)}');
}

const destinations = [
  PhNavDestination(label: 'Início', icon: Icons.home_outlined),
  PhNavDestination(label: 'Programação', icon: Icons.calendar_month_outlined),
  PhNavDestination(label: 'Ingresso', icon: Icons.confirmation_number_outlined),
  PhNavDestination(label: 'Agenda', icon: Icons.bookmark_border_rounded),
  PhNavDestination(label: 'Perfil', icon: Icons.person_outline_rounded),
];

void main() {
  testWidgets('botões, inclusive o pequeno, têm alvo de 44 px', (tester) async {
    await tester.pumpWidget(wrap(Column(mainAxisSize: MainAxisSize.min, children: [
      PhButton(label: 'Entrar', onPressed: () {}),
      PhButton(label: 'Ok', small: true, onPressed: () {}),
      PhButton(label: 'Sair', variant: PhButtonVariant.secondary, onPressed: () {}),
    ])));

    for (final label in ['Entrar', 'Ok', 'Sair']) {
      expectTouchTarget(tester, find.ancestor(of: find.text(label), matching: find.byType(TextButton)));
    }
  });

  testWidgets('botão carregando não dispara de novo e mantém o nome para leitor de tela', (tester) async {
    var taps = 0;
    await tester.pumpWidget(wrap(PhButton(label: 'Enviar', loading: true, onPressed: () => taps++)));

    await tester.tap(find.byType(TextButton));
    expect(taps, 0);
    expect(find.bySemanticsLabel('Enviar'), findsOneWidget);
  });

  testWidgets('chip tem alvo de 44 px e anuncia quando está selecionado', (tester) async {
    final semantics = tester.ensureSemantics();
    var tapped = false;
    await tester.pumpWidget(wrap(PhChip(label: 'Backend', selected: true, onTap: () => tapped = true)));

    expectTouchTarget(tester, find.byType(InkWell));
    expect(tester.getSemantics(find.byType(PhChip)), matchesSemantics(label: 'Backend', isButton: true, isSelected: true, hasSelectedState: true, hasTapAction: true, isFocusable: true, hasFocusAction: true));
    await tester.tap(find.text('Backend'));
    expect(tapped, isTrue);
    semantics.dispose();
  });

  testWidgets('estado do badge sempre vem com texto', (tester) async {
    await tester.pumpWidget(wrap(const Column(mainAxisSize: MainAxisSize.min, children: [
      PhBadge(label: 'Válido', tone: PhTone.ok),
      PhBadge(label: 'Recusado', tone: PhTone.danger, icon: Icons.close),
    ])));

    expect(find.text('Válido'), findsOneWidget);
    expect(find.text('Recusado'), findsOneWidget);
  });

  testWidgets('barra inferior: 5 destinos com 44 px, o atual selecionado e o ingresso em destaque', (tester) async {
    final semantics = tester.ensureSemantics();
    int? selected;
    await tester.pumpWidget(MaterialApp(
      theme: PhTheme.build(PhColors.sertao),
      home: Scaffold(bottomNavigationBar: PhBottomNav(destinations: destinations, currentIndex: 1, highlightedIndex: 2, onSelected: (index) => selected = index)),
    ));

    for (final destination in destinations) {
      expectTouchTarget(tester, find.ancestor(of: find.text(destination.label), matching: find.byType(InkWell)));
    }
    expect(tester.getSemantics(find.bySemanticsLabel('Programação')), matchesSemantics(label: 'Programação', isButton: true, isSelected: true, hasSelectedState: true, hasTapAction: true));

    await tester.tap(find.text('Ingresso'));
    expect(selected, 2);
    final highlight = tester.widget<Container>(find.descendant(of: find.ancestor(of: find.text('Ingresso'), matching: find.byType(Column)), matching: find.byWidgetPredicate((w) => w is Container && w.decoration is BoxDecoration && (w.decoration as BoxDecoration).shape == BoxShape.circle)));
    expect((highlight.decoration as BoxDecoration).color, PhColors.sertao.brand);
    semantics.dispose();
  });

  testWidgets('barra superior com voltar de 44 px e título como cabeçalho', (tester) async {
    var back = false;
    await tester.pumpWidget(MaterialApp(theme: PhTheme.build(PhColors.sertao), home: Scaffold(appBar: PhAppBar(title: 'Sessão', onBack: () => back = true))));

    expectTouchTarget(tester, find.byTooltip('Voltar'));
    await tester.tap(find.byTooltip('Voltar'));
    expect(back, isTrue);
  });

  testWidgets('erro oferece tentar de novo e offline avisa o que está salvo', (tester) async {
    var retried = false;
    await tester.pumpWidget(MaterialApp(
      theme: PhTheme.build(PhColors.sertao),
      home: Scaffold(body: Column(children: [const PhOfflineBanner(), Expanded(child: PhErrorState(onRetry: () => retried = true))])),
    ));

    expect(find.text('Sem internet. Mostrando o que está salvo.'), findsOneWidget);
    expect(find.text('Não deu para carregar'), findsOneWidget);
    await tester.tap(find.text('Tentar de novo'));
    expect(retried, isTrue);
  });

  testWidgets('carregando é anunciado uma vez, sem ler os blocos', (tester) async {
    final semantics = tester.ensureSemantics();
    await tester.pumpWidget(MaterialApp(theme: PhTheme.build(PhColors.sertao), home: const Scaffold(body: PhLoadingList(label: 'Carregando programação'))));

    expect(find.bySemanticsLabel('Carregando programação'), findsOneWidget);
    semantics.dispose();
  });

  test('iniciais do avatar', () {
    expect(PhAvatar.initials('Ana Beatriz Lima'), 'AL');
    expect(PhAvatar.initials('marina'), 'M');
    expect(PhAvatar.initials('  '), '');
  });

  testWidgets('tema da edição muda a marca dos componentes', (tester) async {
    final edition = PhColors.sertao.withEdition(brand: const Color(0xFF1F4E79), onBrand: const Color(0xFFFFFFFF));
    await tester.pumpWidget(wrap(PhButton(label: 'Entrar', onPressed: () {}), colors: edition));

    final style = tester.widget<TextButton>(find.byType(TextButton)).style!;
    expect(style.backgroundColor!.resolve({}), const Color(0xFF1F4E79));
  });
}
