import 'package:equatable/equatable.dart';

class EditionBrandingEntity extends Equatable {
  final String editionId;
  final int? brand;
  final int? onBrand;
  final int? accent;
  final int? onAccent;
  final int? support;
  final String? logoUrl;
  final String? publishedAt;

  const EditionBrandingEntity({
    required this.editionId,
    this.brand,
    this.onBrand,
    this.accent,
    this.onAccent,
    this.support,
    this.logoUrl,
    this.publishedAt,
  });

  @override
  List<Object?> get props => [editionId, brand, onBrand, accent, onAccent, support, logoUrl, publishedAt];
}
