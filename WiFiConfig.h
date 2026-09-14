#pragma once
#include <Arduino.h>
#include <WebServer.h>
#include <DNSServer.h>
class WiFiConfig{public:void begin();void loop();bool connected()const;bool apActive()const{return _apActive;}String ip()const;WebServer& server(){return _server;}private:WebServer _server{80};DNSServer _dns;bool _apActive=false;String _ssid,_pass,_apSsid;void load();void save(const String&,const String&);void startAP();void startSTA();void routes();void root();void scan();void setwifi();void mqtt();void reboot();};extern WiFiConfig WiFiCfg;
