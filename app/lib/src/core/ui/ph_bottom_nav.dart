import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';
import 'package:phpeste_app/src/core/ui/ui_texts.dart';

class PhNavDestination {
  final String label;
  final IconData icon;

  const PhNavDestination({required this.label, required this.icon});
}

class PhBottomNav extends StatelessWidget {
  final List<PhNavDestination> destinations;
  final int currentIndex;
  final int? highlightedIndex;
  final ValueChanged<int> onSelected;

  const PhBottomNav({super.key, required this.destinations, required this.currentIndex, required this.onSelected, this.highlightedIndex});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return Semantics(
      container: true,
      label: UiTexts.navigation,
      explicitChildNodes: true,
      child: DecoratedBox(
        decoration: BoxDecoration(color: c.surface, border: Border(top: BorderSide(color: c.line))),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(4, 6, 4, 6),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                for (var i = 0; i < destinations.length; i++)
                  Expanded(child: _item(context, c, i)),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _item(BuildContext context, PhColors c, int index) {
    final destination = destinations[index];
    final selected = index == currentIndex;
    final highlighted = index == highlightedIndex;
    final color = selected ? c.brand : c.ink2;

    final icon = highlighted
        ? Transform.translate(
            offset: const Offset(0, -14),
            child: Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(color: c.brand, shape: BoxShape.circle, border: Border.all(color: c.bg, width: 4)),
              child: Icon(destination.icon, color: c.onBrand),
            ),
          )
        : Icon(destination.icon, color: color, weight: selected ? 700 : 400);

    return Semantics(
      button: true,
      selected: selected,
      label: destination.label,
      onTap: () => onSelected(index),
      excludeSemantics: true,
      child: InkWell(
        onTap: () => onSelected(index),
        borderRadius: BorderRadius.circular(12),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 52, minWidth: PhTheme.minTouch),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              SizedBox(height: 28, child: OverflowBox(maxHeight: 70, child: icon)),
              const SizedBox(height: 2),
              FittedBox(fit: BoxFit.scaleDown, child: Text(destination.label, maxLines: 1, style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: 11, fontWeight: FontWeight.w500, color: color))),
            ],
          ),
        ),
      ),
    );
  }
}
