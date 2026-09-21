import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/eye_direction.dart';
import '../models/session.dart';
import '../services/app_services.dart';
import '../widgets/dilts_diagram.dart';
import 'session_detail_screen.dart';
import 'session_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _opslag = AppServices.instance.opslag;
  final _datumFormaat = DateFormat('d MMM yyyy, HH:mm', 'nl_NL');
  List<OogSessie> _sessies = [];

  @override
  void initState() {
    super.initState();
    _herlaad();
  }

  void _herlaad() {
    setState(() => _sessies = _opslag.alleSessies());
  }

  Future<void> _nieuweSessie() async {
    final titelController = TextEditingController(
      text: 'Sessie ${DateFormat('d-M-yyyy HH:mm').format(DateTime.now())}',
    );
    var profiel = DiltsProfiel.standaard;

    final aangemaakt = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setStateDialog) => AlertDialog(
          title: const Text('Nieuwe sessie'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              TextField(
                controller: titelController,
                decoration: const InputDecoration(labelText: 'Titel'),
              ),
              const SizedBox(height: 16),
              const Text('Dilts-profiel'),
              RadioListTile<DiltsProfiel>(
                dense: true,
                title: const Text('Standaard (meeste rechtshandigen)'),
                value: DiltsProfiel.standaard,
                groupValue: profiel,
                onChanged: (v) => setStateDialog(() => profiel = v!),
              ),
              RadioListTile<DiltsProfiel>(
                dense: true,
                title: const Text('Gespiegeld (bij sommigen, vaker linkshandigen)'),
                value: DiltsProfiel.gespiegeld,
                groupValue: profiel,
                onChanged: (v) => setStateDialog(() => profiel = v!),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Annuleren'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Starten'),
            ),
          ],
        ),
      ),
    );

    if (aangemaakt != true || !mounted) return;

    final sessie = OogSessie(
      titel: titelController.text.trim().isEmpty
          ? 'Naamloze sessie'
          : titelController.text.trim(),
      diltsProfielIndex: profiel.index,
    );
    await _opslag.bewaarSessie(sessie);
    _herlaad();
    if (!mounted) return;
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => SessionScreen(sessie: sessie)),
    );
    _herlaad();
  }

  Future<void> _verwijder(OogSessie sessie) async {
    await _opslag.verwijderSessie(sessie);
    _herlaad();
  }

  void _toonUitleg() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Over dit model'),
        content: SingleChildScrollView(child: DiltsDiagram()),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Sluiten'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Oogsignalen (NLP-oefentool)'),
        actions: [
          IconButton(
            onPressed: _toonUitleg,
            icon: const Icon(Icons.info_outline),
            tooltip: 'Over het Dilts-model',
          ),
        ],
      ),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            color: Theme.of(context).colorScheme.errorContainer,
            padding: const EdgeInsets.all(12),
            child: Text(
              diltsDisclaimer,
              style: TextStyle(
                color: Theme.of(context).colorScheme.onErrorContainer,
                fontSize: 12,
              ),
            ),
          ),
          Expanded(
            child: _sessies.isEmpty
                ? const Center(child: Text('Nog geen sessies. Start hieronder een nieuwe sessie.'))
                : ListView.builder(
                    itemCount: _sessies.length,
                    itemBuilder: (context, index) {
                      final sessie = _sessies[index];
                      return Dismissible(
                        key: ValueKey(sessie.id),
                        direction: DismissDirection.endToStart,
                        background: Container(
                          color: Theme.of(context).colorScheme.error,
                          alignment: Alignment.centerRight,
                          padding: const EdgeInsets.symmetric(horizontal: 20),
                          child: const Icon(Icons.delete, color: Colors.white),
                        ),
                        onDismissed: (_) => _verwijder(sessie),
                        child: ListTile(
                          title: Text(sessie.titel),
                          subtitle: Text(
                            '${_datumFormaat.format(sessie.aangemaaktOp)} · ${sessie.items.length} vraag/antwoord-item(s)',
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () async {
                            await Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => SessionDetailScreen(sessieId: sessie.id),
                              ),
                            );
                            _herlaad();
                          },
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _nieuweSessie,
        icon: const Icon(Icons.add),
        label: const Text('Nieuwe sessie'),
      ),
    );
  }
}
