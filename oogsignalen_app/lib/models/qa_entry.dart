import 'package:hive/hive.dart';

import 'eye_direction.dart';

/// Eén meting van de waargenomen oogrichting, met tijdstip t.o.v. de start
/// van het antwoord (in milliseconden).
///
/// Hive-adapters voor deze klassen worden hieronder met de hand geschreven
/// (i.p.v. via build_runner-codegen), omdat de codegenerator geen
/// constructor-parameter kan matchen met de afgeleide `richting`-property.
@HiveType(typeId: 1)
class EyeSample extends HiveObject {
  @HiveField(0)
  final int millisSindsStartAntwoord;

  @HiveField(1)
  final int richtingIndex;

  EyeSample({
    required this.millisSindsStartAntwoord,
    required EyeDirection richting,
  }) : richtingIndex = richting.index;

  EyeDirection get richting => EyeDirection.values[richtingIndex];
}

/// Eén vraag-antwoord-item binnen een sessie.
@HiveType(typeId: 2)
class QaEntry extends HiveObject {
  @HiveField(0)
  String vraagTekst;

  @HiveField(1)
  final DateTime vraagTijdstip;

  @HiveField(2)
  DateTime? antwoordStart;

  @HiveField(3)
  DateTime? antwoordEind;

  @HiveField(4)
  String? audioPad;

  @HiveField(5)
  List<EyeSample> oogSamples;

  @HiveField(6)
  String? notitie;

  QaEntry({
    required this.vraagTekst,
    DateTime? vraagTijdstip,
    this.antwoordStart,
    this.antwoordEind,
    this.audioPad,
    List<EyeSample>? oogSamples,
    this.notitie,
  })  : vraagTijdstip = vraagTijdstip ?? DateTime.now(),
        oogSamples = oogSamples ?? [];

  Duration? get antwoordDuur => (antwoordStart != null && antwoordEind != null)
      ? antwoordEind!.difference(antwoordStart!)
      : null;

  /// Meest voorkomende oogrichting tijdens het antwoord (eenvoudige modus).
  /// Dit is GEEN statistische uitspraak over waarheid, slechts een telling.
  EyeDirection get dominanteRichting {
    if (oogSamples.isEmpty) return EyeDirection.onbekend;
    final tellingen = <EyeDirection, int>{};
    for (final sample in oogSamples) {
      tellingen[sample.richting] = (tellingen[sample.richting] ?? 0) + 1;
    }
    var beste = EyeDirection.onbekend;
    var besteTelling = -1;
    tellingen.forEach((richting, telling) {
      if (richting != EyeDirection.onbekend && telling > besteTelling) {
        beste = richting;
        besteTelling = telling;
      }
    });
    return beste;
  }

  /// Aandeel van de samples (0..1) dat geen bruikbare detectie had.
  double get onbekendAandeel {
    if (oogSamples.isEmpty) return 1;
    final onbekend =
        oogSamples.where((s) => s.richting == EyeDirection.onbekend).length;
    return onbekend / oogSamples.length;
  }
}

class EyeSampleAdapter extends TypeAdapter<EyeSample> {
  @override
  final int typeId = 1;

  @override
  EyeSample read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return EyeSample(
      millisSindsStartAntwoord: fields[0] as int,
      richting: EyeDirection.values[fields[1] as int],
    );
  }

  @override
  void write(BinaryWriter writer, EyeSample obj) {
    writer
      ..writeByte(2)
      ..writeByte(0)
      ..write(obj.millisSindsStartAntwoord)
      ..writeByte(1)
      ..write(obj.richtingIndex);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is EyeSampleAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}

class QaEntryAdapter extends TypeAdapter<QaEntry> {
  @override
  final int typeId = 2;

  @override
  QaEntry read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return QaEntry(
      vraagTekst: fields[0] as String,
      vraagTijdstip: fields[1] as DateTime?,
      antwoordStart: fields[2] as DateTime?,
      antwoordEind: fields[3] as DateTime?,
      audioPad: fields[4] as String?,
      oogSamples: (fields[5] as List).cast<EyeSample>(),
      notitie: fields[6] as String?,
    );
  }

  @override
  void write(BinaryWriter writer, QaEntry obj) {
    writer
      ..writeByte(7)
      ..writeByte(0)
      ..write(obj.vraagTekst)
      ..writeByte(1)
      ..write(obj.vraagTijdstip)
      ..writeByte(2)
      ..write(obj.antwoordStart)
      ..writeByte(3)
      ..write(obj.antwoordEind)
      ..writeByte(4)
      ..write(obj.audioPad)
      ..writeByte(5)
      ..write(obj.oogSamples)
      ..writeByte(6)
      ..write(obj.notitie);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is QaEntryAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}
