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
