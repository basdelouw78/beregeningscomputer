#include <Arduino.h>
#include <TFT_eSPI.h>
#include <ElegantOTA.h>
#include "config.h"
#include "HealthGuard.h"
#include "WiFiConfig.h"
#include "Irrigation.h"
#include "MQTTManager.h"
TFT_eSPI tft=TFT_eSPI();static const uint16_t cal[5]={TOUCH_CAL_X0,TOUCH_CAL_X1,TOUCH_CAL_Y0,TOUCH_CAL_Y1,TOUCH_CAL_Z};
enum Screen{SPLASH,HOME,ZONES,SETTINGS};static Screen screen=SPLASH;static uint32_t started=0,lastDraw=0,lastTouch=0;static bool bl=true;
static void backlight(bool on){digitalWrite(TFT_BACKLIGHT_PIN,on?TFT_BACKLIGHT_ON_LEVEL:!TFT_BACKLIGHT_ON_LEVEL);bl=on;}static void header(const char*s){tft.fillRect(0,0,480,38,TFT_DARKGREY);tft.setTextColor(TFT_WHITE,TFT_DARKGREY);tft.drawCentreString(s,240,10,2);tft.setTextColor(WiFi.isConnected()?TFT_GREEN:TFT_RED,TFT_DARKGREY);tft.drawString(WiFi.isConnected()?"W":"!",8,10,2);tft.setTextColor(MQTT.connected()?TFT_GREEN:TFT_ORANGE,TFT_DARKGREY);tft.drawString("M",465,10,2);}static void btn(int x,int y,int w,int h,const String&s,uint16_t c){tft.fillRoundRect(x,y,w,h,7,c);tft.drawRoundRect(x,y,w,h,7,TFT_WHITE);tft.setTextColor(TFT_WHITE,c);tft.drawCentreString(s,x+w/2,y+(h-16)/2,2);}static bool hit(int x,int y,int w,int h,uint16_t X,uint16_t Y){return X>=x&&X<=x+w&&Y>=y&&Y<=y+h;}
// Opstartscherm: verticale water->groen gradient met een druppel-logo (natuurlijke uitstraling)
static void drawSplash(){
 for(int y=0;y<320;y++){
  uint8_t r=6+(uint8_t)((20-6)*y/320),g=40+(uint8_t)((110-40)*y/320),b=70-(uint8_t)((70-55)*y/320);
  tft.drawFastHLine(0,y,480,tft.color565(r,g,b));
 }
 int cx=240,cy=100;
 tft.fillTriangle(cx,cy-48,cx-27,cy,cx+27,cy,TFT_CYAN);
 tft.fillCircle(cx,cy,27,TFT_CYAN);
 tft.fillCircle(cx-9,cy-8,8,tft.color565(220,255,255));
 tft.setTextColor(TFT_WHITE);tft.drawCentreString("RainMaster",240,160,4);
 tft.setTextColor(TFT_GREENYELLOW);tft.drawCentreString("Slimme beregening",240,205,2);
 tft.setTextColor(TFT_LIGHTGREY);tft.drawCentreString(FW_VERSION,240,290,2);
}
static void draw(){lastDraw=millis();if(screen==SPLASH){drawSplash();return;}if(screen==HOME){tft.fillScreen(TFT_BLACK);header("BEREGENING");if(IRRIGATION.isRunning()){tft.setTextColor(TFT_GREEN);tft.drawCentreString("BEREGENING ACTIEF",240,55,2);tft.setTextColor(TFT_CYAN);tft.drawCentreString("ZONE "+String(IRRIGATION.activeZone()+1),240,88,4);uint32_t r=IRRIGATION.remainingSeconds();char tm[12];snprintf(tm,sizeof(tm),"%02lu:%02lu",(unsigned long)(r/60),(unsigned long)(r%60));tft.setTextColor(TFT_WHITE);tft.drawCentreString(tm,240,130,6);btn(20,205,200,55,"STOP ALLES",TFT_RED);btn(260,205,200,55,"ZONES",TFT_DARKGREY);}else{tft.setTextColor(TFT_LIGHTGREY);tft.drawCentreString("GEREED",240,62,4);tft.setTextColor(IRRIGATION.autoMode()?TFT_GREEN:TFT_ORANGE);tft.drawCentreString(IRRIGATION.autoMode()?"AUTOMATISCH":"HANDMATIG",240,105,2);btn(20,155,200,55,"START CYCLUS",TFT_DARKGREEN);btn(260,155,200,55,"ZONE KIEZEN",TFT_DARKGREY);btn(20,230,200,50,IRRIGATION.autoMode()?"AUTO: AAN":"AUTO: UIT",IRRIGATION.autoMode()?TFT_DARKGREEN:TFT_DARKGREY);btn(260,230,200,50,"INSTELLINGEN",TFT_DARKGREY);}return;}if(screen==ZONES){tft.fillScreen(TFT_BLACK);header("ZONES");for(uint8_t z=0;z<MAX_ZONES;z++){int x=10+(z%2)*240,y=48+(z/2)*55;bool a=IRRIGATION.isRunning()&&IRRIGATION.activeZone()==z;btn(x,y,225,48,"Zone "+String(z+1)+"  "+String(IRRIGATION.runtime(z)/60)+"m",a?TFT_DARKGREEN:TFT_DARKGREY);}btn(10,278,100,34,"TERUG",TFT_DARKGREY);btn(125,278,160,34,"STOP",TFT_RED);btn(300,278,170,34,"AUTO CYCLUS",TFT_DARKGREEN);return;}tft.fillScreen(TFT_BLACK);header("INSTELLINGEN");tft.setTextColor(TFT_WHITE);tft.drawString("WiFi",20,65,2);tft.drawString(WiFi.isConnected()?WiFi.localIP().toString():"niet verbonden",150,65,2);tft.drawString("MQTT",20,95,2);tft.drawString(MQTT.connected()?"verbonden":"niet verbonden",150,95,2);tft.drawString("Broker",20,125,2);tft.drawString(MQTT.broker().length()?MQTT.broker():"niet ingesteld",150,125,2);tft.drawString("Webconfig via browser",20,160,2);btn(20,205,210,50,"ZONES",TFT_DARKGREY);btn(250,205,210,50,"TERUG",TFT_DARKGREY);}
static void touch(){uint16_t x,y;if(!tft.getTouch(&x,&y))return;lastTouch=millis();if(!bl){backlight(true);return;}if(screen==HOME){if(IRRIGATION.isRunning()){if(hit(20,205,200,55,x,y))IRRIGATION.stopAll();else if(hit(260,205,200,55,x,y))screen=ZONES;}else{if(hit(20,155,200,55,x,y))IRRIGATION.startCycle();else if(hit(260,155,200,55,x,y))screen=ZONES;else if(hit(20,230,200,50,x,y))IRRIGATION.setAutoMode(!IRRIGATION.autoMode());else if(hit(260,230,200,50,x,y))screen=SETTINGS;}}else if(screen==ZONES){for(uint8_t z=0;z<MAX_ZONES;z++){int bx=10+(z%2)*240,by=48+(z/2)*55;if(hit(bx,by,225,48,x,y)){IRRIGATION.startZone(z);screen=HOME;draw();return;}}if(hit(10,278,100,34,x,y))screen=HOME;else if(hit(125,278,160,34,x,y))IRRIGATION.stopAll();else if(hit(300,278,170,34,x,y))IRRIGATION.startCycle();}else if(screen==SETTINGS){if(hit(20,205,210,50,x,y))screen=ZONES;else if(hit(250,205,210,50,x,y))screen=HOME;}draw();}
void setup(){Serial.begin(115200);HealthGuard::Cfg c;HealthGuard::begin(c);pinMode(TFT_BACKLIGHT_PIN,OUTPUT);backlight(true);tft.init();tft.setRotation(1);tft.setTouch((uint16_t*)cal);IRRIGATION.begin();WiFiCfg.begin();ElegantOTA.begin(&WiFiCfg.server());ElegantOTA.setAuth(WEBUI_USER,WEBUI_PASS);MQTT.begin();started=millis();draw();}
void loop(){HealthGuard::loop();WiFiCfg.loop();IRRIGATION.loop();MQTT.loop();touch();if(bl&&millis()-lastTouch>SCREEN_IDLE_TIMEOUT_MS)backlight(false);if(screen==SPLASH&&millis()-started>2500){screen=HOME;draw();}if(screen!=SPLASH&&millis()-lastDraw>1000)draw();HealthGuard::progress();delay(2);}
