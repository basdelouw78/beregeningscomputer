# RainMaster

RainMaster is een slimme beregeningscomputer op basis van een ESP32-S3
met een 4" touchscreen. Je bedient je beregeningszones rechtstreeks op
het scherm, en via Home Assistant koppel je de zone-commando's aan de
daadwerkelijke relais/kleppen in je installatie.

Er zijn twee volledig onafhankelijke implementaties in deze repository,
voor dezelfde hardware:
- **Custom firmware** (C++ / PlatformIO) — in de root van de repo.
- **ESPHome-variant** — in `esphome/`.

Kies wat het beste bij je past: de custom firmware geeft je volledige
controle en een kleine footprint; de ESPHome-variant is eenvoudiger te
onderhouden en integreert direct native met Home Assistant.

## Hardware
- **MCU:** ESP32-S3-WROOM-1U-N8R2 (8 MB flash, 2 MB quad-SPI PSRAM)
- **Display:** 4" ILI9488 SPI TFT, 8-pins module inclusief touch (XPT2046) en SD-kaartslot

## Functies
- Acht onafhankelijke beregeningszones, elk met een eigen looptijd
- **Planning per zone**: tot 3 onafhankelijke starttijden, met een eigen
  dagen-van-de-week-selectie — "elke maandag/woensdag/vrijdag om 06:00
  en 19:00" is bijvoorbeeld mogelijk
- **Regenstop**: beregening tijdelijk (in dagen) volledig uitstellen
- **Waterbudget/seizoensaanpassing**: alle geplande looptijden met een
  percentage schalen (bv. 50% in het najaar, 130% in een hittegolf)
- **Zone in-/uitschakelen**: een zone tijdelijk uitsluiten van de
  cyclus en planning (handmatig testen blijft altijd mogelijk)
- Automatische cyclus die alle ingeschakelde zones na elkaar doorloopt
- Handmatig een enkele zone starten/stoppen vanaf het touchscreen
- Live countdown van de resterende looptijd + geschiedenis per zone
  (laatste keer dat een zone liep)
- Home Assistant-integratie voor bediening, planning en automatisering
  op afstand
- WiFi-fallback met een tijdelijk toegangspunt als er geen verbinding is
- OTA-firmware-updates

## Planning & automatisering

De planning werkt hetzelfde in beide firmware-varianten en is bewust
vormgegeven zoals bij bestaande beregeningscomputers (Rain Bird, Hunter,
RainMachine e.d.):

| Functie | RainMaster |
|---|---|
| Meerdere starttijden per zone | Ja, tot 3 per zone |
| Dagen-van-de-week-selectie | Ja, per zone onafhankelijk |
| Automatische cyclus (alle zones na elkaar) | Ja |
| Regenstop (tijdelijk alles uitstellen) | Ja, 0-14 dagen |
| Waterbudget/seizoensaanpassing (%) | Ja, 50-150% |
| Zone in-/uitschakelen | Ja |
| Handmatige zone-test | Ja, altijd mogelijk (ook als zone uitgeschakeld is) |
| Laatste-run-geschiedenis per zone | Ja |
| Fysieke regensensor-ingang | Nee — dit board heeft er geen; koppel in plaats daarvan de "Regenstop"-entiteit aan een Home Assistant-regen/weer-automatisering (zie hieronder) |
| Master-klep/pomprelais-uitgang | Nee — dit board stuurt geen relais rechtstreeks aan; gebruik de "Beregening actief"-entiteit om via Home Assistant een pomprelais te schakelen |

**Wat de planning WEL en NIET regelt:** de scheduler start/stopt zones
zelf, ook zonder dat Home Assistant online is (de instellingen staan
opgeslagen op het apparaat). Home Assistant is dus geen vereiste voor
dagelijks gebruik — het is de plek waar je de planning instelt en waar
je automatiseringen (bv. gekoppeld aan een weerstation) bovenop bouwt.

