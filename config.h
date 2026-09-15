#pragma once
#define FW_VERSION "v2.0.0"
#define DEVICE_NAME "RainMaster"
#define TFT_BACKLIGHT_PIN 38
#define TFT_BACKLIGHT_ON_LEVEL 1
#define SCREEN_IDLE_TIMEOUT_MS 60000UL
#define TOUCH_CAL_X0 231
#define TOUCH_CAL_X1 3663
#define TOUCH_CAL_Y0 253
#define TOUCH_CAL_Y1 3471
#define TOUCH_CAL_Z 7
#define MAX_ZONES 8
#define DEFAULT_RUNTIME_SEC 600UL
#define MIN_RUNTIME_SEC 10UL
#define MAX_RUNTIME_SEC 7200UL
#define DEFAULT_AUTO_MODE true
// Planning: tot 3 onafhankelijke starttijden per zone, met dagen-van-de-week
#define MAX_STARTS_PER_ZONE 3
// Regenstop: max. aantal dagen dat geplande starts worden overgeslagen
#define MAX_RAIN_DELAY_DAYS 14
// Water-budget/seizoensaanpassing: percentage waarmee geplande looptijden worden geschaald
#define WATER_BUDGET_MIN 50
#define WATER_BUDGET_MAX 150
#define WATER_BUDGET_DEFAULT 100
// NTP-tijdsynchronisatie (nodig voor de planning); TZ_INFO is een POSIX-tijdzonestring
// (standaard Europe/Amsterdam) - pas aan als het apparaat elders staat.
#define NTP_SERVER1 "pool.ntp.org"
#define NTP_SERVER2 "time.google.com"
#define TZ_INFO "CET-1CEST,M3.5.0,M10.5.0/3"
#define MQTT_DEFAULT_PORT 1883
#define MQTT_KEEPALIVE_SEC 30
#define MQTT_RECONNECT_MS 5000UL
#define MQTT_PUBLISH_MS 5000UL
#define MQTT_DISCOVERY_PREFIX "homeassistant"
#define MQTT_BASE_TOPIC "beregening"
// Inloggegevens voor de webconfiguratie (/setwifi, /mqtt, /reboot) en OTA-updates (/update).
// WIJZIG DIT WACHTWOORD voor je het apparaat op een netwerk aansluit!
#define WEBUI_USER "admin"
#define WEBUI_PASS "beregening123"
