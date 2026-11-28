import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/app/features/home/presentation/texts/home_texts.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/services/session_service.dart';
import 'package:phpeste_app/src/core/ui/ph_app_bar.dart';
import 'package:phpeste_app/src/core/ui/ph_states.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PhAppBar(
        title: HomeTexts.title,
        actions: [
          IconButton(
            onPressed: () => injector<SessionService>().removeToken(),
            tooltip: HomeTexts.logout,
            icon: const Icon(Icons.logout_rounded),
          ),
        ],
      ),
      body: const PhMessageState(icon: Icons.festival_outlined, title: HomeTexts.body),
    );
  }
}
