import 'dart:async';
import 'dart:io';

import 'package:camera/camera.dart';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

import '../models/eye_direction.dart';
import '../models/qa_entry.dart';
import '../models/session.dart';
import '../services/app_services.dart';
import '../services/audio_service.dart';
import '../services/eye_tracking_service.dart';

/// Live sessiescherm: hier worden vragen gemarkeerd en antwoorden
/// opgenomen. Tijdens het ingedrukt houden van de opnameknop wordt zowel
/// audio als (indien beschikbaar) de waargenomen oogrichting gelogd, zodat
/// achteraf precies te herleiden is welke oogsignalen bij welk antwoord
/// hoorden.
class SessionScreen extends StatefulWidget {
  final OogSessie sessie;

  const SessionScreen({super.key, required this.sessie});

  @override
  State<SessionScreen> createState() => _SessionScreenState();
}

class _SessionScreenState extends State<SessionScreen> {
  final _opslag = AppServices.instance.opslag;
  late final AudioService _audioService = AppServices.instance.nieuweAudioService();
  EyeTrackingService? _eyeService;
  StreamSubscription<EyeTrackingFrame>? _eyeSub;

  final _vraagTekstController = TextEditingController();

  bool _gebruikHandmatigeModus = !EyeTrackingService.automatischeDetectieBeschikbaar;
  bool _cameraKlaar = false;
  String? _initFout;

  EyeDirection _huidigeRichting = EyeDirection.onbekend;
  bool _opnameBezig = false;
  DateTime? _antwoordStart;
  final List<EyeSample> _buffer = [];
  QaEntry? _actieveVraag;

  @override
  void initState() {
    super.initState();
    _initialiseer();
  }

  Future<void> _initialiseer() async {
    if (Platform.isAndroid || Platform.isIOS) {
      await [Permission.camera, Permission.microphone].request();
    }

    if (!_gebruikHandmatigeModus) {
      final service = EyeTrackingService();
      try {
        await service.start();
        _eyeService = service;
        _eyeSub = service.stream.listen(_opEyeFrame);
        if (mounted) setState(() => _cameraKlaar = true);
      } catch (e) {
        await service.dispose();
        if (mounted) {
          setState(() {
            _gebruikHandmatigeModus = true;
            _initFout = 'Automatische oogdetectie kon niet starten: $e. '
                'Je kunt de richting handmatig aangeven.';
          });
        }
      }
    }
  }

  void _opEyeFrame(EyeTrackingFrame frame) {
    if (!mounted) return;
    setState(() => _huidigeRichting = frame.richting);
    if (_opnameBezig && _antwoordStart != null) {
      _buffer.add(EyeSample(
        millisSindsStartAntwoord:
            DateTime.now().difference(_antwoordStart!).inMilliseconds,
        richting: frame.richting,
      ));
    }
  }

  void _handmatigeRichtingGetikt(EyeDirection richting) {
    if (!_opnameBezig || _antwoordStart == null) return;
    setState(() => _huidigeRichting = richting);
    _buffer.add(EyeSample(
      millisSindsStartAntwoord:
          DateTime.now().difference(_antwoordStart!).inMilliseconds,
      richting: richting,
    ));
  }

  void _nieuweVraag() {
    setState(() {
      _actieveVraag = QaEntry(vraagTekst: _vraagTekstController.text.trim());
      widget.sessie.items.add(_actieveVraag!);
    });
  }

