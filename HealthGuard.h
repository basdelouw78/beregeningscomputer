#pragma once
#include <Arduino.h>
namespace HealthGuard{struct Cfg{uint32_t wdtSeconds=10,hardHangMs=30000,uplinkSilentMs=0,vitalsPeriodMs=10000,heapWarnBytes=35000,heapPanicBytes=24000;bool rebootOnHeapPanic=true;};void begin(const Cfg&);void loop();void progress();void uplinkOk();void noteLvglFrame();}
