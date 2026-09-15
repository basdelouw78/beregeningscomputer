#include "MQTTManager.h"
#include "Irrigation.h"
#include "config.h"
#include <WiFi.h>
#include <Preferences.h>
#include <PubSubClient.h>
#include <time.h>
MQTTManager MQTT; static WiFiClient net; static PubSubClient client(net); static Preferences prefs; static MQTTManager* self=nullptr;
void mqttCallbackBridge(char*t,byte*p,unsigned int l){if(self)self->callback(t,p,l);} static String ps(byte*p,unsigned int l){String s;for(unsigned int i=0;i<l;i++)s+=(char)p[i];s.trim();return s;} static bool yes(const String&s){return s.equalsIgnoreCase("ON")||s.equalsIgnoreCase("true")||s=="1";}
// "HH:MM" <-> minuten sinds middernacht (-1 = geen starttijd ingesteld)
static int16_t parseHHMM(const String&s){int c=s.indexOf(':');if(c<1)return -1;int h=s.substring(0,c).toInt(),m=s.substring(c+1).toInt();if(h<0||h>23||m<0||m>59)return -1;return(int16_t)(h*60+m);}
static String formatHHMM(int16_t m){if(m<0)return "--:--";char b[6];snprintf(b,sizeof(b),"%02d:%02d",m/60,m%60);return String(b);}
static String formatEpoch(time_t t){if(t<100000)return "nooit";struct tm tmv;localtime_r(&t,&tmv);char b[20];strftime(b,sizeof(b),"%Y-%m-%d %H:%M",&tmv);return String(b);}
// Dagmasker (bit0=ma..bit6=zo) <-> cijferreeks "1".."7" (1=ma..7=zo), bv. "135" = ma/wo/vr
static uint8_t parseDayMask(const String&s){uint8_t m=0;for(size_t i=0;i<s.length();i++){char c=s[i];if(c>='1'&&c<='7')m|=1<<(c-'1');}return m;}
static String formatDayMask(uint8_t m){String s;for(uint8_t d=0;d<7;d++)if(m&(1<<d))s+=String(d+1);return s.length()?s:"-";}
static String dayLabel(uint8_t offset){if(offset==0)return "vandaag";if(offset==1)return "morgen";time_t t=time(nullptr)+(time_t)offset*86400UL;struct tm tmv;localtime_r(&t,&tmv);static const char*n[7]={"zo","ma","di","wo","do","vr","za"};return String(n[tmv.tm_wday]);}
void MQTTManager::load(){if(!prefs.begin("mqtt",true))return;_broker=prefs.getString("broker","");_port=prefs.getUShort("port",1883);_user=prefs.getString("user","");_password=prefs.getString("pass","");prefs.end();}
void MQTTManager::save(){if(!prefs.begin("mqtt",false))return;prefs.putString("broker",_broker);prefs.putUShort("port",_port);prefs.putString("user",_user);prefs.putString("pass",_password);prefs.end();}
void MQTTManager::begin(){self=this;load();client.setCallback(mqttCallbackBridge);client.setKeepAlive(MQTT_KEEPALIVE_SEC);client.setBufferSize(2048);}
void MQTTManager::setBroker(const String&v){_broker=v;save();client.disconnect();_discoveryPublished=false;}void MQTTManager::setPort(uint16_t v){_port=v?v:1883;save();client.disconnect();_discoveryPublished=false;}void MQTTManager::setUser(const String&v){_user=v;save();client.disconnect();_discoveryPublished=false;}void MQTTManager::setPassword(const String&v){_password=v;save();client.disconnect();_discoveryPublished=false;}
bool MQTTManager::connected()const{return client.connected();}
bool MQTTManager::connect(){if(!WiFi.isConnected()||!_broker.length())return false;client.setServer(_broker.c_str(),_port);String id=String(DEVICE_NAME)+"-"+String((uint32_t)(ESP.getEfuseMac()&0xffffffff),HEX);String will=String(MQTT_BASE_TOPIC)+"/status/availability";bool ok=_user.length()?client.connect(id.c_str(),_user.c_str(),_password.c_str(),will.c_str(),0,true,"offline"):client.connect(id.c_str(),will.c_str(),0,true,"offline");if(!ok){Serial.printf("[MQTT] connect failed rc=%d\n",client.state());return false;}client.publish(will.c_str(),"online",true);client.subscribe(MQTT_BASE_TOPIC "/command/#");client.subscribe(MQTT_BASE_TOPIC "/zone/+/command");client.subscribe(MQTT_BASE_TOPIC "/zone/+/runtime/set");client.subscribe(MQTT_BASE_TOPIC "/zone/+/enabled/set");client.subscribe(MQTT_BASE_TOPIC "/zone/+/schedule/+/set");publishDiscovery();publishState();return true;}
void MQTTManager::loop(){if(!WiFi.isConnected()){if(client.connected())client.disconnect();return;}if(!client.connected()){if(millis()-_lastReconnect>=MQTT_RECONNECT_MS){_lastReconnect=millis();connect();}return;}client.loop();if(millis()-_lastPublish>=MQTT_PUBLISH_MS){_lastPublish=millis();publishState();}}
void MQTTManager::callback(char*t,byte*p,unsigned int l){
 String topic(t),v=ps(p,l);
 if(topic==String(MQTT_BASE_TOPIC)+"/command/start"){if(v.equalsIgnoreCase("CYCLE"))IRRIGATION.startCycle();else{int z=v.toInt();if(z>=1&&z<=MAX_ZONES)IRRIGATION.startZone(z-1);}}
 else if(topic==String(MQTT_BASE_TOPIC)+"/command/stop"){IRRIGATION.stopAll();}
 else if(topic==String(MQTT_BASE_TOPIC)+"/command/auto"){IRRIGATION.setAutoMode(yes(v));}
 else if(topic==String(MQTT_BASE_TOPIC)+"/command/raindelay/set"){IRRIGATION.setRainDelayDays((uint16_t)constrain(v.toInt(),0,(long)MAX_RAIN_DELAY_DAYS));}
 else if(topic==String(MQTT_BASE_TOPIC)+"/command/waterbudget/set"){IRRIGATION.setWaterBudget((uint8_t)constrain(v.toInt(),(long)WATER_BUDGET_MIN,(long)WATER_BUDGET_MAX));}
 else if(topic.startsWith(String(MQTT_BASE_TOPIC)+"/zone/")){
  int a=topic.indexOf("/zone/")+6,b=topic.indexOf('/',a);if(b<0)return;
  int z=topic.substring(a,b).toInt()-1;if(z<0||z>=MAX_ZONES)return;
  String action=topic.substring(b+1);
  if(action=="command"){if(yes(v))IRRIGATION.startZone(z);else IRRIGATION.stopAll();}
  else if(action=="runtime/set")IRRIGATION.setRuntime(z,constrain(v.toInt(),(long)MIN_RUNTIME_SEC,(long)MAX_RUNTIME_SEC));
  else if(action=="enabled/set")IRRIGATION.setZoneEnabled(z,yes(v));
  else if(action=="schedule/daymask/set")IRRIGATION.setDayMask(z,parseDayMask(v));
  else if(action=="schedule/start1/set")IRRIGATION.setStartMinute(z,0,parseHHMM(v));
  else if(action=="schedule/start2/set")IRRIGATION.setStartMinute(z,1,parseHHMM(v));
  else if(action=="schedule/start3/set")IRRIGATION.setStartMinute(z,2,parseHHMM(v));
  else return;
 } else return;
 publishState();
}
void MQTTManager::publishState(bool retained){
 if(!client.connected())return;
 client.publish(MQTT_BASE_TOPIC "/status/state",IRRIGATION.stateText(),retained);
 String s=IRRIGATION.isRunning()?String(IRRIGATION.activeZone()+1):"0";client.publish(MQTT_BASE_TOPIC "/status/active_zone",s.c_str(),retained);
 s=String(IRRIGATION.remainingSeconds());client.publish(MQTT_BASE_TOPIC "/status/remaining_seconds",s.c_str(),retained);
 s=IRRIGATION.autoMode()?"ON":"OFF";client.publish(MQTT_BASE_TOPIC "/status/auto",s.c_str(),retained);
 client.publish(MQTT_BASE_TOPIC "/status/master",IRRIGATION.isRunning()?"ON":"OFF",retained);
 client.publish(MQTT_BASE_TOPIC "/status/raindelay",String(IRRIGATION.rainDelayDays()).c_str(),retained);
 client.publish(MQTT_BASE_TOPIC "/status/waterbudget",String(IRRIGATION.waterBudget()).c_str(),retained);
 {uint8_t nz,nd;uint16_t nm;String nr=IRRIGATION.nextRun(nz,nd,nm)?("Zone "+String(nz+1)+" "+dayLabel(nd)+" "+formatHHMM((int16_t)nm)):"geen";client.publish(MQTT_BASE_TOPIC "/status/next_run",nr.c_str(),retained);}
 for(uint8_t z=0;z<MAX_ZONES;z++){
  String t=String(MQTT_BASE_TOPIC)+"/zone/"+String(z+1)+"/state";const char*q=IRRIGATION.isRunning()&&IRRIGATION.activeZone()==z?"ON":"OFF";client.publish(t.c_str(),q,retained);
  t=String(MQTT_BASE_TOPIC)+"/zone/"+String(z+1)+"/runtime";s=String(IRRIGATION.runtime(z));client.publish(t.c_str(),s.c_str(),retained);
  t=String(MQTT_BASE_TOPIC)+"/zone/"+String(z+1)+"/enabled";client.publish(t.c_str(),IRRIGATION.zoneEnabled(z)?"ON":"OFF",retained);
  t=String(MQTT_BASE_TOPIC)+"/zone/"+String(z+1)+"/schedule/daymask";client.publish(t.c_str(),formatDayMask(IRRIGATION.dayMask(z)).c_str(),retained);
  for(uint8_t k=0;k<MAX_STARTS_PER_ZONE;k++){t=String(MQTT_BASE_TOPIC)+"/zone/"+String(z+1)+"/schedule/start"+String(k+1);client.publish(t.c_str(),formatHHMM(IRRIGATION.startMinute(z,k)).c_str(),retained);}
  t=String(MQTT_BASE_TOPIC)+"/zone/"+String(z+1)+"/last_run";client.publish(t.c_str(),formatEpoch(IRRIGATION.lastStart(z)).c_str(),retained);
 }
}
static void pub(const String&t,const String&j){client.publish(t.c_str(),j.c_str(),true);}
void MQTTManager::publishDiscovery(){if(!client.connected()||_discoveryPublished)return;publishSwitchDiscovery();publishStatusDiscovery();publishGlobalScheduleDiscovery();for(uint8_t z=0;z<MAX_ZONES;z++){publishZoneDiscovery(z);publishNumberDiscovery(z);publishScheduleDiscovery(z);}_discoveryPublished=true;}
void MQTTManager::publishSwitchDiscovery(){String t=String(MQTT_DISCOVERY_PREFIX)+"/switch/rainmaster/auto/config";pub(t,"{\"name\":\"Beregening automatisch\",\"unique_id\":\"rainmaster_auto\",\"command_topic\":\"beregening/command/auto\",\"state_topic\":\"beregening/status/auto\",\"payload_on\":\"ON\",\"payload_off\":\"OFF\",\"device\":{\"identifiers\":[\"rainmaster\"],\"name\":\"RainMaster\",\"manufacturer\":\"RainMaster\",\"model\":\"ESP32-S3\"}}");}
void MQTTManager::publishStatusDiscovery(){String d="\"device\":{\"identifiers\":[\"rainmaster\"],\"name\":\"RainMaster\",\"manufacturer\":\"RainMaster\",\"model\":\"ESP32-S3\"}";pub(String(MQTT_DISCOVERY_PREFIX)+"/sensor/rainmaster/state/config","{\"name\":\"Beregening status\",\"unique_id\":\"rainmaster_state\",\"state_topic\":\"beregening/status/state\","+d+"}");pub(String(MQTT_DISCOVERY_PREFIX)+"/sensor/rainmaster/active_zone/config","{\"name\":\"Actieve zone\",\"unique_id\":\"rainmaster_active_zone\",\"state_topic\":\"beregening/status/active_zone\","+d+"}");pub(String(MQTT_DISCOVERY_PREFIX)+"/sensor/rainmaster/remaining/config","{\"name\":\"Resterende tijd\",\"unique_id\":\"rainmaster_remaining\",\"state_topic\":\"beregening/status/remaining_seconds\",\"unit_of_measurement\":\"s\",\"device_class\":\"duration\","+d+"}");}
void MQTTManager::publishZoneDiscovery(uint8_t z){String id="rainmaster_zone_"+String(z+1),t=String(MQTT_DISCOVERY_PREFIX)+"/switch/"+id+"/config";String j="{\"name\":\"Zone "+String(z+1)+"\",\"unique_id\":\""+id+"\",\"command_topic\":\"beregening/zone/"+String(z+1)+"/command\",\"state_topic\":\"beregening/zone/"+String(z+1)+"/state\",\"payload_on\":\"ON\",\"payload_off\":\"OFF\",\"device\":{\"identifiers\":[\"rainmaster\"],\"name\":\"RainMaster\",\"manufacturer\":\"RainMaster\",\"model\":\"ESP32-S3\"}}";pub(t,j);}
void MQTTManager::publishNumberDiscovery(uint8_t z){String id="rainmaster_zone_runtime_"+String(z+1),t=String(MQTT_DISCOVERY_PREFIX)+"/number/"+id+"/config";String j="{\"name\":\"Zone "+String(z+1)+" looptijd\",\"unique_id\":\""+id+"\",\"command_topic\":\"beregening/zone/"+String(z+1)+"/runtime/set\",\"state_topic\":\"beregening/zone/"+String(z+1)+"/runtime\",\"min\":10,\"max\":7200,\"step\":10,\"mode\":\"box\",\"unit_of_measurement\":\"s\",\"device\":{\"identifiers\":[\"rainmaster\"],\"name\":\"RainMaster\",\"manufacturer\":\"RainMaster\",\"model\":\"ESP32-S3\"}}";pub(t,j);}
// Per-zone: aan/uit-schakelaar, dagmasker-tekstveld en 3 starttijd-tekstvelden, plus een sensor met de laatste keer dat de zone liep.
void MQTTManager::publishScheduleDiscovery(uint8_t z){
 String d="\"device\":{\"identifiers\":[\"rainmaster\"],\"name\":\"RainMaster\",\"manufacturer\":\"RainMaster\",\"model\":\"ESP32-S3\"}";
 String zn=String(z+1),base="beregening/zone/"+zn;
 String id="rainmaster_zone_"+zn+"_enabled";
 pub(String(MQTT_DISCOVERY_PREFIX)+"/switch/"+id+"/config","{\"name\":\"Zone "+zn+" ingeschakeld\",\"unique_id\":\""+id+"\",\"command_topic\":\""+base+"/enabled/set\",\"state_topic\":\""+base+"/enabled\",\"payload_on\":\"ON\",\"payload_off\":\"OFF\",\"entity_category\":\"config\","+d+"}");
 id="rainmaster_zone_"+zn+"_days";
 pub(String(MQTT_DISCOVERY_PREFIX)+"/text/"+id+"/config","{\"name\":\"Zone "+zn+" dagen (1=ma..7=zo, bv. 1357)\",\"unique_id\":\""+id+"\",\"command_topic\":\""+base+"/schedule/daymask/set\",\"state_topic\":\""+base+"/schedule/daymask\",\"entity_category\":\"config\","+d+"}");
 for(uint8_t k=0;k<MAX_STARTS_PER_ZONE;k++){
  id="rainmaster_zone_"+zn+"_start"+String(k+1);
  pub(String(MQTT_DISCOVERY_PREFIX)+"/text/"+id+"/config","{\"name\":\"Zone "+zn+" starttijd "+String(k+1)+" (HH:MM)\",\"unique_id\":\""+id+"\",\"command_topic\":\""+base+"/schedule/start"+String(k+1)+"/set\",\"state_topic\":\""+base+"/schedule/start"+String(k+1)+"\",\"entity_category\":\"config\","+d+"}");
 }
 id="rainmaster_zone_"+zn+"_last_run";
 pub(String(MQTT_DISCOVERY_PREFIX)+"/sensor/"+id+"/config","{\"name\":\"Zone "+zn+" laatste beregening\",\"unique_id\":\""+id+"\",\"state_topic\":\""+base+"/last_run\",\"entity_category\":\"diagnostic\","+d+"}");
}
// Globaal: regenstop (dagen), water-budget (%), "master"-indicator en eerstvolgende geplande start.
void MQTTManager::publishGlobalScheduleDiscovery(){
 String d="\"device\":{\"identifiers\":[\"rainmaster\"],\"name\":\"RainMaster\",\"manufacturer\":\"RainMaster\",\"model\":\"ESP32-S3\"}";
 pub(String(MQTT_DISCOVERY_PREFIX)+"/number/rainmaster_raindelay/config","{\"name\":\"Regenstop\",\"unique_id\":\"rainmaster_raindelay\",\"command_topic\":\"beregening/command/raindelay/set\",\"state_topic\":\"beregening/status/raindelay\",\"min\":0,\"max\":"+String(MAX_RAIN_DELAY_DAYS)+",\"step\":1,\"mode\":\"box\",\"unit_of_measurement\":\"dagen\",\"icon\":\"mdi:weather-rainy\","+d+"}");
 pub(String(MQTT_DISCOVERY_PREFIX)+"/number/rainmaster_waterbudget/config","{\"name\":\"Waterbudget\",\"unique_id\":\"rainmaster_waterbudget\",\"command_topic\":\"beregening/command/waterbudget/set\",\"state_topic\":\"beregening/status/waterbudget\",\"min\":"+String(WATER_BUDGET_MIN)+",\"max\":"+String(WATER_BUDGET_MAX)+",\"step\":5,\"mode\":\"slider\",\"unit_of_measurement\":\"%\",\"icon\":\"mdi:water-percent\","+d+"}");
 pub(String(MQTT_DISCOVERY_PREFIX)+"/binary_sensor/rainmaster_master/config","{\"name\":\"Beregening actief\",\"unique_id\":\"rainmaster_master\",\"state_topic\":\"beregening/status/master\",\"payload_on\":\"ON\",\"payload_off\":\"OFF\","+d+"}");
 pub(String(MQTT_DISCOVERY_PREFIX)+"/sensor/rainmaster_next_run/config","{\"name\":\"Volgende beregening\",\"unique_id\":\"rainmaster_next_run\",\"state_topic\":\"beregening/status/next_run\",\"icon\":\"mdi:calendar-clock\","+d+"}");
}
