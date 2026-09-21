import 'dart:io';

import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/eye_direction.dart';
import '../models/qa_entry.dart';
import '../models/session.dart';
import '../services/app_services.dart';

/// Overzicht van een (afgeronde of lopende) sessie: alle vraag/antwoord-
/// items met hun Dilts-interpretatie, audio-afspeelknop, en de mogelijkheid
/// om er een PDF-verslag van te genereren en te delen/printen.
class SessionDetailScreen extends StatefulWidget {
  final String sessieId;

  const SessionDetailScreen({super.key, required this.sessieId});

  @override
  State<SessionDetailScreen> createState() => _SessionDetailScreenState();
}

class _SessionDetailScreenState extends State<SessionDetailScreen> {
  final _opslag = AppServices.instance.opslag;
  final _verslag = AppServices.instance.verslag;
  final _speler = AudioPlayer();
  final _datumFormaat = DateFormat('d MMM yyyy, HH:mm', 'nl_NL');

  OogSessie? _sessie;
  bool _bezigMetExporteren = false;

  @override
  void initState() {
    super.initState();
    _sessie = _opslag.sessieById(widget.sessieId);
  }

  @override
  void dispose() {
    _speler.dispose();
    super.dispose();
  }

  Future<void> _speelAf(String? pad) async {
    if (pad == null) return;
    final bestand = File(pad);
    if (!await bestand.exists()) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Audiobestand niet gevonden.')),
        );
      }
      return;
    }
    await _speler.stop();
    await _speler.play(DeviceFileSource(pad));
  }

  Future<void> _exporteer() async {
    final sessie = _sessie;
    if (sessie == null) return;
    setState(() => _bezigMetExporteren = true);
    try {
      final bytes = await _verslag.genereerPdf(sessie);
      final bestandsnaam =
          'oogsignalen_${sessie.titel.replaceAll(RegExp(r'[^A-Za-z0-9_-]'), '_')}.pdf';
      if (Platform.isWindows || Platform.isLinux || Platform.isMacOS) {
        await _verslag.printOfBewaarPdf(bytes, bestandsnaam);
      } else {
        await _verslag.deelPdf(bytes, bestandsnaam);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Exporteren mislukt: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _bezigMetExporteren = false);
    }
  }

  Future<void> _bewerkVraagTekst(QaEntry item) async {
    final controller = TextEditingController(text: item.vraagTekst);
    final nieuweTekst = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Vraagtekst bewerken'),
        content: TextField(controller: controller, autofocus: true, maxLines: 3),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Annuleren'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(controller.text),
            child: const Text('Opslaan'),
          ),
        ],
      ),
    );
    if (nieuweTekst == null || _sessie == null) return;
    setState(() => item.vraagTekst = nieuweTekst.trim());
    await _opslag.bewaarSessie(_sessie!);
  }

  @override
  Widget build(BuildContext context) {
    final sessie = _sessie;
    if (sessie == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Sessie')),
        body: const Center(child: Text('Sessie niet gevonden.')),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: Text(sessie.titel),
        actions: [
          IconButton(
            onPressed: _bezigMetExporteren ? null : _exporteer,
            icon: _bezigMetExporteren
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.picture_as_pdf),
            tooltip: 'Exporteer verslag als PDF',
          ),
        ],
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            color: Theme.of(context).colorScheme.errorContainer,
            padding: const EdgeInsets.all(10),
            child: Text(
              diltsDisclaimer,
              style: TextStyle(
                fontSize: 11,
                color: Theme.of(context).colorScheme.onErrorContainer,
              ),
            ),
          ),
          Expanded(
            child: sessie.items.isEmpty
                ? const Center(child: Text('Deze sessie heeft nog geen items.'))
                : ListView.builder(
                    itemCount: sessie.items.length,
                    itemBuilder: (context, index) {
                      final item = sessie.items[index];
                      final interpretatie = interpreteerRichting(
                        item.dominanteRichting,
                        profiel: sessie.diltsProfiel,
                      );
                      return Card(
                        margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      item.vraagTekst.trim().isEmpty
                                          ? '(geen vraagtekst)'
                                          : item.vraagTekst,
                                      style: Theme.of(context).textTheme.titleSmall,
                                    ),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.edit, size: 18),
                                    onPressed: () => _bewerkVraagTekst(item),
                                  ),
                                ],
                              ),
                              Text(
                                _datumFormaat.format(item.vraagTijdstip),
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              const SizedBox(height: 6),
                              Text('${interpretatie.code} · ${interpretatie.titel}',
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodyMedium
                                      ?.copyWith(fontWeight: FontWeight.bold)),
                              Text(interpretatie.beschrijving,
                                  style: Theme.of(context).textTheme.bodySmall),
                              const SizedBox(height: 6),
                              Row(
                                children: [
                                  if (item.audioPad != null)
                                    TextButton.icon(
                                      onPressed: () => _speelAf(item.audioPad),
                                      icon: const Icon(Icons.play_arrow),
                                      label: const Text('Antwoord afspelen'),
                                    ),
                                  if (item.antwoordDuur != null)
                                    Text('${item.antwoordDuur!.inSeconds}s'),
                                ],
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
