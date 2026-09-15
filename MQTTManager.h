#pragma once
#include <Arduino.h>
class MQTTManager {
public:
 void begin(); void loop(); bool connected()const; void publishState(bool retained=true); void publishDiscovery();
 const String& broker()const{return _broker;} uint16_t port()const{return _port;} const String& user()const{return _user;}
 void setBroker(const String&); void setPort(uint16_t); void setUser(const String&); void setPassword(const String&);
private:
 String _broker,_user,_password; uint16_t _port=1883; uint32_t _lastReconnect=0,_lastPublish=0; bool _discoveryPublished=false;
 void load();void save();bool connect();void callback(char*,byte*,unsigned int);
 void publishZoneDiscovery(uint8_t);void publishNumberDiscovery(uint8_t);void publishSwitchDiscovery();void publishStatusDiscovery();
 void publishScheduleDiscovery(uint8_t);void publishGlobalScheduleDiscovery();
 friend void mqttCallbackBridge(char*,byte*,unsigned int);
};
extern MQTTManager MQTT;