**Dagmasker-formaat**: cijfers `1`-`7` voor maandag t/m zondag, bv.
`1357` = maandag/woensdag/vrijdag/zondag. **Starttijd-formaat**: `HH:MM`
(24-uurs), bv. `06:30`; `--:--` of een lege/ongeldige waarde betekent
"geen starttijd".

Het touchscreen zelf heeft geen planningseditor (net zoals looptijden
al alleen op afstand instelbaar waren) — je stelt de planning in via
Home Assistant of, bij de custom firmware, rechtstreeks via MQTT. Het
instellingenscherm toont wel de regenstop-status en het waterbudget,
en bij de custom firmware ook de eerstvolgende geplande beurt.

## Schermen

RainMaster heeft vier schermen: een opstartscherm, het startscherm (in
twee toestanden), zone-selectie en instellingen. De onderstaande
afbeeldingen zijn representatieve weergaves van de interface.

### Opstartscherm
<img src="docs/screens/splash.svg" alt="RainMaster opstartscherm met druppel-logo op een blauw-naar-groen verloop" width="480">

Bij het inschakelen toont het scherm 2,5 seconden lang het RainMaster-logo
op een verloop van diep water-blauw naar fris groen — het beeldmerk voor
"water dat groei voedt".

### Startscherm — gereed
<img src="docs/screens/home-idle.svg" alt="Startscherm in rust, met knoppen START CYCLUS, ZONE KIEZEN, AUTO en INSTELLINGEN" width="480">

Toont de status (WiFi/Home Assistant-verbinding bovenin, automatische
modus aan/uit) en geeft toegang tot een volledige cyclus, losse zones,
het aan/uit zetten van de automatische modus en de instellingen.

### Startscherm — actief
<img src="docs/screens/home-running.svg" alt="Startscherm tijdens beregening, met actieve zone en aftellende timer" width="480">

Zodra een zone loopt zie je direct welke zone actief is en hoeveel tijd
er nog resteert. Met één druk op de knop stop je de volledige cyclus.

### Zone-overzicht
<img src="docs/screens/zones.svg" alt="Overzicht van alle 8 zones met looptijd, met de actieve zone gemarkeerd" width="480">

Alle acht zones op één scherm, met hun ingestelde looptijd. Tik op een
zone om die direct te starten; de actief lopende zone is gemarkeerd.

### Instellingen
<img src="docs/screens/settings.svg" alt="Instellingenscherm met WiFi- en MQTT/verbindingsstatus" width="480">

Overzicht van de netwerkstatus. Looptijden en verbindingsinstellingen
wijzig je via de webconfiguratie (custom firmware) of via Home Assistant
(ESPHome-variant).

---

## Custom firmware (PlatformIO)

```
pio run
pio run -t upload
pio device monitor
```

De TFT_eSPI-configuratie (ILI9488, pinnen, fonts) staat als `build_flags`
in `platformio.ini`, in plaats van een `User_Setup.h` in de library.

**Pas de SPI-pinnen (`TFT_CS`, `TFT_DC`, `TFT_RST`, `TFT_MISO`, `TFT_MOSI`,
`TFT_SCLK`, `TOUCH_CS`, `SD_CS`) in `platformio.ini` aan naar je eigen
bedrading** — de huidige waarden zijn placeholders.

### Libraries
- TFT_eSPI
- PubSubClient
- ElegantOTA
- ESP32 Arduino core (WiFi, WebServer, Preferences, DNSServer)

### Webconfiguratie & OTA
De webconfiguratiepagina (WiFi/MQTT instellen, herstarten) en OTA-updates
(`/update`) zijn beveiligd met HTTP Basic Auth. Standaard inloggegevens
(`config.h`, `WEBUI_USER`/`WEBUI_PASS`): gebruiker `admin`, wachtwoord
`beregening123` — **wijzig dit wachtwoord voor je het apparaat op een
netwerk aansluit.**

### MQTT
Discovery wordt automatisch aangemaakt voor automatische modus, status,
actieve zone, resterende tijd, regenstop, waterbudget, "beregening
actief" en eerstvolgende beurt, en per zone: aan/uit, looptijd,
ingeschakeld, dagmasker, 3 starttijden en laatste-run-tijdstip.

