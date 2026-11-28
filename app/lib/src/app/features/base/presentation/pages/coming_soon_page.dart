import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/app/features/base/presentation/texts/base_texts.dart';
import 'package:phpeste_app/src/core/ui/ph_app_bar.dart';
import 'package:phpeste_app/src/core/ui/ph_states.dart';

class ComingSoonPage extends StatelessWidget {
  final String title;
  final IconData icon;

  const ComingSoonPage({super.key, required this.title, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: PhAppBar(title: title),
      body: PhMessageState(icon: icon, title: BaseTexts.soonTitle, text: BaseTexts.soonText),
    );
  }
}
