import 'dart:math' as math;
import 'dart:ui';

double _channel(double value) => value <= 0.03928 ? value / 12.92 : math.pow((value + 0.055) / 1.055, 2.4).toDouble();

double relativeLuminance(Color color) => 0.2126 * _channel(color.r) + 0.7152 * _channel(color.g) + 0.0722 * _channel(color.b);

double contrastRatio(Color a, Color b) {
  final first = relativeLuminance(a);
  final second = relativeLuminance(b);
  final lighter = math.max(first, second);
  final darker = math.min(first, second);
  return (lighter + 0.05) / (darker + 0.05);
}

Color? parseHexColor(Object? value) {
  if (value is! String || !RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(value)) return null;
  return Color(0xFF000000 | int.parse(value.substring(1), radix: 16));
}
