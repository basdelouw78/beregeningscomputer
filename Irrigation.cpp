#include "Irrigation.h"
#include <Preferences.h>
Irrigation IRRIGATION; static Preferences prefs;
void Irrigation::begin(){for(auto &v:_runtime)v=DEFAULT_RUNTIME_SEC;load();}
void Irrigation::load(){if(!prefs.begin("irrigation",true))return;_autoMode=prefs.getBool("auto",DEFAULT_AUTO_MODE);for(uint8_t i=0;i<MAX_ZONES;i++){char k[8];snprintf(k,sizeof(k),"z%u",i);uint32_t v=prefs.getULong(k,DEFAULT_RUNTIME_SEC);_runtime[i]=(v<MIN_RUNTIME_SEC||v>MAX_RUNTIME_SEC)?DEFAULT_RUNTIME_SEC:v;}prefs.end();}
void Irrigation::save(){if(!prefs.begin("irrigation",false))return;prefs.putBool("auto",_autoMode);for(uint8_t i=0;i<MAX_ZONES;i++){char k[8];snprintf(k,sizeof(k),"z%u",i);prefs.putULong(k,_runtime[i]);}prefs.end();}
void Irrigation::setAutoMode(bool e){_autoMode=e;save();}
uint32_t Irrigation::runtime(uint8_t z)const{return z<MAX_ZONES?_runtime[z]:DEFAULT_RUNTIME_SEC;}
void Irrigation::setRuntime(uint8_t z,uint32_t s){if(z>=MAX_ZONES)return;s=constrain(s,MIN_RUNTIME_SEC,MAX_RUNTIME_SEC);_runtime[z]=s;save();}
bool Irrigation::startZone(uint8_t z,uint32_t s,bool manual){if(z>=MAX_ZONES)return false;if(!s)s=_runtime[z];s=constrain(s,MIN_RUNTIME_SEC,MAX_RUNTIME_SEC);_running=true;_cycleMode=false;_activeZone=z;_zoneStartMs=millis();_zoneDurationMs=s*1000UL;Serial.printf("[IRR] Zone %u START %lus%s\n",z+1,(unsigned long)s,manual?" manual":" automatic");return true;}
bool Irrigation::startCycle(){if(!_autoMode)return false;_cycleMode=true;_running=true;_activeZone=0;_cycleNumber++;startCurrentZone();Serial.printf("[IRR] Cycle %lu START\n",(unsigned long)_cycleNumber);return true;}
void Irrigation::startCurrentZone(){if(_activeZone>=MAX_ZONES){stopAll();return;}_zoneStartMs=millis();_zoneDurationMs=_runtime[_activeZone]*1000UL;Serial.printf("[IRR] Cycle zone %u START %lus\n",_activeZone+1,(unsigned long)_runtime[_activeZone]);}
void Irrigation::advanceCycle(){if(!_cycleMode){stopAll();return;}++_activeZone;if(_activeZone>=MAX_ZONES){stopAll();Serial.println("[IRR] Cycle COMPLETE");return;}startCurrentZone();}
void Irrigation::stopAll(){if(_running)Serial.println("[IRR] STOP");_running=false;_cycleMode=false;_activeZone=0;_zoneStartMs=0;_zoneDurationMs=0;}
void Irrigation::loop(){if(_running&&(uint32_t)(millis()-_zoneStartMs)>=_zoneDurationMs)advanceCycle();}
uint32_t Irrigation::remainingSeconds()const{if(!_running)return 0;uint32_t e=millis()-_zoneStartMs;if(e>=_zoneDurationMs)return 0;return(_zoneDurationMs-e+999UL)/1000UL;}
uint32_t Irrigation::elapsedSeconds()const{return _running?(millis()-_zoneStartMs)/1000UL:0;}
const char* Irrigation::stateText()const{if(!_running)return "idle";return _cycleMode?"cycle":"manual";}
