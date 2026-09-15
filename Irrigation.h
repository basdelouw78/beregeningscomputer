#pragma once
#include <Arduino.h>
#include <time.h>
#include "config.h"
class Irrigation {
public:
 void begin(); void loop(); void checkSchedule();
 void setAutoMode(bool e); bool autoMode() const{return _autoMode;}
 bool startZone(uint8_t z,uint32_t sec=0,bool manual=true); bool startCycle(); void stopAll();
 bool isRunning()const{return _running;} uint8_t activeZone()const{return _activeZone;}
 uint32_t remainingSeconds()const; uint32_t elapsedSeconds()const;
 uint32_t runtime(uint8_t z)const; void setRuntime(uint8_t z,uint32_t sec);
 const char* stateText()const; uint32_t cycleNumber()const{return _cycleNumber;}
 // Zone in-/uitschakelen (uitgeschakelde zones slaat de auto-cyclus/planning over, handmatig starten blijft mogelijk)
 bool zoneEnabled(uint8_t z)const; void setZoneEnabled(uint8_t z,bool en);
 // Planning: dagmasker (bit0=maandag .. bit6=zondag) + tot MAX_STARTS_PER_ZONE starttijden (minuten sinds middernacht, -1 = ongebruikt)
 uint8_t dayMask(uint8_t z)const; void setDayMask(uint8_t z,uint8_t mask);
 int16_t startMinute(uint8_t z,uint8_t slot)const; void setStartMinute(uint8_t z,uint8_t slot,int16_t minuteOfDay);
 // Regenstop: aantal dagen dat geplande starts worden overgeslagen (handmatig starten blijft mogelijk)
 uint16_t rainDelayDays()const{return _rainDelayDays;} void setRainDelayDays(uint16_t days);
 // Water-budget/seizoensaanpassing: percentage waarmee geplande/cyclus-looptijden worden geschaald
 uint8_t waterBudget()const{return _waterBudget;} void setWaterBudget(uint8_t pct);
 // Laatste keer dat een zone liep
 time_t lastStart(uint8_t z)const; uint32_t lastDurationSec(uint8_t z)const;
 // Eerstvolgende geplande start; retourneert false als er niets gepland staat (of regenstop actief is)
 bool nextRun(uint8_t&zoneOut,uint8_t&dayOffsetOut,uint16_t&minuteOut)const;
private:
 bool _autoMode=DEFAULT_AUTO_MODE,_running=false,_cycleMode=false; uint8_t _activeZone=0;
 uint32_t _zoneStartMs=0,_zoneDurationMs=0,_cycleNumber=0,_runtime[MAX_ZONES];
 bool _zoneEnabled[MAX_ZONES]; uint8_t _dayMask[MAX_ZONES]; int16_t _startMin[MAX_ZONES][MAX_STARTS_PER_ZONE];
 uint16_t _rainDelayDays=0; uint8_t _waterBudget=WATER_BUDGET_DEFAULT;
 time_t _lastStart[MAX_ZONES]={0}; uint32_t _lastDurationSec[MAX_ZONES]={0};
 int8_t _lastCheckedMinute=-1; int16_t _lastCheckedYday=-1;
 void startCurrentZone(); void advanceCycle(); void save(); void load();
 void beginZoneRun(uint8_t z,uint32_t sec); uint32_t applyWaterBudget(uint32_t sec)const;
};
extern Irrigation IRRIGATION;
