import 'package:hive_flutter/hive_flutter.dart';

import '../models/qa_entry.dart';
import '../models/session.dart';

/// Lokale opslag van sessies via Hive. Alle data blijft op het apparaat zelf
/// - er wordt niets naar een server verstuurd.
class StorageService {
  static const String _boxName = 'oog_sessies';
  late Box<OogSessie> _box;
  bool _initialized = false;

  Future<void> init() async {
    if (_initialized) return;
    await Hive.initFlutter();
    if (!Hive.isAdapterRegistered(1)) {
      Hive.registerAdapter(EyeSampleAdapter());
    }
    if (!Hive.isAdapterRegistered(2)) {
      Hive.registerAdapter(QaEntryAdapter());
    }
    if (!Hive.isAdapterRegistered(3)) {
      Hive.registerAdapter(OogSessieAdapter());
    }
    _box = await Hive.openBox<OogSessie>(_boxName);
    _initialized = true;
  }

  OogSessie? sessieById(String id) => _box.get(id);

  List<OogSessie> alleSessies() {
    final lijst = _box.values.toList();
    lijst.sort((a, b) => b.aangemaaktOp.compareTo(a.aangemaaktOp));
    return lijst;
  }

  Future<void> bewaarSessie(OogSessie sessie) async {
    await _box.put(sessie.id, sessie);
  }

  Future<void> verwijderSessie(OogSessie sessie) async {
    await sessie.delete();
  }
}
