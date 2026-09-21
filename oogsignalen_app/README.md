# Oogsignalen App (NLP-oefentool op basis van het Dilts-model)

Een Flutter-app (Android, iOS, iPadOS, Windows) die tijdens een vraag-en-
antwoordgesprek de blikrichting van de gesprekspartner probeert te
detecteren en interpreteert volgens het "oog-accessing cues"-model van
Robert Dilts (NLP), en die er automatisch een verslag (PDF) van kan
genereren op basis van een audio-opname per antwoord.

## Lees dit eerst: wat deze app wél en niét is

**Dit is geen leugendetector.** Het model van Dilts (bijv. "omhoog-links =
herinnering, omhoog-rechts = fantasie") is **niet wetenschappelijk
aangetoond**. Gecontroleerd onderzoek (o.a. Wiseman, Watt & ten Brinke,
*"The Eyes Don't Have It"*, PLOS ONE 2012) vond géén verband tussen
oogbewegingsrichting en waarheid of liegen.

Deze app is gebouwd als **NLP-georiënteerd oefen-/reflectiehulpmiddel**
(bijv. voor wie met dit model wil oefenen of het zelf empirisch wil
uitproberen), **niet** als bewijsmiddel. De disclaimer staat daarom
overal in de app zichtbaar: op het hoofdscherm, tijdens een live sessie,
in het sessieoverzicht én bovenaan elk gegenereerd PDF-verslag. Gebruik de
uitkomsten nooit als bewijs richting een persoon (verhoor, sollicitatie,
relatieconflict, rechtszaak, etc.).

## Hoe de app werkt

1. **Sessie starten** - kies een titel en een "Dilts-profiel"
   (standaard, of gespiegeld voor mensen bij wie het model - volgens
   Dilts zelf vaker bij linkshandigen - andersom lijkt te werken).
2. **Vraag markeren** - tik op "Markeer nieuwe vraag" en typ eventueel de
   vraagtekst.
3. **Antwoord opnemen (push-to-talk)** - houd de ronde microfoonknop
   ingedrukt zolang de gesprekspartner antwoordt. Dat is het mechanisme
   waarmee de app weet *wanneer* een antwoord loopt: precies zolang de
   knop wordt vastgehouden, wordt er audio opgenomen én wordt de
   waargenomen oogrichting gelogd (met tijdstempel t.o.v. de start van
   het antwoord). Laat de knop los om te stoppen.
   - *Waarom geen automatische spraakherkenning die zelf detecteert
     wanneer een vraag wordt gesteld?* Dat zou losse spraak-naar-tekst-
     en vraagclassificatie vereisen die onbetrouwbaar en foutgevoelig is
     voor een eerste werkende versie. De ingedrukt-houden-knop die in de
     opdracht zelf als alternatief werd genoemd, is expliciet en
     betrouwbaar, en is daarom als primair mechanisme gekozen.
4. **Automatische detectie (Android/iOS/iPadOS)** - de voorcamera +
   Google ML Kit Face Detection lokaliseren het gezicht en de
   oogomtrekken; binnen elk oog wordt de donkerste pixelcluster gezocht
   als schatting van de iris/pupilpositie (een eenvoudige, klassieke
   beeldverwerkingsheuristiek - géén deep-learning iris-tracking). Bij
   een te sterk gedraaid hoofd of (bijna) gesloten ogen wordt de meting
   als "onbekend" gemarkeerd i.p.v. geraden.
5. **Handmatige modus (Windows)** - Google ML Kit heeft geen
   Windows-implementatie. Op Windows toont de app in plaats daarvan een
   3x3-knoppenraster; tik tijdens de opname op de richting die je zelf
   waarneemt (bijv. via een losse camera/videobeeld).
6. **Verslag** - open een sessie en tik op het PDF-icoon. Het verslag
   bevat per vraag: de vraagtekst, het tijdstip, de duur van het antwoord,
   het pad naar de audio-opname, de dominante oogrichting, de
   Dilts-interpretatie én de disclaimer. Op mobiel wordt het gedeeld via
   het systeemdeelmenu, op Windows via het print/opslaan-dialoog.

Alles blijft lokaal op het apparaat (Hive-database + audiobestanden in de
documentenmap van de app); er wordt niets naar een server verstuurd.

## Architectuur

```
lib/
  models/
    eye_direction.dart   # 9-richtingen-model + Dilts-interpretatietabel
    qa_entry.dart         # Eén vraag/antwoord-item + oogmetingen (Hive)
    session.dart           # Een sessie = lijst van qa_entry's (Hive)
  services/
    eye_tracking_service.dart  # Camera + ML Kit + iris-heuristiek
    audio_service.dart          # Push-to-talk opname (package:record)
    storage_service.dart        # Lokale Hive-opslag
    report_service.dart         # PDF-verslag (package:pdf + printing)
    app_services.dart            # Simpele singletons
  screens/
    home_screen.dart            # Sessieoverzicht + nieuwe sessie
    session_screen.dart          # Live sessie (camera/opname/markering)
    session_detail_screen.dart   # Overzicht + afspelen + PDF-export
  widgets/
    dilts_diagram.dart           # 3x3-referentieschema
```

## Platformstatus

| Platform        | Automatische oogdetectie | Audio-opname | PDF-verslag |
|-----------------|:-------------------------:|:-------------:|:------------:|
| Android         | ✅ (ML Kit)                | ✅             | ✅            |
| iOS / iPadOS     | ✅ (ML Kit)                | ✅             | ✅            |
| Windows          | ⚠️ handmatige modus        | ✅             | ✅            |

## Bouwen en draaien

Vereist de Flutter SDK (stable channel). In deze projectmap:

```bash
flutter pub get

# Android (emulator of toestel aangesloten)
flutter run -d android

# iOS / iPadOS (macOS + Xcode vereist)
flutter run -d ios

# Windows (Windows + Visual Studio "Desktop development with C++" vereist)
flutter run -d windows
```

Platformkanttekeningen:
- **Android**: camera- en microfoonpermissies staan in
  `android/app/src/main/AndroidManifest.xml`.
- **iOS/iPadOS**: `NSCameraUsageDescription` en `NSMicrophoneUsageDescription`
  staan in `ios/Runner/Info.plist`.
- **Windows**: is een Win32-app (geen MSIX); Windows vraagt bij het eerste
  gebruik zelf om camera-/microfoontoegang via de systeem-privacyinstellingen.
  Controleer **Instellingen > Privacy en beveiliging > Camera/Microfoon**
  als de knoppen niets lijken te doen.

## Tests

```bash
flutter analyze
flutter test
```

`test/eye_direction_test.dart` toetst de Dilts-interpretatiemapping
(inclusief spiegeling voor het "gespiegelde" profiel).

## Bekende beperkingen

- De iris-schatting is een eenvoudige pixelintensiteitsheuristiek, geen
  gevalideerde eye-tracker; bij tegenlicht, een bril met glans, of weinig
  contrast tussen iris en oogwit kan de schatting onbetrouwbaar zijn (de
  app markeert zulke gevallen zoveel mogelijk als "onbekend").
- Er is geen automatische spraak-naar-tekst; vraagteksten worden handmatig
  getypt.
- Windows heeft geen automatische detectie (zie hierboven).
- Dit is, nogmaals, **geen leugendetector**: gebruik de app alleen zoals
  hierboven beschreven.
