#include "Irrigation.h"
#include <Preferences.h>
Irrigation IRRIGATION; static Preferences prefs;
void Irrigation::begin(){for(auto &v:_runtime)v=DEFAULT_RUNTIME_SEC;load();}
void Irrigation::load(){
 if(!prefs.begin("irrigation",true))return;
 _autoMode=prefs.getBool("auto",DEFAULT_AUTO_MODE);
 _rainDelayDays=prefs.getUShort("rainDelay",0);
 _waterBudget=prefs.getUChar("waterBudget",WATER_BUDGET_DEFAULT);
 if(_waterBudget<WATER_BUDGET_MIN||_waterBudget>WATER_BUDGET_MAX)_waterBudget=WATER_BUDGET_DEFAULT;
 for(uint8_t i=0;i<MAX_ZONES;i++){
  char k[8];
  snprintf(k,sizeof(k),"z%u",i);uint32_t v=prefs.getULong(k,DEFAULT_RUNTIME_SEC);_runtime[i]=(v<MIN_RUNTIME_SEC||v>MAX_RUNTIME_SEC)?DEFAULT_RUNTIME_SEC:v;
  snprintf(k,sizeof(k),"zen%u",i);_zoneEnabled[i]=prefs.getBool(k,true);
  snprintf(k,sizeof(k),"zdm%u",i);_dayMask[i]=prefs.getUChar(k,0x7F);
  for(uint8_t s=0;s<MAX_STARTS_PER_ZONE;s++){snprintf(k,sizeof(k),"zs%u_%u",i,s);_startMin[i][s]=(int16_t)prefs.getShort(k,-1);}
 }
 prefs.end();
}
void Irrigation::save(){
 if(!prefs.begin("irrigation",false))return;
 prefs.putBool("auto",_autoMode);
 prefs.putUShort("rainDelay",_rainDelayDays);
 prefs.putUChar("waterBudget",_waterBudget);
 for(uint8_t i=0;i<MAX_ZONES;i++){
  char k[8];
  snprintf(k,sizeof(k),"z%u",i);prefs.putULong(k,_runtime[i]);
  snprintf(k,sizeof(k),"zen%u",i);prefs.putBool(k,_zoneEnabled[i]);
  snprintf(k,sizeof(k),"zdm%u",i);prefs.putUChar(k,_dayMask[i]);
  for(uint8_t s=0;s<MAX_STARTS_PER_ZONE;s++){snprintf(k,sizeof(k),"zs%u_%u",i,s);prefs.putShort(k,_startMin[i][s]);}
 }
 prefs.end();
}
void Irrigation::setAutoMode(bool e){_autoMode=e;save();}
uint32_t Irrigation::runtime(uint8_t z)const{return z<MAX_ZONES?_runtime[z]:DEFAULT_RUNTIME_SEC;}
void Irrigation::setRuntime(uint8_t z,uint32_t s){if(z>=MAX_ZONES)return;s=constrain(s,MIN_RUNTIME_SEC,MAX_RUNTIME_SEC);_runtime[z]=s;save();}
bool Irrigation::zoneEnabled(uint8_t z)const{return z<MAX_ZONES?_zoneEnabled[z]:false;}
void Irrigation::setZoneEnabled(uint8_t z,bool en){if(z>=MAX_ZONES)return;_zoneEnabled[z]=en;save();}
uint8_t Irrigation::dayMask(uint8_t z)const{return z<MAX_ZONES?_dayMask[z]:0;}
void Irrigation::setDayMask(uint8_t z,uint8_t mask){if(z>=MAX_ZONES)return;_dayMask[z]=mask&0x7F;save();}
int16_t Irrigation::startMinute(uint8_t z,uint8_t slot)const{return(z<MAX_ZONES&&slot<MAX_STARTS_PER_ZONE)?_startMin[z][slot]:(int16_t)-1;}
void Irrigation::setStartMinute(uint8_t z,uint8_t slot,int16_t m){if(z>=MAX_ZONES||slot>=MAX_STARTS_PER_ZONE)return;_startMin[z][slot]=(m<0||m>=1440)?(int16_t)-1:m;save();}
void Irrigation::setRainDelayDays(uint16_t days){_rainDelayDays=days>MAX_RAIN_DELAY_DAYS?MAX_RAIN_DELAY_DAYS:days;save();}
void Irrigation::setWaterBudget(uint8_t pct){_waterBudget=constrain(pct,(uint8_t)WATER_BUDGET_MIN,(uint8_t)WATER_BUDGET_MAX);save();}
time_t Irrigation::lastStart(uint8_t z)const{return z<MAX_ZONES?_lastStart[z]:0;}
uint32_t Irrigation::lastDurationSec(uint8_t z)const{return z<MAX_ZONES?_lastDurationSec[z]:0;}
uint32_t Irrigation::applyWaterBudget(uint32_t sec)const{uint32_t s=(uint32_t)((uint64_t)sec*_waterBudget/100UL);return constrain(s,MIN_RUNTIME_SEC,MAX_RUNTIME_SEC);}
void Irrigation::beginZoneRun(uint8_t z,uint32_t sec){_activeZone=z;_running=true;_zoneStartMs=millis();_zoneDurationMs=sec*1000UL;_lastStart[z]=time(nullptr);_lastDurationSec[z]=sec;}
bool Irrigation::startZone(uint8_t z,uint32_t s,bool manual){if(z>=MAX_ZONES)return false;if(!s)s=_runtime[z];s=constrain(s,MIN_RUNTIME_SEC,MAX_RUNTIME_SEC);_cycleMode=false;beginZoneRun(z,s);Serial.printf("[IRR] Zone %u START %lus%s\n",z+1,(unsigned long)s,manual?" handmatig":" automatisch");return true;}
bool Irrigation::startCycle(){if(!_autoMode)return false;_cycleMode=true;_activeZone=0;_cycleNumber++;startCurrentZone();Serial.printf("[IRR] Cyclus %lu START\n",(unsigned long)_cycleNumber);return true;}
void Irrigation::startCurrentZone(){while(_activeZone<MAX_ZONES&&!_zoneEnabled[_activeZone])_activeZone++;if(_activeZone>=MAX_ZONES){stopAll();return;}uint32_t s=applyWaterBudget(_runtime[_activeZone]);beginZoneRun(_activeZone,s);Serial.printf("[IRR] Cyclus zone %u START %lus\n",_activeZone+1,(unsigned long)s);}
void Irrigation::advanceCycle(){if(!_cycleMode){stopAll();return;}_activeZone++;startCurrentZone();}
void Irrigation::stopAll(){if(_running)Serial.println("[IRR] STOP");_running=false;_cycleMode=false;_activeZone=0;_zoneStartMs=0;_zoneDurationMs=0;}
void Irrigation::loop(){if(_running&&(uint32_t)(millis()-_zoneStartMs)>=_zoneDurationMs)advanceCycle();}
uint32_t Irrigation::remainingSeconds()const{if(!_running)return 0;uint32_t e=millis()-_zoneStartMs;if(e>=_zoneDurationMs)return 0;return(_zoneDurationMs-e+999UL)/1000UL;}
uint32_t Irrigation::elapsedSeconds()const{return _running?(millis()-_zoneStartMs)/1000UL:0;}
const char* Irrigation::stateText()const{if(!_running)return "idle";return _cycleMode?"cycle":"manual";}

