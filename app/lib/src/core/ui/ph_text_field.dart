import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';

class PhTextField extends StatelessWidget {
  final String label;
  final String? hint;
  final String? placeholder;
  final String? error;
  final TextEditingController? controller;
  final ValueChanged<String>? onChanged;
  final TextInputType? keyboardType;
  final bool obscureText;
  final int maxLines;
  final Iterable<String>? autofillHints;
  final TextInputAction? textInputAction;
  final Widget? suffix;

  const PhTextField({
    super.key,
    required this.label,
    this.hint,
    this.placeholder,
    this.error,
    this.controller,
    this.onChanged,
    this.keyboardType,
    this.obscureText = false,
    this.maxLines = 1,
    this.autofillHints,
    this.textInputAction,
    this.suffix,
  });

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: 14, fontWeight: FontWeight.w500, color: c.ink)),
        const SizedBox(height: 6),
        TextField(
          controller: controller,
          onChanged: onChanged,
          keyboardType: keyboardType,
          obscureText: obscureText,
          maxLines: obscureText ? 1 : maxLines,
          autofillHints: autofillHints,
          textInputAction: textInputAction,
          style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: 16, color: c.ink),
          decoration: InputDecoration(
            semanticCounterText: '',
            hintText: placeholder,
            errorText: error,
            suffixIcon: suffix,
            constraints: const BoxConstraints(minHeight: PhTheme.minTouch),
          ),
        ),
        if (hint != null && error == null) ...[
          const SizedBox(height: 4),
          Text(hint!, style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: 12, color: c.ink3)),
        ],
      ],
    );
  }
}
