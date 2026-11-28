import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/ui/ui_texts.dart';

class PhAppBar extends StatelessWidget implements PreferredSizeWidget {
  final String title;
  final VoidCallback? onBack;
  final List<Widget> actions;

  const PhAppBar({super.key, required this.title, this.onBack, this.actions = const []});

  @override
  Size get preferredSize => const Size.fromHeight(60);

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return SafeArea(
      bottom: false,
      child: Container(
        color: c.bg,
        constraints: const BoxConstraints(minHeight: 56),
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 4),
        child: Row(
          children: [
            if (onBack != null)
              IconButton(onPressed: onBack, tooltip: UiTexts.back, icon: const Icon(Icons.chevron_left))
            else
              const SizedBox(width: 8),
            const SizedBox(width: 4),
            Expanded(
              child: Semantics(header: true, child: Text(title, style: Theme.of(context).textTheme.titleLarge, overflow: TextOverflow.ellipsis)),
            ),
            ...actions,
          ],
        ),
      ),
    );
  }
}