// Wordt elke loop() aangeroepen vanuit main; handelt zelf de "eens per minuut"-throttling af.
void Irrigation::checkSchedule(){
 time_t now=time(nullptr);
 if(now<100000)return; // tijd nog niet gesynchroniseerd via NTP
 struct tm t; localtime_r(&now,&t);
 if(t.tm_yday!=_lastCheckedYday){_lastCheckedYday=t.tm_yday;if(_rainDelayDays>0){_rainDelayDays--;save();}}
 if(t.tm_min==_lastCheckedMinute)return;
 _lastCheckedMinute=t.tm_min;
 if(_rainDelayDays>0||_running)return; // regenstop actief, of een lopende zone niet onderbreken
 uint16_t nowMin=(uint16_t)(t.tm_hour*60+t.tm_min);
 uint8_t dayBit=(t.tm_wday==0)?6:(uint8_t)(t.tm_wday-1); // ma=bit0 .. zo=bit6
 for(uint8_t z=0;z<MAX_ZONES;z++){
  if(!_zoneEnabled[z]||!(_dayMask[z]&(1<<dayBit)))continue;
  for(uint8_t k=0;k<MAX_STARTS_PER_ZONE;k++){
   if(_startMin[z][k]==(int16_t)nowMin){
    uint32_t s=applyWaterBudget(_runtime[z]);_cycleMode=false;beginZoneRun(z,s);
    Serial.printf("[IRR] Zone %u GEPLANDE START %lus\n",z+1,(unsigned long)s);
    return;
   }
  }
 }
}

bool Irrigation::nextRun(uint8_t&zoneOut,uint8_t&dayOffsetOut,uint16_t&minuteOut)const{
 if(_rainDelayDays>0)return false;
 time_t now=time(nullptr);
 if(now<100000)return false;
 struct tm t; localtime_r(&now,&t);
 uint16_t nowMin=(uint16_t)(t.tm_hour*60+t.tm_min);
 uint8_t todayBit=(t.tm_wday==0)?6:(uint8_t)(t.tm_wday-1);
 for(uint8_t d=0;d<7;d++){
  uint8_t bit=(todayBit+d)%7; bool found=false; uint16_t bestMin=0; uint8_t bestZone=0;
  for(uint8_t z=0;z<MAX_ZONES;z++){
   if(!_zoneEnabled[z]||!(_dayMask[z]&(1<<bit)))continue;
   for(uint8_t k=0;k<MAX_STARTS_PER_ZONE;k++){
    int16_t m=_startMin[z][k]; if(m<0)continue;
    if(d==0&&(uint16_t)m<=nowMin)continue;
    if(!found||(uint16_t)m<bestMin){found=true;bestMin=(uint16_t)m;bestZone=z;}
   }
  }
  if(found){zoneOut=bestZone;dayOffsetOut=d;minuteOut=bestMin;return true;}
 }
 return false;
}
