import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';

class PhTheme {
  static const String fontFamily = 'Lexend';
  static const double radius = 10;
  static const double radiusLg = 14;
  static const double minTouch = 44;

  static ThemeData build(PhColors c) {
    final scheme = ColorScheme(
      brightness: Brightness.light,
      primary: c.brand,
      onPrimary: c.onBrand,
      secondary: c.accent,
      onSecondary: c.onAccent,
      tertiary: c.terra,
      onTertiary: c.onBrand,
      error: c.danger,
      onError: const Color(0xFFFFFFFF),
      surface: c.surface,
      onSurface: c.ink,
      onSurfaceVariant: c.ink2,
      surfaceContainerHighest: c.surface2,
      outline: c.line,
      outlineVariant: c.line,
    );

    final text = TextTheme(
      headlineMedium: TextStyle(fontSize: 28, height: 1.15, fontWeight: FontWeight.w600, color: c.ink),
      headlineSmall: TextStyle(fontSize: 24, height: 1.2, fontWeight: FontWeight.w600, color: c.ink),
      titleLarge: TextStyle(fontSize: 20, height: 1.2, fontWeight: FontWeight.w600, color: c.ink),
      titleMedium: TextStyle(fontSize: 16, height: 1.35, fontWeight: FontWeight.w600, color: c.ink),
      titleSmall: TextStyle(fontSize: 15, height: 1.35, fontWeight: FontWeight.w600, color: c.ink),
      bodyLarge: TextStyle(fontSize: 16, height: 1.5, fontWeight: FontWeight.w400, color: c.ink),
      bodyMedium: TextStyle(fontSize: 14, height: 1.5, fontWeight: FontWeight.w400, color: c.ink),
      bodySmall: TextStyle(fontSize: 12, height: 1.45, fontWeight: FontWeight.w400, color: c.ink3),
      labelLarge: TextStyle(fontSize: 14, height: 1.2, fontWeight: FontWeight.w500, color: c.ink),
      labelMedium: TextStyle(fontSize: 12, height: 1.2, fontWeight: FontWeight.w500, color: c.ink2),
      labelSmall: TextStyle(fontSize: 12, height: 1.2, fontWeight: FontWeight.w500, color: c.ink2),
    );

    final shape = RoundedRectangleBorder(borderRadius: BorderRadius.circular(radius));
    const buttonText = TextStyle(fontFamily: fontFamily, fontSize: 14, fontWeight: FontWeight.w500);

    OutlineInputBorder border(Color color, [double width = 1]) =>
        OutlineInputBorder(borderRadius: BorderRadius.circular(radius), borderSide: BorderSide(color: color, width: width));

    return ThemeData(
      useMaterial3: true,
      fontFamily: fontFamily,
      colorScheme: scheme,
      scaffoldBackgroundColor: c.bg,
      textTheme: text,
      extensions: [c],
      materialTapTargetSize: MaterialTapTargetSize.padded,
      dividerTheme: DividerThemeData(color: c.line, thickness: 1, space: 1),
      appBarTheme: AppBarTheme(
        backgroundColor: c.bg,
        foregroundColor: c.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: text.titleLarge!.copyWith(fontFamily: fontFamily),
      ),
      cardTheme: CardThemeData(
        color: c.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(radiusLg), side: BorderSide(color: c.line)),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: c.brand,
          foregroundColor: c.onBrand,
          minimumSize: const Size(minTouch, minTouch),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          shape: shape,
          textStyle: buttonText,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: c.ink,
          backgroundColor: Colors.transparent,
          minimumSize: const Size(minTouch, minTouch),
          padding: const EdgeInsets.symmetric(horizontal: 18),
          side: BorderSide(color: c.line),
          shape: shape,
          textStyle: buttonText,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: c.link, minimumSize: const Size(minTouch, minTouch), textStyle: buttonText),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(foregroundColor: c.ink, minimumSize: const Size(minTouch, minTouch), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: c.surface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        hintStyle: TextStyle(color: c.ink3, fontSize: 14),
        errorStyle: TextStyle(color: c.danger, fontSize: 12),
        border: border(c.line),
        enabledBorder: border(c.line),
        focusedBorder: border(c.brand, 2),
        errorBorder: border(c.danger),
        focusedErrorBorder: border(c.danger, 2),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: c.surface,
        selectedColor: c.brand,
        labelStyle: TextStyle(fontFamily: fontFamily, fontSize: 14, fontWeight: FontWeight.w500, color: c.ink2),
        secondaryLabelStyle: TextStyle(fontFamily: fontFamily, fontSize: 14, fontWeight: FontWeight.w500, color: c.onBrand),
        side: BorderSide(color: c.line),
        shape: const StadiumBorder(),
        showCheckmark: false,
      ),
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStatePropertyAll(c.surface),
        trackColor: WidgetStateProperty.resolveWith((states) => states.contains(WidgetState.selected) ? c.brand : c.line),
        trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
      ),
      snackBarTheme: SnackBarThemeData(backgroundColor: c.ink, contentTextStyle: TextStyle(fontFamily: fontFamily, color: c.surface, fontSize: 14)),
      progressIndicatorTheme: ProgressIndicatorThemeData(color: c.brand),
    );
  }
}
