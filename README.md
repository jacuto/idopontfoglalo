# Ráérő — PHP-s időpontfoglaló

**PHP 8.2 + Laravel 12**, MySQL/MariaDB adatbázissal. A Node.js-változatot váltja fel; a naptár és a vendégoldali felület megmaradt. Nem szükséges Node.js, Passenger vagy JavaScript-build. Egyetlen szalon kipróbálható prototípusa, nem kész többvállalkozásos SaaS.

**Már telepítetted a PHP-s változatot?** A frissítési lépések: [FRISSITES.md](FRISSITES.md).

## Telepítés Tárhely.eu cPanelen

### 1. Külön mappa és adatbázis

A működő főoldalad mappáját ne módosítsd. A PHP-s változat kerüljön **új mappába**, például:

```text
/home/digitmar/idopontfoglalo-php/
    artisan
    composer.json
    composer.lock
    app/
    public/
        index.php
        .htaccess
        app.js
        style.css
```

A GitHub ZIP-et kibontva a belső projektmappa tartalmát töltsd ide, a rejtett `.htaccess` és `.env.example` fájlokkal együtt. Ne tölts fel saját `.env` fájlt a GitHubra. A `vendor` mappa a következő lépésben készül el.

cPanel → **MySQL-adatbázis varázsló / Database Wizard**:

1. Hozz létre egy új adatbázist, például `digitmar_foglalo`.
2. Hozz létre hozzá egy külön adatbázis-felhasználót, erős jelszóval.
3. Rendeld a felhasználót ehhez az adatbázishoz, **ALL PRIVILEGES** jogosultsággal.

A cPanel által kijelzett teljes, előtagos neveket használd. A meglévő weboldalad adatbázisát ne válaszd.

### 2. Telepítés SSH-ban

```sh
cd ~/idopontfoglalo-php
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan raero:install
```

A beállító létrehozza a helyi `.env` fájlt és az alkalmazáskulcsot, majd megkérdezi:

- MySQL-kiszolgáló (általában `localhost`);
- adatbázis teljes neve;
- adatbázis-felhasználó teljes neve;
- adatbázis jelszava;
- saját szolgáltatói jelszavad, kétszer, legalább 12 karakterrel.

A jelszavak gépelés közben nem láthatók, ez normális. Az adminjelszóból csak hash kerül a konfigurációba. Jelszót ne küldj chatben, ne tedd GitHubra. A régi Node.js környezeti változók helyett a PHP-s változat saját `.env` fájlt használ.

Ellenőrzés:

```sh
php artisan raero:check
```

A telepítő újrafuttatható: a meglévő adatokat és jelszót megtartja. Jelszócsere:

```sh
php artisan raero:install --reset-password
```

### 3. Az aldomain átállítása PHP-ra

Csak a `foglalo.digitmarketing.hu` aldomain beállításait módosítsd.

1. cPanel → Node.js: állítsd le, majd szüntesd meg **ennek az aldomainnek** a Node.js-alkalmazás-hozzárendelését. A mentett régi projektfájlokat/adatokat tartsd meg. A régi Passenger-hozzárendelés nem szolgálhatja ki tovább ezt a címet.
2. cPanel → Domains / Domainek → aldomain kezelése: dokumentumgyökér legyen **`/home/digitmar/idopontfoglalo-php/public`**. Relatív mező esetén `idopontfoglalo-php/public`.
3. MultiPHP Manager: ennél az aldomainnél **PHP 8.2** vagy újabb.
4. Az aldomain HTTPS-tanúsítványa legyen érvényes.
5. Nyisd meg: `https://foglalo.digitmarketing.hu/` — szolgáltatói belépés.
6. Vendégoldal: `https://foglalo.digitmarketing.hu/foglalas`.

A dokumentumgyökér kizárólag a **public** mappa legyen; ne a teljes projekt. Az alkalmazás, a `.env`, az adatbázisadatok és a naplók nem publikus fájlok. A gyökér `.htaccess` véletlen rossz feltöltés esetén tiltja a hozzáférést; a public mappában lévő külön szabályok a PHP-belépőre irányítanak.

Ha a cPanel nem engedi a dokumentumgyökeret a `public_html` mappán kívülre helyezni, ne másold ki önmagában a `public` tartalmát és ne módosítsd találomra az útvonalakat. Egyeztess a szolgáltatóval az elkülönített Laravel dokumentumgyökérről; a tényleges tárhelybeállítás alapján más elrendezés is készíthető.

### 4. Hibakeresés és frissítés

Napló: **`storage/logs/laravel.log`**, már nem a Passenger naplója.

