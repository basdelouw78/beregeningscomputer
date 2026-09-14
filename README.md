# GridTogether Beregeningscomputer v2.0.0

Ombouw van de aangeleverde GridTogether firmware naar een ESP32-S3 + bestaand TFT/touchscreen beregeningscomputer.

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

Bij eerste start zonder opgeslagen WiFi: AP `GridTogether-xxxxxx`, wachtwoord `12345678`.
