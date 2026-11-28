import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/app/features/base/presentation/texts/base_texts.dart';
import 'package:phpeste_app/src/core/ui/ph_bottom_nav.dart';

class BasePage extends StatelessWidget {
  static const int ticketIndex = 2;

  static const List<PhNavDestination> destinations = [
    PhNavDestination(label: BaseTexts.home, icon: Icons.home_outlined),
    PhNavDestination(label: BaseTexts.program, icon: Icons.calendar_month_outlined),
    PhNavDestination(label: BaseTexts.ticket, icon: Icons.confirmation_number_outlined),
    PhNavDestination(label: BaseTexts.agenda, icon: Icons.bookmark_border_rounded),
    PhNavDestination(label: BaseTexts.profile, icon: Icons.person_outline_rounded),
  ];

  const BasePage({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: shell,
      bottomNavigationBar: PhBottomNav(
        destinations: destinations,
        currentIndex: shell.currentIndex,
        highlightedIndex: ticketIndex,
        onSelected: (index) => shell.goBranch(index, initialLocation: index == shell.currentIndex),
      ),
    );
  }
}
