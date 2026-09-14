# GridTogether Beregeningscomputer v2.0.0

Ombouw van de aangeleverde GridTogether firmware naar een ESP32-S3 + bestaand TFT/touchscreen beregeningscomputer.

Verwijderd: P1/DSMR, LoRa, energiehandel, API/backend, DeviceAuth en energiebatching.
Behouden: TFT/touch, WiFi-configuratieconcept, webserver, OTA, watchdog en lokale NVS-instellingen.

Deze versie gebruikt geen fysieke klep/relais-GPIO's. De ESP bedient via touchscreen en MQTT; Home Assistant koppelt de MQTT-zonecommando's aan de daadwerkelijke relais/kleppen.

## Libraries
- TFT_eSPI
- PubSubClient
- ElegantOTA
- ESP32 Arduino core (WiFi, WebServer, Preferences, DNSServer)

Gebruik de bestaande TFT_eSPI hardwareconfiguratie. De bestaande rotatie `1`, touchkalibratie `{231,3663,253,3471,7}` en backlight GPIO 38 zijn overgenomen.

## MQTT
Discovery wordt automatisch aangemaakt voor automatische modus, status, actieve zone, resterende tijd, zone 1-8 en looptijden.

Commando's:
- `beregening/command/start` = `CYCLE` of `1`..`8`
- `beregening/command/stop` = willekeurige payload
- `beregening/command/auto` = `ON`/`OFF`
- `beregening/zone/1/command` = `ON`/`OFF`
- `beregening/zone/1/runtime/set` = seconden

Bij eerste start zonder opgeslagen WiFi: AP `GridTogether-xxxxxx`, wachtwoord `12345678`.
