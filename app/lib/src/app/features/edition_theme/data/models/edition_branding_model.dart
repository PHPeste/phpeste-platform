import 'dart:convert';

import 'package:phpeste_app/src/app/features/edition_theme/domain/entities/edition_branding_entity.dart';

class EditionBrandingModel extends EditionBrandingEntity {
  const EditionBrandingModel({
    required super.editionId,
    super.brand,
    super.onBrand,
    super.accent,
    super.onAccent,
    super.support,
    super.logoUrl,
    super.publishedAt,
  });

  static int? _color(Object? value) {
    if (value is! String || !RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(value)) return null;
    return 0xFF000000 | int.parse(value.substring(1), radix: 16);
  }

  static String? _hex(int? value) => value == null ? null : '#${(value & 0xFFFFFF).toRadixString(16).padLeft(6, '0').toUpperCase()}';

  static String? _https(Object? value) {
    if (value is! String) return null;
    final uri = Uri.tryParse(value);
    return uri != null && uri.scheme == 'https' && uri.host.isNotEmpty ? value : null;
  }

  factory EditionBrandingModel.fromApi(Map<String, dynamic> payload) {
    final data = payload['data'] as Map<String, dynamic>;
    final theme = (data['theme'] as Map<String, dynamic>?) ?? const {};
    final colors = (theme['colors'] as Map<String, dynamic>?) ?? const {};
    final logos = (theme['logos'] as Map<String, dynamic>?) ?? const {};

    return EditionBrandingModel(
      editionId: data['edition_id'] as String,
      brand: _color(colors['brand']),
      onBrand: _color(colors['on_brand']),
      accent: _color(colors['accent']),
      onAccent: _color(colors['on_accent']),
      support: _color(colors['support']),
      logoUrl: _https(logos['primary']),
      publishedAt: data['published_at'] as String?,
    );
  }

  Map<String, dynamic> toMap() => {
        'edition_id': editionId,
        'brand': _hex(brand),
        'on_brand': _hex(onBrand),
        'accent': _hex(accent),
        'on_accent': _hex(onAccent),
        'support': _hex(support),
        'logo_url': logoUrl,
        'published_at': publishedAt,
      };

  factory EditionBrandingModel.fromMap(Map<String, dynamic> map) => EditionBrandingModel(
        editionId: map['edition_id'] as String,
        brand: _color(map['brand']),
        onBrand: _color(map['on_brand']),
        accent: _color(map['accent']),
        onAccent: _color(map['on_accent']),
        support: _color(map['support']),
        logoUrl: _https(map['logo_url']),
        publishedAt: map['published_at'] as String?,
      );

  String toJson() => json.encode(toMap());

  factory EditionBrandingModel.fromJson(String source) => EditionBrandingModel.fromMap(json.decode(source) as Map<String, dynamic>);
}
