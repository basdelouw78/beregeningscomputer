#include "HealthGuard.h"
#include <WiFi.h>
#include <esp_task_wdt.h>
namespace HealthGuard{static Cfg c;static uint32_t progressMs,vitalsMs;void begin(const Cfg&x){c=x;esp_task_wdt_init(c.wdtSeconds,true);esp_task_wdt_add(NULL);progressMs=vitalsMs=millis();}void progress(){progressMs=millis();}void uplinkOk(){progress();}void noteLvglFrame(){progress();}void loop(){esp_task_wdt_reset();uint32_t n=millis();if(n-progressMs>c.hardHangMs){Serial.println("[GUARD] hang reboot");delay(50);ESP.restart();}if(n-vitalsMs>c.vitalsPeriodMs){vitalsMs=n;uint32_t h=ESP.getFreeHeap();Serial.printf("[VITAL] heap=%u min=%u wifi=%s\n",h,ESP.getMinFreeHeap(),WiFi.isConnected()?"on":"off");if(c.rebootOnHeapPanic&&(h<c.heapPanicBytes||ESP.getMinFreeHeap()<c.heapPanicBytes)){delay(50);ESP.restart();}}}}
