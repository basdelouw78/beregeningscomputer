/// Model rond de "oog-accessing cues" van Robert Dilts (NLP).
///
/// BELANGRIJK - lees dit voordat je verder leest in deze file:
/// Er is GEEN wetenschappelijk bewijs dat oogbewegingsrichting iets zegt over
/// of iemand liegt. Gecontroleerd onderzoek (o.a. Wiseman, Watt & ten Brinke,
/// PLOS ONE 2012) vond geen verband tussen oogbeweging en waarheid/leugen.
/// Dit model wordt hier uitsluitend geïmplementeerd als NLP-georiënteerd
/// reflectie-/trainingshulpmiddel, nadrukkelijk NIET als leugendetector.
/// Zie [diltsDisclaimer] - deze tekst moet overal zichtbaar blijven waar
/// een interpretatie aan een gebruiker wordt getoond.
library;

const String diltsDisclaimer =
    'Dit is GEEN leugendetector. Het "oogbewegingsmodel" van Robert Dilts '
    '(NLP) is niet wetenschappelijk aangetoond: gecontroleerd onderzoek '
    '(o.a. Wiseman e.a., 2012) vond geen verband tussen oogbewegingsrichting '
    'en waarheid of liegen. Gebruik deze app alleen als speels/reflectief '
    'NLP-oefenhulpmiddel - nooit als bewijs richting een persoon, bij een '
    'verhoor, sollicitatie, relatieconflict of juridische kwestie.';

/// Grofmazige richting van de iris/pupil binnen de oogomtrek, zoals
/// WAARGENOMEN DOOR DE CAMERA (dus in schermruimte: "links" = links in
/// beeld). Omdat de camera de persoon frontaal aankijkt, is dit gespiegeld
/// t.o.v. de eigen linker-/rechterkant van de persoon.
enum EyeDirection {
  upLeft,
  upCenter,
  upRight,
  midLeft,
  center,
  midRight,
  downLeft,
  downCenter,
  downRight,
  onbekend,
}

extension EyeDirectionLabel on EyeDirection {
  String get labelNl => switch (this) {
        EyeDirection.upLeft => 'Omhoog-links (beeld)',
        EyeDirection.upCenter => 'Omhoog-midden (beeld)',
        EyeDirection.upRight => 'Omhoog-rechts (beeld)',
        EyeDirection.midLeft => 'Midden-links (beeld)',
        EyeDirection.center => 'Recht vooruit / centraal',
        EyeDirection.midRight => 'Midden-rechts (beeld)',
        EyeDirection.downLeft => 'Omlaag-links (beeld)',
        EyeDirection.downCenter => 'Omlaag-midden (beeld)',
        EyeDirection.downRight => 'Omlaag-rechts (beeld)',
        EyeDirection.onbekend => 'Onbekend / geen gezicht gedetecteerd',
      };
}

/// Sommige mensen (Dilts noemt dit vaker bij linkshandigen, maar het is
/// individueel) vertonen een gespiegeld patroon. Laat de gebruiker dit
/// per sessie instellen i.p.v. aannames te doen.
enum DiltsProfiel { standaard, gespiegeld }

class DiltsInterpretatie {
  final String code;
  final String titel;
  final String beschrijving;
  final double zekerheid; // 0..1, indicatief, geen wetenschappelijke maat

  const DiltsInterpretatie({
    required this.code,
    required this.titel,
    required this.beschrijving,
    required this.zekerheid,
  });

  static const onbekend = DiltsInterpretatie(
    code: '-',
    titel: 'Geen interpretatie',
    beschrijving:
        'Er kon geen gezicht/oog worden gedetecteerd op het moment van de vraag.',
    zekerheid: 0,
  );
}

