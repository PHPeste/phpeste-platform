import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

Future<void> testExecutable(FutureOr<void> Function() testMain) async {
  TestWidgetsFlutterBinding.ensureInitialized();

  final lexend = FontLoader('Lexend');
  for (final file in ['Regular', 'Medium', 'SemiBold']) {
    lexend.addFont(rootBundle.load('assets/fonts/lexend/Lexend-$file.ttf'));
  }
  await lexend.load();

  final flutterRoot = Platform.environment['FLUTTER_ROOT'];
  final icons = File('$flutterRoot/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf');
  if (flutterRoot != null && icons.existsSync()) {
    final loader = FontLoader('MaterialIcons')..addFont(Future.value(ByteData.sublistView(icons.readAsBytesSync())));
    await loader.load();
  }

  if (goldenFileComparator is LocalFileComparator) {
    goldenFileComparator = TolerantGoldenComparator((goldenFileComparator as LocalFileComparator).basedir.resolve('golden_test.dart'));
  }

  await testMain();
}

class TolerantGoldenComparator extends LocalFileComparator {
  static const double tolerance = 0.005;

  TolerantGoldenComparator(super.testFile);

  @override
  Future<bool> compare(Uint8List imageBytes, Uri golden) async {
    final result = await GoldenFileComparator.compareLists(imageBytes, await getGoldenBytes(golden));
    if (result.passed || result.diffPercent <= tolerance) {
      result.dispose();
      return true;
    }
    final error = await generateFailureOutput(result, golden, basedir);
    result.dispose();
    throw FlutterError(error);
  }
}
