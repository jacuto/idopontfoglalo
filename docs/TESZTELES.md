# Ellenőrzések és megismétlésük

## Végleges ellenőrzés

- PHP **8.2.32**, PHPUnit **11.5.57**.
- Külön **MariaDB 11.4** tesztadatbázis: **69 sikeres teszt, 389 ellenőrzés**.
- Külön SQLite memóriabeli adatbázis: **69 teszt, 378 ellenőrzés, 2 szándékosan kihagyott többfolyamatos teszt** (67 sikeres); a két valódi párhuzamossági teszt itt szándékosan kihagyott, mert azokhoz InnoDB-zárolás szükséges.
- Valódi Chromium/Playwright: importált, foglalás nélküli vendég keresése; azonos nevek megkülönböztetése; telefonszám-formázás; lefelé/felfelé nyíl, Enter, Esc és kiválasztás; vendégadatlap; 390 px mobilnézet vízszintes oldal-túlcsordulás nélkül; avatarigazítás; nyilvános szolgáltatás mellől foglalásindítás. Nem volt böngészős JavaScript-hiba.
- Az összes alkalmazás-, útvonal-, konfiguráció-, migráció- és PHP-tesztfájl szintaktikai ellenőrzése.
- Az öt böngészős JavaScript-fájl és a Playwright-szkript `node --check` ellenőrzése.
- `git diff --check`.

A tesztek között szerepelnek a korábbi, megőrzött naptár-/foglalás-/import-/értesítés-/OAuth-/Stripe-/Billingo-/Számlázz.hu-tesztek. Az új tesztek ellenőrzik az adatelkülönítést, a kézi küldés sorainak elkülönítését, a maszkolt főadminkulcsokat, a hamis vendégfiók-hozzárendelés tilalmát, a titkos link egyszeri kapcsolását, a megjelenést/értékelést, a kuponár-pillanatképet, a bérlet egyszeri visszaadását és meg nem jelenéskori fogyasztását, az üzeneteket, hírlevél-hozzájárulást/leiratkozást/küldési sort, előfizetési jóváírást/számlázást és Stripe-eseményismétlést, a csomag naptárkeretét, valamint az adatmegőrző frissítés ismételhetőségét.

A `ConcurrencyTest` **két külön PHP-folyamatot** indít ugyanazon MariaDB-rekord ellen. Külön vizsgálja az egyetlen bérletalkalom és az egyetlen kuponfelhasználás versenyhelyzetét. Mindkét esetben pontosan egy foglalás sikerül; a másik érthető jogosultsági/kerethibával áll meg. Nem memóriabeli utánzat teszteli a zárolást.

A bővített böngészős ellenőrzés a feltételes kuponmezőt, a szolgáltatás → nap → időpont sorrendet (az iframe nélküli modulban is), a követőkódok hiányát, a vendéglistás eltávolítást, hívási/üzenetküldési hivatkozásokat, az Esc-/háttérkattintásos bezárást, a függő lista megnyitását, az egyszerűsített menüt, az előző hónapot/30 napot/napi grafikont és a főadmin havi díjlistáját is ellenőrzi. Új backendtesztek fedik a központi elérhetőségek védelmét, a helyi vendégeltávolítást, a 0 Ft-os próbatételeket, a 21 napos határidőt, régebbi tartozás megőrzését újabb hónap kifizetésekor, a Stripe-metaadatok és titkok szétválasztását, valamint a mintaadat-feltöltés védelmét és ismételhetőségét. A Stripe-munkamenet mentése előtt érkező esemény újrapróbálását, a meglévő nyitott Stripe-számla újrafelhasználását és az utolsó 48 próbaóra kezelését is teszteljük.

## Mit nem bizonyítanak ezek a tesztek?

A külső OAuth-, Stripe-, Billingo-, Számlázz.hu- és BulkGate-válaszok szimuláltak. Valós kulcs, valódi kézbesítés, tényleges kártyaterhelés vagy éles számla nem történt. Az üzemeltető külső fiókjain, a konkrét Tárhely.eu-konfiguráción, az éles adatbázis másolatán és valós felhasználókkal külön átvételi próba szükséges. Nem történt nagy terheléses, több ezer szolgáltatós kapacitásmérés.

