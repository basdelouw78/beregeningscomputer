import 'dart:typed_data';

import 'package:intl/intl.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

import '../models/eye_direction.dart';
import '../models/qa_entry.dart';
import '../models/session.dart';

/// Genereert een PDF-verslag van een sessie: per vraag/antwoord de
/// waargenomen oogrichting, de bijbehorende Dilts-interpretatie en een
/// verwijzing naar de audio-opname - inclusief de wetenschappelijke
/// disclaimer, zodat die nooit los van het verslag kan worden gelezen.
class ReportService {
  final DateFormat _datumFormaat = DateFormat('d MMMM yyyy, HH:mm', 'nl_NL');

  Future<Uint8List> genereerPdf(OogSessie sessie) async {
    final doc = pw.Document();

    doc.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.a4,
        build: (context) => [
          pw.Header(
            level: 0,
            child: pw.Text(
              sessie.titel,
              style:
                  pw.TextStyle(fontSize: 22, fontWeight: pw.FontWeight.bold),
            ),
          ),
          pw.Text('Sessie aangemaakt op: ${_datumFormaat.format(sessie.aangemaaktOp)}'),
          pw.Text(
            'Dilts-profiel: ${sessie.diltsProfiel == DiltsProfiel.standaard ? "Standaard (rechtshandig)" : "Gespiegeld"}',
          ),
          pw.SizedBox(height: 12),
          pw.Container(
            padding: const pw.EdgeInsets.all(8),
            decoration: pw.BoxDecoration(
              border: pw.Border.all(color: PdfColors.red300),
              borderRadius: const pw.BorderRadius.all(pw.Radius.circular(4)),
            ),
            child: pw.Text(
              diltsDisclaimer,
              style: const pw.TextStyle(fontSize: 9, color: PdfColors.red900),
            ),
          ),
          pw.SizedBox(height: 16),
          if (sessie.items.isEmpty)
            pw.Text('Deze sessie bevat nog geen vraag/antwoord-items.'),
          ...sessie.items
              .asMap()
              .entries
              .expand((entry) => _bouwItem(entry.key + 1, entry.value)),
        ],
      ),
    );

    return doc.save();
  }

  List<pw.Widget> _bouwItem(int nummer, QaEntry item) {
    final richting = item.dominanteRichting;
    final interpretatie = interpreteerRichting(richting);
    return [
      pw.Text(
        'Vraag $nummer',
        style: pw.TextStyle(fontSize: 14, fontWeight: pw.FontWeight.bold),
      ),
      pw.Text(
        item.vraagTekst.trim().isEmpty
            ? '(geen vraagtekst genoteerd)'
            : item.vraagTekst,
      ),
      pw.SizedBox(height: 4),
      pw.Text(
        'Tijdstip vraag: ${_datumFormaat.format(item.vraagTijdstip)}',
        style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
      ),
      if (item.antwoordDuur != null)
        pw.Text(
          'Duur antwoord: ${item.antwoordDuur!.inSeconds} sec.',
          style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
        ),
      if (item.audioPad != null)
        pw.Text(
          'Audio-opname opgeslagen op dit apparaat: ${item.audioPad}',
          style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey700),
        ),
      pw.SizedBox(height: 4),
      pw.Text('Dominante oogrichting tijdens antwoord: ${richting.labelNl}'),
      pw.Text(
        'Interpretatie (Dilts-model, code ${interpretatie.code}): ${interpretatie.titel}',
        style: pw.TextStyle(fontWeight: pw.FontWeight.bold),
      ),
      pw.Text(interpretatie.beschrijving, style: const pw.TextStyle(fontSize: 10)),
      if (item.notitie != null && item.notitie!.trim().isNotEmpty)
        pw.Text('Notitie: ${item.notitie}'),
      pw.SizedBox(height: 4),
      pw.Text(
        'Aantal metingen tijdens dit antwoord: ${item.oogSamples.length} '
        '(${(item.onbekendAandeel * 100).toStringAsFixed(0)}% onbekend/onbetrouwbaar)',
        style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey600),
      ),
      pw.SizedBox(height: 8),
      pw.Divider(),
      pw.SizedBox(height: 8),
    ];
  }

  /// Deelt het PDF-bestand via het platform-share-menu (e-mail, opslaan, ...).
  Future<bool> deelPdf(Uint8List bytes, String bestandsnaam) {
    return Printing.sharePdf(bytes: bytes, filename: bestandsnaam);
  }

  /// Opent het systeem-print/opslaan-dialoog (handig op Windows, waar delen
  /// via het os-share-menu niet altijd beschikbaar is).
  Future<bool> printOfBewaarPdf(Uint8List bytes, String naam) {
    return Printing.layoutPdf(onLayout: (format) async => bytes, name: naam);
  }
}