  Future<void> _startOpname() async {
    if (_opnameBezig) return;
    _actieveVraag ??= () {
      final entry = QaEntry(vraagTekst: _vraagTekstController.text.trim());
      widget.sessie.items.add(entry);
      return entry;
    }();

    final heeftToestemming = await _audioService.heeftMicrofoonToestemming();
    if (!heeftToestemming) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Geen microfoontoestemming gekregen.')),
        );
      }
      return;
    }

    _buffer.clear();
    _antwoordStart = DateTime.now();
    try {
      await _audioService.startOpname(
        sessieId: widget.sessie.id,
        entryIndex: widget.sessie.items.length - 1,
      );
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Kon opname niet starten: $e')),
        );
      }
      return;
    }
    setState(() => _opnameBezig = true);
  }

  Future<void> _stopOpname() async {
    if (!_opnameBezig) return;
    final pad = await _audioService.stopOpname();
    final entry = _actieveVraag;
    if (entry != null) {
      entry.antwoordStart = _antwoordStart;
      entry.antwoordEind = DateTime.now();
      entry.audioPad = pad;
      entry.oogSamples = List.of(_buffer);
      await _opslag.bewaarSessie(widget.sessie);
    }
    setState(() {
      _opnameBezig = false;
      _actieveVraag = null;
      _antwoordStart = null;
    });
    _vraagTekstController.clear();
  }

  @override
  void dispose() {
    _eyeSub?.cancel();
    _eyeService?.dispose();
    _audioService.dispose();
    _vraagTekstController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final interpretatie = interpreteerRichting(
      _huidigeRichting,
      profiel: widget.sessie.diltsProfiel,
    );

    return Scaffold(
      appBar: AppBar(title: Text(widget.sessie.titel)),
      body: SafeArea(
        child: Column(
          children: [
            if (_initFout != null)
              Container(
                width: double.infinity,
                color: Theme.of(context).colorScheme.errorContainer,
                padding: const EdgeInsets.all(8),
                child: Text(_initFout!,
                    style: TextStyle(
                        color: Theme.of(context).colorScheme.onErrorContainer)),
              ),
            Expanded(
              flex: 3,
              child: _gebruikHandmatigeModus
                  ? _handmatigPaneel()
                  : _cameraPaneel(),
            ),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  Text('Live richting: ${_huidigeRichting.labelNl}'),
                  Text(
                    '${interpretatie.code} · ${interpretatie.titel}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  TextField(
                    controller: _vraagTekstController,
                    enabled: !_opnameBezig,
                    decoration: const InputDecoration(
                      labelText: 'Vraagtekst (optioneel)',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: _opnameBezig ? null : _nieuweVraag,
                          icon: const Icon(Icons.help_outline),
                          label: const Text('Markeer nieuwe vraag'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  _opnameKnop(),
                  const SizedBox(height: 4),
                  Text(
                    _opnameBezig
                        ? 'Opname loopt... laat los om te stoppen'
                        : 'Houd ingedrukt tijdens het antwoord',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              flex: 2,
              child: _itemsLijst(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _cameraPaneel() {
    final controller = _eyeService?.controller;
    if (!_cameraKlaar || controller == null || !controller.value.isInitialized) {
      return const Center(child: CircularProgressIndicator());
    }
    return ClipRect(
      child: FittedBox(
        fit: BoxFit.cover,
        child: SizedBox(
          width: controller.value.previewSize?.height ?? 1,
          height: controller.value.previewSize?.width ?? 1,
          child: CameraPreview(controller),
        ),
      ),
    );
  }

  Widget _handmatigPaneel() {
    const rijen = [
      [EyeDirection.upLeft, EyeDirection.upCenter, EyeDirection.upRight],
      [EyeDirection.midLeft, EyeDirection.center, EyeDirection.midRight],
      [EyeDirection.downLeft, EyeDirection.downCenter, EyeDirection.downRight],
    ];
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Text(
            'Automatische detectie is hier niet beschikbaar. Tik tijdens de '
            'opname op de richting die je waarneemt.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          for (final rij in rijen)
            Row(
              children: [
                for (final richting in rij)
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.all(4),
                      child: OutlinedButton(
                        onPressed:
                            _opnameBezig ? () => _handmatigeRichtingGetikt(richting) : null,
                        style: OutlinedButton.styleFrom(
                          backgroundColor: _huidigeRichting == richting
                              ? Theme.of(context).colorScheme.primaryContainer
                              : null,
                        ),
                        child: Text(richting.labelNl, textAlign: TextAlign.center),
                      ),
                    ),
                  ),
              ],
            ),
        ],
      ),
    );
  }

  Widget _opnameKnop() {
    return Listener(
      onPointerDown: (_) => _startOpname(),
      onPointerUp: (_) => _stopOpname(),
      onPointerCancel: (_) => _stopOpname(),
      child: Container(
        height: 72,
        width: 72,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: _opnameBezig
              ? Colors.red
              : Theme.of(context).colorScheme.primary,
        ),
        child: Icon(
          _opnameBezig ? Icons.stop : Icons.mic,
          color: Colors.white,
          size: 32,
        ),
      ),
    );
  }

  Widget _itemsLijst() {
    final items = widget.sessie.items;
    if (items.isEmpty) {
      return const Center(child: Text('Nog geen vraag/antwoord-items.'));
    }
    return ListView.builder(
      itemCount: items.length,
      itemBuilder: (context, index) {
        final item = items[items.length - 1 - index];
        final interpretatie = interpreteerRichting(
          item.dominanteRichting,
          profiel: widget.sessie.diltsProfiel,
        );
        return ListTile(
          dense: true,
          leading: Text('#${items.length - index}'),
          title: Text(
            item.vraagTekst.trim().isEmpty ? '(geen vraagtekst)' : item.vraagTekst,
          ),
          subtitle: Text('${item.dominanteRichting.labelNl} · ${interpretatie.titel}'),
        );
      },
    );
  }
}