Commando's (algemeen):
- `beregening/command/start` = `CYCLE` of `1`..`8`
- `beregening/command/stop` = willekeurige payload
- `beregening/command/auto` = `ON`/`OFF`
- `beregening/command/raindelay/set` = dagen (0-14)
- `beregening/command/waterbudget/set` = percentage (50-150)

Commando's (per zone `N` = 1-8):
- `beregening/zone/N/command` = `ON`/`OFF`
- `beregening/zone/N/runtime/set` = seconden
- `beregening/zone/N/enabled/set` = `ON`/`OFF`
- `beregening/zone/N/schedule/daymask/set` = bv. `1357` (ma/wo/vr/zo)
- `beregening/zone/N/schedule/start1/set` (en `start2`/`start3`) = `HH:MM` of `--:--`

Status-topics volgen dezelfde padnamen zonder `/set` (bv.
`beregening/status/raindelay`, `beregening/zone/N/schedule/daymask`,
`beregening/zone/N/last_run`, `beregening/status/next_run`,
`beregening/status/master`).

Bij eerste start zonder opgeslagen WiFi: AP `RainMaster-xxxxxx`, wachtwoord `12345678`.

---

## ESPHome-variant

Alternatieve implementatie op basis van [ESPHome](https://esphome.io)
voor dezelfde hardware, in `esphome/rainmaster.yaml`.

Belangrijkste verschillen met de custom firmware:
- **Home Assistant-integratie via de native ESPHome API** (automatische
  discovery van alle entiteiten), in plaats van handmatig samengestelde
  MQTT-discovery-topics. MQTT kan er desgewenst naast, maar zit niet in
  deze config.
- **Touchscreen-UI met LVGL**: dezelfde vier schermen als hierboven,
  met dezelfde natuurlijke opstart-animatie.
- Zone-looptijden en -planning stel je in via entiteiten in Home Assistant.
- WiFi-fallback (AP + captive portal) komt standaard mee via ESPHome zelf.
- De tijd komt van Home Assistant zelf (`time: platform: homeassistant`)
  in plaats van een eigen NTP-verbinding.
- **Automatische firmware-updates via GitHub**: het apparaat controleert
  periodiek een firmware-manifest op GitHub Releases en haalt zelf een
  nieuwe versie op — zie "Automatische updates via GitHub" hieronder.

### Entiteiten in Home Assistant
Per zone (1-8): een schakelaar om de zone handmatig te starten/stoppen,
de looptijd (seconden), "ingeschakeld" (uitsluiten van cyclus/planning),
het dagmasker (tekstveld, cijfers `1`-`7`), 3 starttijden (tekstvelden,
`HH:MM`) en een sensor met de laatste keer dat de zone liep.
Algemeen: automatische modus, regenstop (dagen), waterbudget (%) en
"beregening actief".

Er is bewust geen aparte "volgende beregening"-entiteit op het apparaat
zelf (dat zou een dagen-vooruit-scan over alle zones vergen die beter
past bij Home Assistant dan bij het board) — bouw dat zo nodig als een
Home Assistant-template-sensor bovenop de hierboven genoemde
dagmasker-/starttijd-entiteiten. De custom firmware heeft dit wel
ingebouwd (zie "Planning & automatisering" hierboven).

### Bouwen/flashen
```
cd esphome
cp secrets.yaml.example secrets.yaml   # vul AP/OTA/API-gegevens in — GEEN wifi hier
esphome run rainmaster.yaml
```

**Eerste keer wifi instellen** (ook na de allereerste flash, of als je ooit
van wifi-netwerk wisselt): `rainmaster.yaml` bevat bewust géén wifi-ssid/
-wachtwoord. Na het flashen zet het apparaat zelf een tijdelijk toegangspunt
op (`RainMaster-Fallback`, wachtwoord uit `ap_password`). Verbind daarmee,
volg het configuratieschermpje (captive portal) dat vanzelf opent, en vul
daar je eigen wifi-netwerk in. Dat wordt op het apparaat zelf opgeslagen
(niet in de firmware) en overleeft toekomstige firmware-updates — je hoeft
dit dus maar één keer te doen.

**Belangrijk vóór het flashen:**
- De SPI-pinnen in de `substitutions:`-sectie bovenaan
  `rainmaster.yaml` (`pin_sclk`, `pin_mosi`, `pin_miso`,
  `pin_tft_cs`, `pin_tft_dc`, `pin_tft_rst`, `pin_touch_cs`, `pin_sd_cs`)
  zijn **placeholders** — pas ze aan naar je eigen bedrading.
- De touchkalibratie (`calibration:` onder `touchscreen:`) is een
  startwaarde; controleer/herijk aan de hand van de `x_raw`/`y_raw`-waarden
  die in de logs verschijnen bij het aanraken van het scherm (`on_touch:`),
  en pas zo nodig `calibration:` of `transform:` (swap_xy/mirror_x/mirror_y) aan.
- Kleuren kunnen omgedraaid ogen op sommige ILI9488-panelen; wissel dan
  `color_order: BGR` naar `RGB` in het `display:`-blok.

### Automatische updates via GitHub

Het apparaat kan zelf nieuwe firmware ophalen en installeren, zonder dat
Home Assistant/de ESPHome-dashboard erbij nodig is — vergelijkbaar met hoe
bijvoorbeeld watermeter-kits en andere gedeelde ESPHome-projecten dit doen.

**Hoe het werkt:**
1. Elke push naar `main` (of handmatig via "Run workflow") draait
   [`.github/workflows/build-firmware.yml`](.github/workflows/build-firmware.yml):
   compileert `rainmaster.yaml` en publiceert een GitHub Release met
   `rainmaster.bin` en een `manifest.json`.
2. Het apparaat controleert (elke 12 uur, of handmatig) een `update:`-entiteit
   in Home Assistant die dat manifest leest via
   `.../releases/latest/download/manifest.json`.
3. Is de versie in het manifest nieuwer? Dan haalt het apparaat zelf
   `rainmaster.bin` op en flasht dat (via `ota: platform: http_request`).

**Waarom dit veilig is om publiek te hosten:** zoals hierboven beschreven
bevat `rainmaster.yaml` bewust geen wifi-wachtwoord — dat wordt pas ná het
flashen, lokaal op het apparaat, ingesteld via het captive portal. De
gecompileerde firmware bevat dus geen van jouw geheimen, en kan zonder risico
in deze (publieke) repository staan.

**Eenmalig instellen** (daarna volledig automatisch): voeg in deze
GitHub-repository onder **Settings → Secrets and variables → Actions → New
repository secret** de volgende drie secrets toe — zelfde waarden als in je
lokale `esphome/secrets.yaml`:

| Secret-naam | Waarde |
|---|---|
| `AP_PASSWORD` | wachtwoord voor het tijdelijke wifi-toegangspunt |
| `OTA_PASSWORD` | wachtwoord voor lokale (niet-GitHub) OTA-updates |
| `API_ENCRYPTION_KEY` | de 32-byte base64 API-sleutel voor Home Assistant |

Daarna publiceert elke push naar `main` automatisch een nieuwe release, en
haalt elk RainMaster-apparaat dat zelf op. Heb je deze repo geforkt of
hernoemd? Pas dan ook `firmware_manifest_url` in `rainmaster.yaml`
(`substitutions:`) aan naar `<jouw-gebruikersnaam>/<jouw-repo>`.

Dit is een uitgebreide, met zorg opgebouwde configuratie, maar **nog niet
getest op echte hardware** (er was geen ESPHome-toolchain beschikbaar in de
omgeving waarin dit is gemaakt). Controleer na de eerste `esphome compile`
de foutmeldingen — kleine schema-aanpassingen (bijv. exacte sleutelnamen
binnen `lvgl:`-acties) kunnen nodig zijn afhankelijk van je ESPHome-versie.
