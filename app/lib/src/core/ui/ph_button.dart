import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';

enum PhButtonVariant { primary, secondary, danger, accent }

class PhButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final PhButtonVariant variant;
  final IconData? icon;
  final bool block;
  final bool small;
  final bool loading;

  const PhButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.variant = PhButtonVariant.primary,
    this.icon,
    this.block = false,
    this.small = false,
    this.loading = false,
  });

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    final (background, foreground, border) = switch (variant) {
      PhButtonVariant.primary => (c.brand, c.onBrand, c.brand),
      PhButtonVariant.secondary => (Colors.transparent, c.ink, c.line),
      PhButtonVariant.danger => (c.danger, const Color(0xFFFFFFFF), c.danger),
      PhButtonVariant.accent => (c.accent, c.onAccent, c.accent),
    };

    final child = loading
        ? SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2, color: foreground))
        : Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icon != null) ...[Icon(icon, size: 20), const SizedBox(width: 8)],
              Flexible(child: Text(label, textAlign: TextAlign.center)),
            ],
          );

    final button = TextButton(
      onPressed: loading ? null : onPressed,
      style: TextButton.styleFrom(
        backgroundColor: background,
        foregroundColor: foreground,
        disabledBackgroundColor: variant == PhButtonVariant.secondary ? Colors.transparent : background.withValues(alpha: 0.55),
        disabledForegroundColor: foreground.withValues(alpha: 0.8),
        minimumSize: const Size(PhTheme.minTouch, PhTheme.minTouch),
        padding: EdgeInsets.symmetric(horizontal: small ? 12 : 18, vertical: small ? 8 : 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(PhTheme.radius), side: BorderSide(color: border)),
        textStyle: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: small ? 13 : 14, fontWeight: FontWeight.w500),
      ),
      child: child,
    );

    return Semantics(
      button: true,
      label: loading ? label : null,
      excludeSemantics: loading,
      child: block ? SizedBox(width: double.infinity, child: button) : button,
    );
  }
}
