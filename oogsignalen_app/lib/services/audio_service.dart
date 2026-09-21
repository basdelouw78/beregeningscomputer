import 'dart:async';
import 'dart:io';

import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';

/// Beheert het opnemen van audio per antwoord ("push-to-talk"): de
/// gebruiker houdt een knop ingedrukt tijdens het geven van een antwoord,
/// zodat de app precies weet wanneer een antwoord begint en eindigt (en dus
/// welk deel van de oogmetingen bij dat antwoord hoort).
class AudioService {
  final AudioRecorder _recorder = AudioRecorder();
  String? _huidigPad;

  Future<bool> heeftMicrofoonToestemming() => _recorder.hasPermission();

  bool get isBezig => _huidigPad != null;

  /// Start een opname voor het antwoord op [sessieId]/[entryIndex]. Geeft
  /// het bestandspad terug waar de opname naartoe wordt geschreven.
  Future<String> startOpname({
    required String sessieId,
    required int entryIndex,
  }) async {
    if (isBezig) {
      throw StateError('Er loopt al een opname.');
    }
    final map = await getApplicationDocumentsDirectory();
    final opnameMap = Directory('${map.path}/oogsignalen_opnames/$sessieId');
    if (!await opnameMap.exists()) {
      await opnameMap.create(recursive: true);
    }
    final bestandsnaam =
        'antwoord_${entryIndex}_${DateTime.now().millisecondsSinceEpoch}.m4a';
    final pad = '${opnameMap.path}/$bestandsnaam';

    await _recorder.start(
      const RecordConfig(encoder: AudioEncoder.aacLc),
      path: pad,
    );
    _huidigPad = pad;
    return pad;
  }

  /// Stopt de lopende opname en geeft het uiteindelijke bestandspad terug
  /// (of null als er niets werd opgenomen).
  Future<String?> stopOpname() async {
    if (!isBezig) return null;
    final pad = await _recorder.stop();
    _huidigPad = null;
    return pad ?? _huidigPad;
  }

  Future<void> annuleerOpname() async {
    if (!isBezig) return;
    try {
      final pad = await _recorder.stop();
      _huidigPad = null;
      if (pad != null) {
        final bestand = File(pad);
        if (await bestand.exists()) await bestand.delete();
      }
    } catch (_) {
      _huidigPad = null;
    }
  }

  Future<void> dispose() async {
    if (isBezig) await annuleerOpname();
    await _recorder.dispose();
  }
}
