import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';
import 'package:phpeste_app/src/core/ui/ph_app_bar.dart';
import 'package:phpeste_app/src/core/ui/ph_avatar.dart';
import 'package:phpeste_app/src/core/ui/ph_badge.dart';
import 'package:phpeste_app/src/core/ui/ph_bottom_nav.dart';
import 'package:phpeste_app/src/core/ui/ph_button.dart';
import 'package:phpeste_app/src/core/ui/ph_card.dart';
import 'package:phpeste_app/src/core/ui/ph_chip.dart';
import 'package:phpeste_app/src/core/ui/ph_states.dart';
import 'package:phpeste_app/src/core/ui/ph_text_field.dart';

class ComponentCatalogPage extends StatefulWidget {
  static final PhColors sampleEdition = PhColors.sertao.withEdition(
    brand: const Color(0xFF1F4E79),
    onBrand: const Color(0xFFFFFFFF),
    accent: const Color(0xFF7FC8C2),
    onAccent: const Color(0xFF10263A),
    support: const Color(0xFF2E6B5E),
  );

  const ComponentCatalogPage({super.key});

  @override
  State<ComponentCatalogPage> createState() => _ComponentCatalogPageState();
}

class _ComponentCatalogPageState extends State<ComponentCatalogPage> {
  bool _edition = false;
  int _chip = 0;
  int _tab = 2;

  @override
  Widget build(BuildContext context) {
    final colors = _edition ? ComponentCatalogPage.sampleEdition : PhColors.sertao;
    return Theme(
      data: PhTheme.build(colors),
      child: Builder(builder: (context) => _catalog(context)),
    );
  }

  Widget _catalog(BuildContext context) {
    final c = context.ph;
    const gap = SizedBox(height: 12, width: 12);
    return Scaffold(
      backgroundColor: c.bg,
      appBar: PhAppBar(title: 'Componentes', onBack: Navigator.of(context).canPop() ? () => Navigator.of(context).pop() : null),
      bottomNavigationBar: PhBottomNav(
        destinations: const [
          PhNavDestination(label: 'Início', icon: Icons.home_outlined),
          PhNavDestination(label: 'Programação', icon: Icons.calendar_month_outlined),
          PhNavDestination(label: 'Ingresso', icon: Icons.confirmation_number_outlined),
          PhNavDestination(label: 'Agenda', icon: Icons.bookmark_border_rounded),
          PhNavDestination(label: 'Perfil', icon: Icons.person_outline_rounded),
        ],
        currentIndex: _tab,
        highlightedIndex: 2,
        onSelected: (index) => setState(() => _tab = index),
      ),
      body: ListView(
        children: [
          const PhStripe(),
          const PhOfflineBanner(),
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Tema de uma edição de exemplo'),
                  value: _edition,
                  onChanged: (value) => setState(() => _edition = value),
                ),
                gap,
                Wrap(spacing: 12, runSpacing: 12, children: [
                  PhButton(label: 'Primário', onPressed: () {}),
                  PhButton(label: 'Secundário', variant: PhButtonVariant.secondary, onPressed: () {}),
                  PhButton(label: 'Destaque', variant: PhButtonVariant.accent, onPressed: () {}),
                  PhButton(label: 'Excluir', variant: PhButtonVariant.danger, onPressed: () {}),
                  PhButton(label: 'Pequeno', small: true, onPressed: () {}),
                  const PhButton(label: 'Desativado', onPressed: null),
                  PhButton(label: 'Enviando', loading: true, onPressed: () {}),
                ]),
                gap,
                PhButton(label: 'Botão de largura total', icon: Icons.qr_code_2_rounded, block: true, onPressed: () {}),
                gap,
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final (index, label) in ['Todas', 'Backend', 'Arquitetura', 'Testes'].indexed)
                    PhChip(label: label, selected: _chip == index, onTap: () => setState(() => _chip = index)),
                ]),
                gap,
                const Wrap(spacing: 8, runSpacing: 8, children: [
                  PhBadge(label: 'Válido', tone: PhTone.ok),
                  PhBadge(label: 'Pendente', tone: PhTone.warn),
                  PhBadge(label: 'Recusado', tone: PhTone.danger),
                  PhBadge(label: 'Em avaliação', tone: PhTone.info),
                  PhBadge(label: 'Rascunho'),
                ]),
                gap,
                PhCard(
                  child: Row(children: [
                    const PhAvatar(name: 'Ana Beatriz Lima'),
                    gap,
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('Ana Beatriz Lima', style: Theme.of(context).textTheme.titleSmall),
                        Text('Engenheira de software', style: Theme.of(context).textTheme.bodySmall),
                      ]),
                    ),
                  ]),
                ),
                gap,
                const PhTextField(label: 'E-mail', placeholder: 'voce@exemplo.com', hint: 'O mesmo e-mail da compra.'),
                gap,
                const PhTextField(label: 'Senha', obscureText: true, error: 'Informe a senha.'),
                gap,
                SizedBox(height: 320, child: PhCard(padding: EdgeInsets.zero, child: PhErrorState(onRetry: () {}))),
                gap,
                const SizedBox(height: 360, child: PhCard(padding: EdgeInsets.zero, child: PhLoadingList(items: 2))),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
