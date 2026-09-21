import 'dart:async';
import 'dart:io';
import 'dart:math';
import 'dart:ui';

import 'package:camera/camera.dart';
import 'package:flutter/services.dart' show DeviceOrientation;
import 'package:google_mlkit_face_detection/google_mlkit_face_detection.dart';

import '../models/eye_direction.dart';

/// Resultaat van de verwerking van één camera-frame.
class EyeTrackingFrame {
  final EyeDirection richting;
  final bool gezichtGedetecteerd;

  const EyeTrackingFrame({
    required this.richting,
    required this.gezichtGedetecteerd,
  });
}

/// Beheert de voorcamera-stream, laat Google ML Kit een gezicht + oogomtrek
/// detecteren, en schat daarbinnen de positie van de pupil/iris met een
/// eenvoudige "donkerste pixelcluster"-heuristiek (géén deep learning-
/// gebaseerde iris-tracking). Dit is een grove, best-effort benadering -
/// zie ook [diltsDisclaimer] in eye_direction.dart voor de beperkingen.
///
/// Google ML Kit's on-device Vision API heeft alleen Android- en
/// iOS-implementaties; op Windows is automatische detectie niet mogelijk
/// en moet de gebruiker de waargenomen richting handmatig kiezen (zie
/// [automatischeDetectieBeschikbaar]).
class EyeTrackingService {
  CameraController? _controller;
  FaceDetector? _faceDetector;

  final StreamController<EyeTrackingFrame> _uitvoer =
      StreamController<EyeTrackingFrame>.broadcast();

  bool _busy = false;
  DateTime _laatsteVerwerking = DateTime.fromMillisecondsSinceEpoch(0);
  static const _minInterval = Duration(milliseconds: 150);

  Stream<EyeTrackingFrame> get stream => _uitvoer.stream;

  CameraController? get controller => _controller;

  bool get actief => _controller?.value.isStreamingImages ?? false;

  static bool get automatischeDetectieBeschikbaar =>
      !kIsWebOrDesktop && (Platform.isAndroid || Platform.isIOS);

  Future<void> start() async {
    if (!automatischeDetectieBeschikbaar) {
      throw StateError(
        'Automatische oogdetectie is op dit platform niet beschikbaar. '
        'Gebruik de handmatige richtingkeuze.',
      );
    }
    final cameras = await availableCameras();
    if (cameras.isEmpty) {
      throw StateError('Geen camera gevonden op dit apparaat.');
    }
    final voorCamera = cameras.firstWhere(
      (c) => c.lensDirection == CameraLensDirection.front,
      orElse: () => cameras.first,
    );

    final controller = CameraController(
      voorCamera,
      ResolutionPreset.medium,
      enableAudio: false,
      imageFormatGroup:
          Platform.isAndroid ? ImageFormatGroup.nv21 : ImageFormatGroup.bgra8888,
    );
    await controller.initialize();
    _controller = controller;

    _faceDetector = FaceDetector(
      options: FaceDetectorOptions(
        enableContours: true,
        enableClassification: true,
        enableTracking: false,
        performanceMode: FaceDetectorMode.fast,
      ),
    );

    await controller.startImageStream(_opFrame);
  }

  Future<void> stop() async {
    final controller = _controller;
    _controller = null;
    try {
      if (controller != null && controller.value.isStreamingImages) {
        await controller.stopImageStream();
      }
    } catch (_) {
      // Camera kan al gestopt/losgekoppeld zijn; niet fataal.
    }
    await controller?.dispose();
    await _faceDetector?.close();
    _faceDetector = null;
  }

  Future<void> dispose() async {
    await stop();
    await _uitvoer.close();
  }

  void _opFrame(CameraImage image) {
    final nu = DateTime.now();
    if (_busy || nu.difference(_laatsteVerwerking) < _minInterval) return;
    _busy = true;
    _laatsteVerwerking = nu;
    _verwerk(image).whenComplete(() => _busy = false);
  }

