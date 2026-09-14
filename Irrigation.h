#pragma once
#include <Arduino.h>
#include "config.h"
class Irrigation {
public:
 void begin(); void loop();
 void setAutoMode(bool e); bool autoMode() const{return _autoMode;}
 bool startZone(uint8_t z,uint32_t sec=0,bool manual=true); bool startCycle(); void stopAll();
 bool isRunning()const{return _running;} uint8_t activeZone()const{return _activeZone;}
 uint32_t remainingSeconds()const; uint32_t elapsedSeconds()const;
 uint32_t runtime(uint8_t z)const; void setRuntime(uint8_t z,uint32_t sec);
 const char* stateText()const; uint32_t cycleNumber()const{return _cycleNumber;}
private:
 bool _autoMode=DEFAULT_AUTO_MODE,_running=false,_cycleMode=false; uint8_t _activeZone=0;
 uint32_t _zoneStartMs=0,_zoneDurationMs=0,_cycleNumber=0,_runtime[MAX_ZONES];
 void startCurrentZone(); void advanceCycle(); void save(); void load();
};
extern Irrigation IRRIGATION;
