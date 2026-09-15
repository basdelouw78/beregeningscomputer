# RainMaster v2.0.0

Ombouw van de aangeleverde GridTogether firmware naar RainMaster, een ESP32-S3 + bestaand TFT/touchscreen beregeningscomputer.

Verwijderd: P1/DSMR, LoRa, energiehandel, API/backend, DeviceAuth en energiebatching.
Behouden: TFT/touch, WiFi-configuratieconcept, webserver, OTA, watchdog en lokale NVS-instellingen.

Deze versie gebruikt geen fysieke klep/relais-GPIO's. De ESP bedient via touchscreen en MQTT; Home Assistant koppelt de MQTT-zonecommando's aan de daadwerkelijke relais/kleppen.

## Hardware
- MCU: ESP32-S3-WROOM-1U-N8R2 (8 MB flash, 2 MB quad-SPI PSRAM)
- Display: 4" ILI9488 SPI TFT, 8-pins module inclusief touch en SD-kaartslot

## Bouwen (PlatformIO)
```
pio run
pio run -t upload
pio device monitor
```
De TFT_eSPI-configuratie (ILI9488, pinnen, fonts) staat als `build_flags` in
`platformio.ini` in plaats van een `User_Setup.h` in de library.

**Pas de SPI-pinnen (`TFT_CS`, `TFT_DC`, `TFT_RST`, `TFT_MISO`, `TFT_MOSI`,
`TFT_SCLK`, `TOUCH_CS`, `SD_CS`) in `platformio.ini` aan naar je eigen
bedrading** — de huidige waarden zijn placeholders. De bestaande rotatie `1`,
touchkalibratie `{231,3663,253,3471,7}` (in `config.h`) en backlight GPIO 38
zijn overgenomen uit de aangeleverde configuratie.

## Libraries
- TFT_eSPI
- PubSubClient
- ElegantOTA
- ESP32 Arduino core (WiFi, WebServer, Preferences, DNSServer)

## Webconfiguratie & OTA
De webconfiguratiepagina (WiFi/MQTT instellen, herstarten) en OTA-updates
(`/update`) zijn beveiligd met HTTP Basic Auth. Standaard inloggegevens
(`config.h`, `WEBUI_USER`/`WEBUI_PASS`): gebruiker `admin`, wachtwoord
`beregening123` — **wijzig dit wachtwoord voor je het apparaat op een
netwerk aansluit.**

## MQTT
Discovery wordt automatisch aangemaakt voor automatische modus, status, actieve zone, resterende tijd, zone 1-8 en looptijden.

Commando's:
- `beregening/command/start` = `CYCLE` of `1`..`8`
- `beregening/command/stop` = willekeurige payload
- `beregening/command/auto` = `ON`/`OFF`
- `beregening/zone/1/command` = `ON`/`OFF`
- `beregening/zone/1/runtime/set` = seconden

Bij eerste start zonder opgeslagen WiFi: AP `RainMaster-xxxxxx`, wachtwoord `12345678`.

## ESPHome-variant

Naast de custom C++/PlatformIO-firmware hierboven staat in `esphome/` een
alternatieve implementatie op basis van [ESPHome](https://esphome.io) voor
dezelfde hardware (ESP32-S3-WROOM-1U-N8R2 + 4" ILI9488 met XPT2046-touch).

Belangrijkste verschillen met de C++-firmware:
- **Home Assistant-integratie via de native ESPHome API** (automatische
  discovery van alle entiteiten), in plaats van handmatig samengestelde
  MQTT-discovery-topics. MQTT kan er desgewenst naast, maar zit niet in
  deze config.
- **Touchscreen-UI met LVGL**: Splash/Home/Zones/Instellingen-schermen,
  qua indeling gebaseerd op de originele schermen in `main.cpp`.
- Zone-looptijden stel je in via de `number`-entiteiten in Home Assistant
  (net als in de C++-firmware kon dat ook daar alleen via MQTT, niet via
  het touchscreen zelf).
- WiFi-fallback (AP + captive portal) komt standaard mee via ESPHome zelf.

### Bouwen/flashen
```
cd esphome
cp secrets.yaml.example secrets.yaml   # vul WiFi/OTA/API-gegevens in
esphome run beregeningscomputer.yaml
```

**Belangrijk vóór het flashen:**
- De SPI-pinnen in de `substitutions:`-sectie bovenaan
  `beregeningscomputer.yaml` (`pin_sclk`, `pin_mosi`, `pin_miso`,
  `pin_tft_cs`, `pin_tft_dc`, `pin_tft_rst`, `pin_touch_cs`, `pin_sd_cs`)
  zijn **placeholders** — pas ze aan naar je eigen bedrading.
- De touchkalibratie (`calibration:` onder `touchscreen:`) is een
  startwaarde, overgenomen uit de kalibratie van de C++-firmware.
  Coördinatensystemen verschillen per library; controleer/herijk aan de
  hand van de `x_raw`/`y_raw`-waarden die in de logs verschijnen bij het
  aanraken van het scherm (`on_touch:`), en pas zo nodig `calibration:`
  of `transform:` (swap_xy/mirror_x/mirror_y) aan.
- Kleuren kunnen omgedraaid ogen op sommige ILI9488-panelen; wissel dan
  `color_order: BGR` naar `RGB` in het `display:`-blok.

Dit is een uitgebreide, met zorg opgebouwde configuratie, maar **nog niet
getest op echte hardware** (er was geen ESPHome-toolchain beschikbaar in de
omgeving waarin dit is gemaakt). Controleer na de eerste `esphome compile`
de foutmeldingen — kleine schema-aanpassingen (bijv. exacte sleutelnamen
binnen `lvgl:`-acties) kunnen nodig zijn afhankelijk van je ESPHome-versie.
