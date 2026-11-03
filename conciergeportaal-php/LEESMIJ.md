# Conciërgeportaal — PHP-versie voor Strato

Dit is het conciërgeportaal in PHP + MySQL. Het draait op gewone webhosting zoals Strato:
geen Node.js, geen eigen server, geen extra kosten.

## Wat heb je nodig?

- Een hostingpakket met **PHP 8.1 of nieuwer** en een **MySQL-database** (bij Strato standaard).
- Een FTP-programma, bijvoorbeeld [FileZilla](https://filezilla-project.org) (gratis).

## Installeren in 5 stappen

### 1. Database aanmaken
Log in bij Strato → **Databases en webspace** → **Database beheren** → **Database aanmaken**.
Noteer de databasenaam (bijv. `dbs1234567`), de gebruikersnaam (bijv. `dbu1234567`),
het wachtwoord en de server (meestal `rdbms.strato.de`).

### 2. PHP-versie controleren
Zet in de Strato-klantenlogin de PHP-versie op **8.1 of hoger** (8.3 is prima).

### 3. Bestanden uploaden
Pak het zip-bestand uit en upload **de inhoud** van de map naar je webruimte, bijvoorbeeld naar een map
`portaal`. Dan wordt het adres `https://jouwdomein.nl/portaal`.

> **Let op:** de bestanden `.htaccess` en `.user.ini` beginnen met een punt en zijn daardoor vaak
> onzichtbaar. Zet in FileZilla **Server → Verborgen bestanden tonen** aan en controleer dat ze
> mee zijn geüpload (ook `app/.htaccess` en `uploads/.htaccess`). Zonder deze bestanden werkt het
> portaal niet goed en is het minder veilig.

Liever een eigen adres zoals `portaal.jouwdomein.nl`? Maak in Strato bij **Domeinen** een subdomein aan
en laat het naar de map `portaal` wijzen. Zet daar ook **SSL (https)** aan.

### 4. Installatiescherm doorlopen
Open `https://jouwdomein.nl/portaal` in je browser. Je komt vanzelf op het installatiescherm.
- Alle controles moeten op **OK** staan.
- Vul de databasegegevens uit stap 1 in.
- Kies een **registratiecode** (aanrader): alleen wie die code kent kan een account aanmaken.

### 5. Eerste account aanmaken
Na de installatie maak je het eerste account aan. **Dat account wordt automatisch beheerder.**
Ga daarna naar **Beheer** en vervang de voorbeeldscholen door jullie echte scholen
(tik op de kaart om een school te plaatsen). Geef tot slot de registratiecode aan de conciërges.

Op de telefoon kunnen conciërges het portaal via **Delen → Zet op beginscherm** als app installeren.

## Grote video's

Hoe groot een upload mag zijn, bepaalt je hostingpakket. Het portaal vraagt via `.user.ini` om 64 MB per
bestand, maar het maximum van je pakket gaat voor. Het formulier toont het echte maximum.
Te grote video's kun je op YouTube zetten (eventueel als "niet vermeld") en als link bij de tutorial plakken;
de video wordt dan in de pagina getoond.

## Back-up

- **Database:** in de Strato-klantenlogin kun je een back-up maken/terugzetten, of exporteren via phpMyAdmin.
- **Bestanden:** download af en toe de map `uploads/` via FTP.
- Bewaar ook `config.php` (hierin staan de databasegegevens).

## Problemen oplossen

| Probleem | Oplossing |
|---|---|
| Elke pagina behalve de hoofdpagina geeft "Not Found" | `.htaccess` is niet geüpload (verborgen bestand). Zie stap 3. |
| "Kan niet verbinden met de database" | Controleer server, databasenaam, gebruiker en wachtwoord. Een nieuwe Strato-database kan een paar minuten nodig hebben voordat hij werkt. |
| Installatie zegt "map uploads/ is niet schrijfbaar" | Geef de map `uploads` in FileZilla (rechtermuisknop → Bestandsrechten) de rechten 755. |
| Opnieuw installeren | Verwijder `config.php` via FTP en open de site opnieuw. De gegevens in de database blijven bewaard. |
| Ergens een foutmelding "Er ging iets mis" | De details staan in het foutenlogboek van je hosting (bij Strato in de klantenlogin). |

## Mappen

```
index.php          alle pagina's lopen via dit bestand
install.php        installatiescherm (geblokkeerd na installatie)
config.php         wordt bij de installatie aangemaakt (niet delen!)
app/               de code (afgeschermd voor bezoekers)
  controllers/     logica per onderdeel
  views/           de pagina's
assets/            opmaak, JavaScript, kaart (Leaflet) en icoon
uploads/           geüploade foto's/video's (alleen voor ingelogde gebruikers)
```