  Future<void> _verwerk(CameraImage image) async {
    final controller = _controller;
    final detector = _faceDetector;
    if (controller == null || detector == null || _uitvoer.isClosed) return;

    final inputImage = _naarInputImage(
      image,
      controller.description,
      controller.value.deviceOrientation,
    );
    if (inputImage == null) {
      _voegToe(const EyeTrackingFrame(
        richting: EyeDirection.onbekend,
        gezichtGedetecteerd: false,
      ));
      return;
    }

    List<Face> gezichten;
    try {
      gezichten = await detector.processImage(inputImage);
    } catch (_) {
      return;
    }

    if (gezichten.isEmpty) {
      _voegToe(const EyeTrackingFrame(
        richting: EyeDirection.onbekend,
        gezichtGedetecteerd: false,
      ));
      return;
    }

    final richting = _bepaalRichting(gezichten.first, image);
    _voegToe(EyeTrackingFrame(richting: richting, gezichtGedetecteerd: true));
  }

  void _voegToe(EyeTrackingFrame frame) {
    if (!_uitvoer.isClosed) _uitvoer.add(frame);
  }

  EyeDirection _bepaalRichting(Face gezicht, CameraImage image) {
    final yaw = gezicht.headEulerAngleY ?? 0;
    final pitch = gezicht.headEulerAngleX ?? 0;
    // Bij een te sterk gedraaid hoofd is een uitspraak over de oogrichting
    // niet betrouwbaar te onderscheiden van een hoofdbeweging.
    if (yaw.abs() > 25 || pitch.abs() > 20) return EyeDirection.onbekend;

    final linksOpen = gezicht.leftEyeOpenProbability;
    final rechtsOpen = gezicht.rightEyeOpenProbability;
    if ((linksOpen != null && linksOpen < 0.35) ||
        (rechtsOpen != null && rechtsOpen < 0.35)) {
      return EyeDirection.onbekend;
    }

    final linkerOog = gezicht.contours[FaceContourType.leftEye]?.points;
    final rechterOog = gezicht.contours[FaceContourType.rightEye]?.points;
    if ((linkerOog == null || linkerOog.isEmpty) &&
        (rechterOog == null || rechterOog.isEmpty)) {
      return EyeDirection.onbekend;
    }

    final posities = <_NormPositie>[];
    if (linkerOog != null && linkerOog.isNotEmpty) {
      final p = _irisPositieInOog(image, linkerOog);
      if (p != null) posities.add(p);
    }
    if (rechterOog != null && rechterOog.isNotEmpty) {
      final p = _irisPositieInOog(image, rechterOog);
      if (p != null) posities.add(p);
    }
    if (posities.isEmpty) return EyeDirection.onbekend;

    final x = posities.map((p) => p.x).reduce((a, b) => a + b) / posities.length;
    final y = posities.map((p) => p.y).reduce((a, b) => a + b) / posities.length;
    return _classificeer(x, y);
  }

  EyeDirection _classificeer(double x, double y) {
    const laag = 0.38;
    const hoog = 0.62;
    final links = x < laag;
    final rechts = x > hoog;
    final boven = y < laag;
    final onder = y > hoog;
    if (boven && links) return EyeDirection.upLeft;
    if (boven && rechts) return EyeDirection.upRight;
    if (boven) return EyeDirection.upCenter;
    if (onder && links) return EyeDirection.downLeft;
    if (onder && rechts) return EyeDirection.downRight;
    if (onder) return EyeDirection.downCenter;
    if (links) return EyeDirection.midLeft;
    if (rechts) return EyeDirection.midRight;
    return EyeDirection.center;
  }

  /// Zoekt binnen de begrenzende box van de oogomtrek de donkerste
  /// pixelcluster (pupil/iris is doorgaans donkerder dan het oogwit) en
  /// geeft de genormaliseerde positie (0..1 in beide richtingen) daarvan
  /// terug. Retourneert null bij te weinig contrast (bijv. slechte
  /// belichting) om een betrouwbare schatting te maken.
  _NormPositie? _irisPositieInOog(CameraImage image, List<Point<int>> contour) {
    var minX = contour.first.x, maxX = contour.first.x;
    var minY = contour.first.y, maxY = contour.first.y;
    for (final p in contour) {
      minX = min(minX, p.x);
      maxX = max(maxX, p.x);
      minY = min(minY, p.y);
      maxY = max(maxY, p.y);
    }
    minX = minX.clamp(0, image.width - 1);
    maxX = maxX.clamp(0, image.width - 1);
    minY = minY.clamp(0, image.height - 1);
    maxY = maxY.clamp(0, image.height - 1);
    final breedte = maxX - minX;
    final hoogte = maxY - minY;
    if (breedte < 4 || hoogte < 3) return null;

    final stap = max(1, (min(breedte, hoogte) / 15).round());

    var minLum = 255;
    var maxLum = 0;
    for (var y = minY; y <= maxY; y += stap) {
      for (var x = minX; x <= maxX; x += stap) {
        final lum = _luminantie(image, x, y);
        if (lum == null) continue;
        if (lum < minLum) minLum = lum;
        if (lum > maxLum) maxLum = lum;
      }
    }
    if (maxLum - minLum < 12) return null; // te weinig contrast

    final drempel = minLum + (maxLum - minLum) * 0.25;
    double sumX = 0, sumY = 0, gewicht = 0;
    for (var y = minY; y <= maxY; y += stap) {
      for (var x = minX; x <= maxX; x += stap) {
        final lum = _luminantie(image, x, y);
        if (lum == null || lum > drempel) continue;
        final w = (drempel - lum) + 1;
        sumX += x * w;
        sumY += y * w;
        gewicht += w;
      }
    }
    if (gewicht <= 0) return null;

    final cx = sumX / gewicht;
    final cy = sumY / gewicht;
    final normX = ((cx - minX) / breedte).clamp(0.0, 1.0);
    final normY = ((cy - minY) / hoogte).clamp(0.0, 1.0);
    return _NormPositie(normX, normY);
  }

