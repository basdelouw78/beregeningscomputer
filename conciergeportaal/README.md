# Conciërgeportaal

Webapp voor de conciërges van alle scholen binnen de organisatie. Werkt prettig op de telefoon
(mobile-first, onderbalk-navigatie, te installeren als app via "Zet op beginscherm") en op de computer.

## Functies

- **Inloggen / registreren** met e-mail + wachtwoord (wachtwoorden versleuteld met bcrypt).
  De **eerste** gebruiker die zich registreert wordt automatisch beheerder.
- **Setup na registratie**: kies op een **landkaart** (of in de lijst) op welke school/scholen je werkt
  en geef per school per dag je begin- en eindtijd op. Daarna ga je naar de hoofdpagina.
  Later aan te passen via *Mijn locaties*.
- **Hoofdpagina** met tegels naar alle onderdelen, wie er vandaag aanwezig is (met belknop),
  je eigen rooster, de volgende meeting en de nieuwste vragen.
- **Materialen uitlenen**: materiaal aanbieden, lenen (met terugbrengdatum en locatie), terugmelden.
  Te laat? Dan krijgt het een rode markering.
- **Meeting plannen**: meetings aanmaken, aan-/afmelden, zien wie er komt.
- **Vragen & antwoorden**: vraag stellen, antwoorden, markeren als opgelost, zoeken.
- **Tutorials**: handleidingen uploaden met stappen, foto's, video's en PDF's (op de telefoon kun je
  direct een foto maken). Te filteren op categorie.
- **Collega's**: kaart + lijst van wie waar werkt, met bel- en mailknop.
- **Beheer** (alleen beheerders): scholen toevoegen door op de kaart te tikken, wijzigen, verwijderen,
  en beheerders aanwijzen.

## Starten

Benodigd: [Node.js](https://nodejs.org) **22.13 of nieuwer** (de database is SQLite, ingebouwd in Node;
er hoeft geen aparte database-server geïnstalleerd te worden).

```bash
cd conciergeportaal
npm install
npm start
```

Open daarna <http://localhost:3000> en maak het eerste account aan (dat wordt de beheerder).
Bij de eerste start staan er zes voorbeeldscholen op de kaart; pas die aan via **Beheer**.

## Instellingen (omgevingsvariabelen)

| Variabele          | Betekenis                                                                 |
|--------------------|---------------------------------------------------------------------------|
| `PORT`             | Poort van de webserver (standaard `3000`)                                 |
| `REGISTRATIE_CODE` | Als ingesteld moet iedereen deze code invullen bij registreren (aanrader!) |
| `SESSION_SECRET`   | Geheim voor de inlogcookies (anders wordt er een in `data/` aangemaakt)  |
| `NODE_ENV`         | Zet op `production` als de site via HTTPS draait (veilige cookies)       |
| `DATA_DIR`         | Map voor de database (standaard `./data`)                                 |
| `UPLOAD_DIR`       | Map voor geüploade tutorialbestanden (standaard `./uploads`)              |

Voorbeeld:

```bash
REGISTRATIE_CODE=conciërge2026 NODE_ENV=production npm start
```

## Back-up

Alles staat in twee mappen: `data/` (database) en `uploads/` (bestanden). Kopieer die regelmatig.

## Techniek

Node.js + Express, EJS-templates, SQLite (`node:sqlite`), Leaflet + OpenStreetMap voor de kaart,
multer voor uploads. Beveiliging: bcrypt-wachtwoorden, CSRF-tokens op alle formulieren,
sessies in de database, beperking op het aantal inlogpogingen, uploads alleen zichtbaar voor ingelogde
gebruikers.

## Mappen

```
server.js          app-opbouw en middleware
db.js              databaseschema + voorbeeldscholen
routes/            pagina's per onderdeel (auth, setup, materialen, meetings, vragen, tutorials, admin)
views/             EJS-templates
public/            stylesheet, JavaScript, app-icoon
```