- „It works!”: még a Node.js tesztalkalmazás szolgálja ki a címet.
- 403: ellenőrizd, hogy a dokumentumgyökér a PHP-s projekt `public` mappája-e.
- 500: nézd meg a Laravel-naplót; ellenőrizd a `vendor`, a `.env`, az alkalmazáskulcs és az adatbázis beállítását.
- Írási jogosultság: a `storage` és `bootstrap/cache` könyvtárakba a tárhely PHP-folyamatának írnia kell. Ne használj 777-es jogosultságot.
- `APP_DEBUG=false` maradjon nyilvános weboldalon.

Későbbi frissítés előtt mentsd az adatbázist és a `.env` fájlt. Frissítéskor ezeket ne írd felül, és soha ne futtass `migrate:fresh` parancsot valós adatokon.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan raero:check
```

## Újdonságok és kapcsolatok

A működő PHP-s verzió frissítése, az SMTP/cron, az SMS, a közösségi belépés, a számlázás, a Stripe és a webkomponens beállítása: **[FRISSITES.md](FRISSITES.md)**.

- Kontrasztosabb, reszponzív felület; több sorba rendezett munkatárs- és szolgáltatáskártyák, középre rendezett avatarok.
- Napi/heti/havi naptár, gyors időpontfelvitel, munkatársszűrés, percre pontos nyitás és zárás.
- Egyéni vagy csapatműködés, egyéni módban rejtett munkatársmenü és szűrő.
- Vendégadatok szerkesztése; munkatársak szerkesztése és archiválása, történeti adatok megtartásával.
- Vendég-, munkatárs- és foglalás-CSV import/export; atomi, ellenőrzött import, kezelőtokenek exportja nélkül.
- Tényleges befizetések készpénzzel/kártyával/átutalással; időszakszűrős bevételi kördiagram, szolgáltatásonkénti felosztással.
- E-mail a vendégnek és a szolgáltatónak külön kapcsolóval; időzített e-mail/SMS emlékeztető, tartós küldési sorral és újrapróbálással.
- SMTP, Twilio, Stripe vendégfizetés és aláírt webhook, Billingo és Számlázz.hu számlakiállítási kapcsolatok. Valódi használathoz saját hozzáférés és szolgáltatói beállítás szükséges.
- Google/Facebook vendégregisztráció és belépés; adminbelépés csak engedélyezett fiókoknak. A jelszavas adminbelépés megmaradt.
- Számlázási adatok, titkosított kapcsolati kulcsok; az admin API nem adja vissza a titkos kulcsértékeket.
- Iframe nélküli, Shadow DOM-os foglaló webkomponens, engedélyezett gazdaoldalakkal és harmadik fél sütije nélkül.
- Google Analytics, Tag Manager, Ads és Meta Pixel azonosítók; a saját foglalóoldalon csak hozzájárulás után induló mérés.

Megmaradt a több szolgáltatásos foglalás, az eltérő ár/időtartam, a hatóidős párhuzamosság, a jóváhagyás és a titkos linkes vendégmódosítás/lemondás. A MySQL-tranzakció a párhuzamos foglalást ellenőrzi.

## Még nem teljes termék

Egy szalon munkaterülete működik. A több önálló vállalkozás regisztrációja/adatelkülönítése, wildcard aldomainek, központi csomagkorlátozás, két hónapos próbaidő-kezelés, átutalásos előfizetés és a platform előfizetési Billingo-számlái további fejlesztést igényelnek. A közösségi vendégregisztráció nem hoz létre új vállalkozást. Előlegszámla, automatikus sztornó, szabadság- és heti munkarend-kezelés sincs ebben a változatban.

A külső API-kat HTTP-tesztválaszokkal ellenőrizzük; az éles Google/Facebook/Stripe/számlázó fiókot a saját hozzáférésekkel kell hitelesíteni és végigpróbálni. Ez nem jelenti azt, hogy a távoli fiókok már össze vannak kapcsolva.

## Fejlesztés és tesztelés

```sh
composer install
php vendor/bin/phpunit
```

A tesztek külön SQLite-adatbázissal futnak; ellenőrzik a foglalási szabályokat, szerkesztést/archiválást, importot, pénzügyi műveleteket, outboxot, OAuth-jogosultságokat, CORS-t és API-kapcsolatokat. SMTP-küldést külön helyi tesztszerverrel, böngészős folyamatokat Chromiumban, valamint webkomponenst eltérő weboldalról is ellenőriztünk.

Helyi SQLite: a `.env` fájlban `DB_CONNECTION=sqlite`, `DB_DATABASE` egy meglévő üres SQLite-fájl abszolút útvonala, `APP_URL=http://localhost:8000`, `SESSION_SECURE_COOKIE=false`. Utána `php artisan raero:install`, majd:

```sh
php -S localhost:8000 -t public public/index.php
```

A beépített PHP-kiszolgáló csak fejlesztéshez való. A szolgáltatói dátummezőket magyarországi időzónájú böngészőben használd. A valódi tárhelyhez MySQL/MariaDB és HTTPS szükséges.