  int? _luminantie(CameraImage image, int x, int y) {
    if (x < 0 || y < 0 || x >= image.width || y >= image.height) return null;
    if (image.planes.isEmpty) return null;
    final vlak = image.planes.first;
    if (image.format.group == ImageFormatGroup.bgra8888) {
      final bpp = vlak.bytesPerPixel ?? 4;
      final index = y * vlak.bytesPerRow + x * bpp;
      if (index + 2 >= vlak.bytes.length) return null;
      final b = vlak.bytes[index];
      final g = vlak.bytes[index + 1];
      final r = vlak.bytes[index + 2];
      return ((r + g + b) / 3).round();
    }
    // nv21 / yuv420: de eerste (volledige resolutie) plane is de Y-vlak,
    // die direct als luminantie kan worden gebruikt.
    final index = y * vlak.bytesPerRow + x;
    if (index >= vlak.bytes.length) return null;
    return vlak.bytes[index];
  }

  InputImage? _naarInputImage(
    CameraImage image,
    CameraDescription camera,
    DeviceOrientation deviceOrientation,
  ) {
    InputImageRotation? rotation;
    if (Platform.isIOS) {
      rotation = InputImageRotationValue.fromRawValue(camera.sensorOrientation);
    } else if (Platform.isAndroid) {
      const gradenPerOrientatie = {
        DeviceOrientation.portraitUp: 0,
        DeviceOrientation.landscapeLeft: 90,
        DeviceOrientation.portraitDown: 180,
        DeviceOrientation.landscapeRight: 270,
      };
      final deviceGraden = gradenPerOrientatie[deviceOrientation] ?? 0;
      int rotatieCompensatie;
      if (camera.lensDirection == CameraLensDirection.front) {
        rotatieCompensatie = (camera.sensorOrientation + deviceGraden) % 360;
      } else {
        rotatieCompensatie =
            (camera.sensorOrientation - deviceGraden + 360) % 360;
      }
      rotation = InputImageRotationValue.fromRawValue(rotatieCompensatie);
    }
    if (rotation == null) return null;

    final format = InputImageFormatValue.fromRawValue(image.format.raw as int);
    if (format == null) return null;
    if (Platform.isAndroid && format != InputImageFormat.nv21) return null;
    if (Platform.isIOS && format != InputImageFormat.bgra8888) return null;
    if (image.planes.length != 1) return null;

    final vlak = image.planes.first;
    return InputImage.fromBytes(
      bytes: vlak.bytes,
      metadata: InputImageMetadata(
        size: Size(image.width.toDouble(), image.height.toDouble()),
        rotation: rotation,
        format: format,
        bytesPerRow: vlak.bytesPerRow,
      ),
    );
  }
}

class _NormPositie {
  final double x;
  final double y;
  const _NormPositie(this.x, this.y);
}

/// `kIsWeb` bestaat al in Flutter; hier houden we ook rekening met
/// desktop-platforms (Windows/macOS/Linux) waarop ML Kit niet werkt.
bool get kIsWebOrDesktop {
  try {
    return Platform.isWindows || Platform.isLinux || Platform.isMacOS;
  } catch (_) {
    return true; // bv. web: Platform gooit een exceptie
  }
}
