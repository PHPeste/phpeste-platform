import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';

class PhAvatar extends StatelessWidget {
  final String name;
  final double size;

  const PhAvatar({super.key, required this.name, this.size = 36});

  static String initials(String name) {
    final words = name.trim().split(RegExp(r'\s+')).where((word) => word.isNotEmpty).toList();
    if (words.isEmpty) return '';
    final first = words.first[0];
    final last = words.length > 1 ? words.last[0] : '';
    return (first + last).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return ExcludeSemantics(
      child: Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(color: c.accentSoft, shape: BoxShape.circle),
        child: Text(initials(name), style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: size * 0.36, fontWeight: FontWeight.w600, color: c.brand)),
      ),
    );
  }
}
