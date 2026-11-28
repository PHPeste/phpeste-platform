import 'package:material_ui/material_ui.dart';
import 'package:phpeste_app/src/core/theme/ph_colors.dart';
import 'package:phpeste_app/src/core/theme/ph_theme.dart';
import 'package:phpeste_app/src/core/ui/ph_button.dart';
import 'package:phpeste_app/src/core/ui/ui_texts.dart';

class PhOfflineBanner extends StatelessWidget {
  final String message;

  const PhOfflineBanner({super.key, this.message = UiTexts.offline});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return Semantics(
      liveRegion: true,
      child: Container(
        width: double.infinity,
        color: c.warnSoft,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        child: Row(
          children: [
            Icon(Icons.wifi_off_rounded, color: c.warn, size: 20),
            const SizedBox(width: 10),
            Expanded(child: Text(message, style: TextStyle(fontFamily: PhTheme.fontFamily, fontSize: 13, color: c.ink))),
          ],
        ),
      ),
    );
  }
}

class PhMessageState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? text;
  final List<Widget> actions;

  const PhMessageState({super.key, required this.icon, required this.title, this.text, this.actions = const []});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.symmetric(horizontal: 36, vertical: 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ExcludeSemantics(
              child: Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(color: c.surface2, shape: BoxShape.circle),
                child: Icon(icon, color: c.brand, size: 32),
              ),
            ),
            const SizedBox(height: 14),
            Semantics(header: true, child: Text(title, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleLarge)),
            if (text != null) ...[
              const SizedBox(height: 14),
              Text(text!, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: c.ink2)),
            ],
            for (final action in actions) ...[const SizedBox(height: 14), action],
          ],
        ),
      ),
    );
  }
}

class PhErrorState extends StatelessWidget {
  final VoidCallback onRetry;
  final String title;
  final String text;

  const PhErrorState({super.key, required this.onRetry, this.title = UiTexts.errorTitle, this.text = UiTexts.errorText});

  @override
  Widget build(BuildContext context) {
    return PhMessageState(
      icon: Icons.cloud_off_rounded,
      title: title,
      text: text,
      actions: [PhButton(label: UiTexts.retry, icon: Icons.refresh_rounded, onPressed: onRetry)],
    );
  }
}

class PhSkeleton extends StatelessWidget {
  final double width;
  final double height;

  const PhSkeleton({super.key, this.width = double.infinity, required this.height});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(8),
        gradient: LinearGradient(colors: [c.surface2, c.line, c.surface2]),
      ),
    );
  }
}

class PhLoadingList extends StatelessWidget {
  final String label;
  final int items;

  const PhLoadingList({super.key, this.label = UiTexts.loading, this.items = 4});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return Semantics(
      label: label,
      liveRegion: true,
      child: ExcludeSemantics(
        child: ListView(
          padding: const EdgeInsets.all(20),
          physics: const NeverScrollableScrollPhysics(),
          children: [
            const FractionallySizedBox(alignment: Alignment.centerLeft, widthFactor: 0.6, child: PhSkeleton(height: 36)),
            for (var i = 0; i < items; i++)
              Container(
                margin: const EdgeInsets.only(top: 14),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                decoration: BoxDecoration(color: c.surface, borderRadius: BorderRadius.circular(PhTheme.radiusLg), border: Border.all(color: c.line)),
                child: const Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    SizedBox(width: 52, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [PhSkeleton(height: 18), SizedBox(height: 8), PhSkeleton(width: 36, height: 12)])),
                    SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          FractionallySizedBox(widthFactor: 0.9, child: PhSkeleton(height: 18)),
                          SizedBox(height: 10),
                          FractionallySizedBox(widthFactor: 0.6, child: PhSkeleton(height: 12)),
                          SizedBox(height: 10),
                          FractionallySizedBox(widthFactor: 0.4, child: PhSkeleton(height: 12)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class PhStripe extends StatelessWidget {
  const PhStripe({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.ph;
    return ExcludeSemantics(
      child: SizedBox(
        height: 8,
        child: Row(children: [for (var i = 0; i < 40; i++) Expanded(child: ColoredBox(color: i.isEven ? c.accent : c.terra))]),
      ),
    );
  }
}