/// Vertaalt een in beeld waargenomen [EyeDirection] naar de klassieke
/// Dilts-interpretatie, rekening houdend met spiegeling (camera vs. eigen
/// perspectief van de persoon) en het gekozen profiel.
DiltsInterpretatie interpreteerRichting(
  EyeDirection richting, {
  DiltsProfiel profiel = DiltsProfiel.standaard,
}) {
  if (richting == EyeDirection.onbekend) return DiltsInterpretatie.onbekend;

  // Stap 1: spiegel schermruimte naar het eigen perspectief van de persoon
  // (camera kijkt de persoon frontaal aan: beeld-links = hun rechterkant).
  final eigenLinks = richting == EyeDirection.upRight ||
      richting == EyeDirection.midRight ||
      richting == EyeDirection.downRight;
  final eigenRechts = richting == EyeDirection.upLeft ||
      richting == EyeDirection.midLeft ||
      richting == EyeDirection.downLeft;

  // Stap 2: pas eventueel het gespiegelde profiel toe.
  final links = profiel == DiltsProfiel.standaard ? eigenLinks : eigenRechts;
  final rechts = profiel == DiltsProfiel.standaard ? eigenRechts : eigenLinks;

  final isBoven = richting == EyeDirection.upLeft ||
      richting == EyeDirection.upCenter ||
      richting == EyeDirection.upRight;
  final isOnder = richting == EyeDirection.downLeft ||
      richting == EyeDirection.downCenter ||
      richting == EyeDirection.downRight;

  if (isBoven && links) {
    return const DiltsInterpretatie(
      code: 'Ve',
      titel: 'Visueel herinnerd',
      beschrijving:
          'Klassieke Dilts-interpretatie: de persoon haalt een eerder gezien '
          'beeld / eerder waargenomen feiten op uit het geheugen.',
      zekerheid: 0.3,
    );
  }
  if (isBoven && rechts) {
    return const DiltsInterpretatie(
      code: 'Vc',
      titel: 'Visueel geconstrueerd',
      beschrijving:
          'Klassieke Dilts-interpretatie: de persoon construeert een nieuw, '
          'nooit eerder gezien beeld (verbeelding, plannen, verzinnen).',
      zekerheid: 0.3,
    );
  }
  if (isBoven) {
    return const DiltsInterpretatie(
      code: 'V?',
      titel: 'Visueel (richting onduidelijk)',
      beschrijving:
          'Blik omhoog zonder duidelijke zijwaartse component: volgens het '
          'model visuele verwerking, zonder onderscheid herinnerd/geconstrueerd.',
      zekerheid: 0.15,
    );
  }
  if (links && !isOnder) {
    return const DiltsInterpretatie(
      code: 'Ae',
      titel: 'Auditief herinnerd',
      beschrijving:
          'Klassieke Dilts-interpretatie: de persoon herinnert zich een eerder '
          'gehoord geluid, eigen eerder gezegde woorden of een gesprek.',
      zekerheid: 0.3,
    );
  }
  if (rechts && !isOnder) {
    return const DiltsInterpretatie(
      code: 'Ac',
      titel: 'Auditief geconstrueerd',
      beschrijving:
          'Klassieke Dilts-interpretatie: de persoon construeert nieuwe '
          'geluiden of woorden die nog niet eerder zo zijn gehoord.',
      zekerheid: 0.3,
    );
  }
  if (isOnder && rechts) {
    return const DiltsInterpretatie(
      code: 'K',
      titel: 'Kinesthetisch',
      beschrijving:
          'Klassieke Dilts-interpretatie: de persoon is in contact met een '
          'gevoel of lichamelijke sensatie.',
      zekerheid: 0.3,
    );
  }
  if (isOnder && links) {
    return const DiltsInterpretatie(
      code: 'Ad',
      titel: 'Auditief digitaal / interne dialoog',
      beschrijving:
          'Klassieke Dilts-interpretatie: de persoon voert een innerlijk '
          'gesprek met zichzelf of redeneert stil.',
      zekerheid: 0.3,
    );
  }
  return const DiltsInterpretatie(
    code: 'C',
    titel: 'Centraal / direct oogcontact',
    beschrijving:
        'Geen zijwaartse of verticale uitwijking gedetecteerd op het moment '
        'van de vraag.',
    zekerheid: 0.1,
  );
}
