import 'package:hive/hive.dart';
import 'package:uuid/uuid.dart';

import 'eye_direction.dart';
import 'qa_entry.dart';

const _uuid = Uuid();

@HiveType(typeId: 3)
class OogSessie extends HiveObject {
  @HiveField(0)
  final String id;

  @HiveField(1)
  String titel;

  @HiveField(2)
  final DateTime aangemaaktOp;

  @HiveField(3)
  int diltsProfielIndex;

  @HiveField(4)
  List<QaEntry> items;

  OogSessie({
    String? id,
    required this.titel,
    DateTime? aangemaaktOp,
    this.diltsProfielIndex = 0,
    List<QaEntry>? items,
  })  : id = id ?? _uuid.v4(),
        aangemaaktOp = aangemaaktOp ?? DateTime.now(),
        items = items ?? [];

  DiltsProfiel get diltsProfiel => DiltsProfiel.values[diltsProfielIndex];
  set diltsProfiel(DiltsProfiel profiel) => diltsProfielIndex = profiel.index;
}

class OogSessieAdapter extends TypeAdapter<OogSessie> {
  @override
  final int typeId = 3;

  @override
  OogSessie read(BinaryReader reader) {
    final numOfFields = reader.readByte();
    final fields = <int, dynamic>{
      for (int i = 0; i < numOfFields; i++) reader.readByte(): reader.read(),
    };
    return OogSessie(
      id: fields[0] as String,
      titel: fields[1] as String,
      aangemaaktOp: fields[2] as DateTime?,
      diltsProfielIndex: fields[3] as int,
      items: (fields[4] as List).cast<QaEntry>(),
    );
  }

  @override
  void write(BinaryWriter writer, OogSessie obj) {
    writer
      ..writeByte(5)
      ..writeByte(0)
      ..write(obj.id)
      ..writeByte(1)
      ..write(obj.titel)
      ..writeByte(2)
      ..write(obj.aangemaaktOp)
      ..writeByte(3)
      ..write(obj.diltsProfielIndex)
      ..writeByte(4)
      ..write(obj.items);
  }

  @override
  int get hashCode => typeId.hashCode;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is OogSessieAdapter &&
          runtimeType == other.runtimeType &&
          typeId == other.typeId;
}
