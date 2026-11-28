import 'package:phpeste_app/src/app/features/home/presentation/texts/home_texts.dart';
import 'package:phpeste_app/src/core/DI/dependency_injector.dart';
import 'package:phpeste_app/src/core/services/session_service.dart';
import 'package:material_ui/material_ui.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(HomeTexts.title),
        centerTitle: true,
        actions: [
          IconButton(
            onPressed: () async {
              final sessionService = injector<SessionService>();
              await sessionService.removeToken();
            },
            tooltip: HomeTexts.logout,
            icon: const Icon(Icons.logout_sharp),
          ),
        ],
      ),
      body: const Center(
        child: Text(HomeTexts.body),
      ),
    );
  }
}