## Backendtesztek futtatása

Csak **külön fejlesztői checkoutban**, üres/tesztcélú `storage` könyvtárral futtasd, ne az éles könyvtárban. A tesztek törlik és újraépítik a tesztadataikat. Nem SQLite esetén a beépített ellenőrzés csak `_test` végű adatbázisnevet enged; ez nem helyettesíti a helyes adatbázis kiválasztását.

```sh
composer install
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
php vendor/bin/phpunit
```

A repó `phpunit.xml` fájlja alapból SQLite `:memory:` adatbázist, nem bizalmas teszt-APP_KEY-t és tesztkörnyezetet állít be. Ehhez PDO SQLite is kell.

### MariaDB/MySQL

Hozz létre külön `raero_test` adatbázist és kizárólag arra jogosult felhasználót. A külön fejlesztői példány `.env.testing` fájljában add meg az adatokat; ne tedd őket parancssori argumentumba vagy GitHubra. Ne tölts be éles külső integrációs kulcsokat.

A `work/phpunit-mariadb.xml` fájlba:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../vendor/autoload.php" colors="true" cacheDirectory="../.phpunit.cache">
  <testsuites><testsuite name="MariaDB"><directory>../tests</directory></testsuite></testsuites>
  <php>
    <env name="APP_ENV" value="testing"/>
    <env name="CACHE_STORE" value="array"/>
    <env name="APP_KEY" value="base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI="/>
    <env name="SESSION_SECURE_COOKIE" value="false"/>
  </php>
</phpunit>
```

A `.env.testing` tartalmazza a **tesztadatbázis** `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE=raero_test`, `DB_USERNAME`, `DB_PASSWORD` beállításait. Ezzel:

```sh
php vendor/bin/phpunit --configuration work/phpunit-mariadb.xml
```

A többfolyamatos teszt átadja az ellenőrzött tesztkapcsolatot a két munkafolyamatnak. A jelszó nem szerepel a folyamat parancssori argumentumai között. A tesztkörnyezetnek engednie kell a helyi adatbázis-kapcsolatot és az alfolyamatindítást.

## Böngészős ellenőrzés

A `tests/browser.cjs` és a `tests/refinements.cjs` a meglévő alkalmazást nyitja meg, nem külön demót. Ehhez külön helyi SQLite-példány és a `tests/support/browser-fixture.php` tesztadat-előkészítő szükséges. A fixture csak az alkalmazás `work/` könyvtára alatti SQLite-adatbázison fut; két mesterséges vendéget készít az egyforma nevek tesztjéhez.

Példa külön fejlesztői checkoutban, POSIX shellben:

```sh
mkdir -p work storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
export APP_ENV=testing APP_DEBUG=true APP_URL=http://127.0.0.1:8088 SESSION_SECURE_COOKIE=false
export DB_CONNECTION=sqlite DB_DATABASE="$PWD/work/browser-test.sqlite"
export APP_KEY=base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=
export ADMIN_PASSWORD_HASH="$(php -r 'echo password_hash("Browser-test-password", PASSWORD_BCRYPT);')"
touch "$DB_DATABASE"
php tests/support/browser-fixture.php
cd public
php -S 127.0.0.1:8088 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

A szerver fusson ebben a terminálban. Egy másik terminálban, az alkalmazás gyökeréből, telepített Playwrighttal és Chromiummal:

```sh
node tests/browser.cjs
node tests/refinements.cjs
```

Ha a rendszer saját Chromiumját használod:

```sh
CHROMIUM_EXECUTABLE=/usr/bin/chromium node tests/browser.cjs
node tests/refinements.cjs
```

A Playwright külön, ideiglenes fejlesztői `work/` könyvtárba telepíthető; nem kell Node.js az alkalmazás éles futtatásához. A szkript `RAERO_TEST_URL` és `RAERO_BROWSER_PASSWORD` környezeti változóval másik, szándékosan kijelölt tesztpéldányra is állítható. A képernyőképeket a `work/` könyvtárba írja.
