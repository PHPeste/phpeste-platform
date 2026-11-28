import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';

enum PhTone { ok, warn, danger, info, neutral }

class PhBadge extends StatelessWidget {
  final String label;
  final PhTone tone;
  final IconData? icon;

  const PhBadge({super.key, required this.label, this.tone = PhTone.neutral, this.icon});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    final (background, foreground) = switch (tone) {
      PhTone.ok => (c.okSoft, c.ok),
      PhTone.warn => (c.warnSoft, c.warn),
      PhTone.danger => (c.dangerSoft, c.danger),
      PhTone.info => (c.infoSoft, c.info),
      PhTone.neutral => (c.surface2, c.ink2),
    };

    return Container(
      constraints: const BoxConstraints(minHeight: 26),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) Icon(icon, size: 14, color: foreground) else Container(width: 6, height: 6, decoration: BoxDecoration(color: foreground, shape: BoxShape.circle)),
          const SizedBox(width: 6),
          Flexible(child: Text(label, style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: 12, fontWeight: FontWeight.w500, color: foreground))),
        ],
      ),
    );
  }
}
