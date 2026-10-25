# Ráérő — PHP-s időpontfoglaló

**PHP 8.2 + Laravel 12**, MySQL/MariaDB adatbázissal. A Node.js-változatot váltja fel; a naptár és a vendégoldali felület megmaradt. Nem szükséges Node.js, Passenger vagy JavaScript-build. Egyetlen szalon kipróbálható prototípusa, nem kész többvállalkozásos SaaS.

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

## Kipróbálható funkciók

- Heti naptár, munkatársszűrés, gyors időpontfelvitel üres cellára kattintva.
- Jóváhagyás, elutasítás, időpontmódosítás, lemondás.
- Szolgáltatások ára, időtartama, munkatársai, automatikus megerősítése szerkeszthető.
- Fodrászati munka–hatóidő–munka szakaszok, párhuzamos foglalás a szabad hatóidőben.
- Több szolgáltatás egy foglalásban, ugyanannál a szakembernél egymás után.
- Munkatársak felvétele; vendégoldalon konkrét szakember vagy „Bárki megfelel”.
- Valós szabadidőpont-lista, budapesti időzónában; az ütközést mentéskor újra ellenőrzi.
- Titkos hivatkozással vendég általi módosítás és lemondás, regisztráció nélkül.
- Vendégtörzs, szolgáltatói keresés/autokitöltés, CSV-import/export (`name;phone;email`).
- Nyitás/zárás, foglalási szabályok, emlékeztető-preferenciák mentése, beágyazókód.
- Session alapú belépés/kilépés, CSRF-védelem az adminműveleteknél, belépési próbálkozások korlátozása.
- MySQL-tranzakció és szalonsor-zárolás az egyidejű foglalási ütközések megelőzésére.

Az árakat és időtartamokat a foglalás rögzítéskor pillanatképként megőrzi. A szabad időpontok Budapest szerint számolódnak; a szolgáltatói dátummezőket magyarországi időzónájú böngészővel használd.

## Még nem megvalósított funkciók

- Több vállalkozás regisztrációja és adatelkülönítése, saját aldomainek, vendégfiókok.
- Stripe, átutalás-egyeztetés, Billingo és Számlázz.hu.
- Tényleges előfizetés, 2 hónapos próbaidő számlálása, csomagkorlátozások. A 4 000 Ft/hó ár üzleti tervként látható.
- E-mail, SMS, meghívó és emlékeztető **küldése**. A beállítások mentése nem jelent üzenetküldést.
- Heti munkarend, szabadság, puffer, lemondási határidő, teljes auditnapló.
- Módosítási kérés alatt az eredeti időpont fenntartása; jelenleg az időpont átíródik, és szükség esetén jóváhagyásra kerül.
- A régi Node.js SQLite-adatainak automatikus átvétele; azok megmaradnak a régi mappában.

Cron most nem szükséges a kipróbáláshoz. A későbbi emlékeztetőknek Laravel ütemezett feladat és valódi küldőkapcsolat kell; jelenleg nincs üzenetküldő cronfeladat. Valós szolgáltatásként indulás előtt további fejlesztés és üzemeltetési/adatvédelmi ellenőrzés szükséges.

## Fejlesztés és tesztelés

```sh
composer install
php vendor/bin/phpunit
```

A tesztek külön, memóriában lévő SQLite-adatbázissal működnek. PHP 8.2 alatt SQLite-tal és külön MariaDB-tesztadatbázissal is sikeresen lefutottak; ellenőrzik a bejelentkezést, jogosultságokat, foglalásokat, hatóidős párhuzamosságot, szabad időpontokat és a vendégoldali módosítást/lemondást.

Helyi futtatásra SQLite is választható: `.env`-ben `DB_CONNECTION=sqlite`, `DB_DATABASE` egy meglévő üres SQLite-fájl abszolút útvonala, `APP_URL=http://localhost:8000`, `SESSION_SECURE_COOKIE=false`. Utána `php artisan raero:install` és:

```sh
php -S localhost:8000 -t public public/index.php
```

A beépített PHP-kiszolgáló fejlesztési eszköz; tárhelyen Apache/PHP szolgálja ki az alkalmazást.

A párhuzamos foglalási próbán öt külön PHP-folyamat ugyanazt a MySQL/MariaDB-időpontot kérte: egy foglalás létrejött, négy ütközési hibát kapott. A tényleges Tárhely.eu telepítést a felhasználó végzi; azon a gépen még nem történt ellenőrzés.
