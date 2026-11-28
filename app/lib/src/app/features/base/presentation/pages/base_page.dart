import 'package:material_ui/material_ui.dart';
import 'package:go_router/go_router.dart';
import 'package:phpeste_app/src/app/features/base/presentation/texts/base_texts.dart';

class BasePage extends StatelessWidget {
  const BasePage({super.key, required this.shell});

  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: shell,
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: shell.currentIndex,
        onTap: shell.goBranch,
        showUnselectedLabels: true,
        items: const [
          BottomNavigationBarItem(
            backgroundColor: Colors.white,
            icon: Icon(Icons.home_outlined),
            label: BaseTexts.home,
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.person),
            backgroundColor: Colors.white,
            label: BaseTexts.profile,
          ),
        ],
      ),
    );
  }
}
