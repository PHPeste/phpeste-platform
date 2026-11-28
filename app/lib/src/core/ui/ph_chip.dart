import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';

class PhChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback? onTap;

  const PhChip({super.key, required this.label, this.selected = false, this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return Semantics(
      button: true,
      selected: selected,
      child: InkWell(
        onTap: onTap,
        customBorder: const StadiumBorder(),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: PhTheme.minTouch, minWidth: PhTheme.minTouch),
          child: Center(
            widthFactor: 1,
            child: Container(
              constraints: const BoxConstraints(minHeight: 36),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: ShapeDecoration(
                color: selected ? c.brand : c.surface,
                shape: StadiumBorder(side: BorderSide(color: selected ? c.brand : c.line)),
              ),
              child: Text(
                label,
                style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: 14, fontWeight: FontWeight.w500, color: selected ? c.onBrand : c.ink2),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
