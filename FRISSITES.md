# Tárhely.eu-frissítés – meglévő alkalmazáshoz

Ez az útmutató a `/home/digitmar/idopontfoglalo-php` meglévő alkalmazásához készült. A webcím `https://foglalo.digitmarketing.hu/`, a dokumentumgyökér változatlanul `/home/digitmar/idopontfoglalo-php/public`. **A digitmarketing.hu főoldalának könyvtárát és beállításait egyik lépés sem módosítja.**

A fejlesztés külön tesztadatbázisokon készült. A GitHub-verzió feltöltése nem jelent éles telepítést. Az alábbi tárhelyműveleteket te végzed el.

## 1. Töltsd le a konkrét átadott verziót

Az átadási üzenetben megadott commit ZIP-hivatkozását használd, ne egy később esetleg megváltozó `main` ZIP-et. A ZIP-ben lévő belső projektmappát nevezd át `raero-frissites` névre. Ebben a mappában közvetlenül legyen az `artisan`, a `composer.json`, az `app` és a `public`.

cPanel → Fájlkezelő: töltsd fel ezt a mappát **`/home/digitmar/raero-frissites`** alá. Még ne másold rá a működő alkalmazásra. A mellékelt, külön frissítő ZIP közvetlenül ebbe a mappába is kibontható.

A csomag nem tartalmaz éles `.env`-et, alkalmazáskulcsot, adatbázist, feltöltéseket vagy telepített `vendor`-t. A függőségeket a Composer telepíti.

## 2. Először próbáld ki külön másolaton

cPanelen készíts külön tesztadatbázist és külön alkalmazásmappát. A meglévő adatbázis exportját **ebbe a külön tesztadatbázisba** importáld. A másolat saját `.env`-jében ennek az adatait állítsd be, a régi APP_KEY megtartásával. A tesztmásolatban kapcsold ki a valós email-/SMS-küldést, a Stripe-ot és az automatikus számlázást; ne futtasd a másolt küldési sort éles kulcsokkal. Ne ugyanazt az éles adatbázist használd a két példányhoz.

A másolaton végezd el az alábbi frissítést és ellenőrzést. Éles frissítést csak a másolat ellenőrzése után végezz.

## 3. Ellenőrizd az SSH-ban használt PHP-t

Lépj be SSH-val, majd:

```sh
cd /home/digitmar/idopontfoglalo-php
pwd
php -v
composer --version
```

A `pwd` pontosan az alkalmazás könyvtárát mutassa. PHP 8.2 vagy újabb szükséges. Ha a `php` más verziót indít, a tárhely által megadott PHP 8.2-es teljes elérési utat használd minden `php` parancs helyett. cPanelen gyakori: `/opt/cpanel/ea-php82/root/usr/bin/php`; ezt a saját tárhelyeden ellenőrizd. Szükséges bővítmények: PDO MySQL, mbstring, OpenSSL, fileinfo, XML/DOM/SimpleXML, ctype, tokenizer; a Composer ezek hiányát is jelezheti.

## 4. Állítsd meg az írásokat és készíts teljes mentést

cPanel → Cron-feladatok: ideiglenesen kapcsold ki a Ráérő `schedule:run` sorát. A feladatot ne töröld el nyomtalanul: előbb másold ki a szövegét.

SSH:

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan down --retry=60
umask 077
RAERO_BACKUP_DIR="/home/digitmar/raero-mentes-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$RAERO_BACKUP_DIR"
tar -czf "$RAERO_BACKUP_DIR/alkalmazas.tar.gz" -C /home/digitmar idopontfoglalo-php
printf 'A mentés könyvtára: %s\n' "$RAERO_BACKUP_DIR"
tar -tzf "$RAERO_BACKUP_DIR/alkalmazas.tar.gz" >/dev/null
```

Ez a mentés a `.env`-et, a feltöltéseket, a `storage` tartalmát és a titkosított kapcsolati fájlokat is tartalmazza. **Nem helyettesíti az adatbázismentést.**

cPanel → phpMyAdmin → a `.env`-ben megadott éles adatbázis → Exportálás → SQL → összes tábla, szerkezet és adatok. Mentsd le a teljes SQL-exportot, és őrizd meg a fenti fájlmentéssel együtt. Ellenőrizd, hogy nem üres és a foglalásokat tároló `salon_state` is benne van. Ha a mentés hibás vagy hiányos, ne folytasd.

A mentés titkokat és vendégadatokat tartalmaz. Ne tedd weben elérhető mappába vagy GitHubra; korlátozott hozzáférésű helyen őrizd meg.

## 5. Másold be csak a programfájlokat

Előbb ellenőrizd a feltöltött könyvtárat:

```sh
test -f /home/digitmar/raero-frissites/artisan
test -f /home/digitmar/raero-frissites/app/Services/Tenant.php
test -f /home/digitmar/raero-frissites/public/features.js
```

Mindhárom parancsnak hiba nélkül kell lefutnia. Ezután:

```sh
cp -a /home/digitmar/raero-frissites/app /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/bootstrap /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/config /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/database /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/public /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/resources /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/routes /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/artisan /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/composer.json /home/digitmar/idopontfoglalo-php/
cp -a /home/digitmar/raero-frissites/composer.lock /home/digitmar/idopontfoglalo-php/
```

A másolás összefésüli a mappákat; nem törli a meglévő feltöltéseket. A `.env`, a `storage`, a saját titkosított kulcsfájlok és a `vendor` nem szerepelnek a másolási listában. **Ne másold rá a teljes ZIP tartalmát olyan programmal, amely előbb kiüríti a célmappát.**

## 6. Telepítsd a függőségeket és frissítsd az adatbázist

```sh
cd /home/digitmar/idopontfoglalo-php
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
php artisan route:list --path=api/platform
```

A `raero:upgrade` elindítja az új, adatokat hozzáadó migrációkat, megőrzi az eredeti szalonrekordot, és az előfizetés nélküli meglévő vállalkozásnak a frissítéskor egyszer két hónap próbaidőt ad. Ismételt futtatáskor nem indítja újra a próbaidőt. A már működő munkatársak számát megőrzi naptárkeretként. A már meglévő Stripe-előfizetést nem írja felül.

Ne futtasd: `migrate:fresh`, `migrate:refresh`, `db:wipe`, `migrate:rollback`, `key:generate`. Meglévő telepítés frissítéséhez a `raero:install` és a jelszó-visszaállítás sem szükséges.

Ha valamelyik parancs hibát jelez, ott állj meg. Ne futtass adatokat törlő parancsot a hiba megkerülésére. A karbantartási mód maradjon bekapcsolva, amíg a hibát kijavítottad vagy visszaállítottad a mentést.

## 7. Külön főadminjelszó

Az eredeti szolgáltatói jelszó megmarad; abból nem lesz főadminjelszó. Az első főadminbelépés előtt egyszer futtasd:

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan raero:platform-password
php artisan config:clear
```

A jelszót a szerver rejtetten kéri, nem a beszélgetés. A parancs csak a `PLATFORM_ADMIN_PASSWORD_HASH` sort írja a meglévő `.env`-be; az APP_KEY-t és az adatbázisadatokat megtartja. A főadmin oldala: `https://foglalo.digitmarketing.hu/foadmin`.

Először az **Üzemeltető számlázási és banki adatai** részben add meg a beszélgetésben megadott saját adatokat. Ezek szándékosan nincsenek a nyilvános ZIP-ben vagy GitHub-kódban.

Itt a **Saját Billingo, Stripe és SMTP** gombnál külön megadhatod az üzemeltető kapcsolatait. A szolgáltatói Beállításokban továbbra is a szalon saját kulcsai vannak. A külső fiókok beállításai: [KAPCSOLATOK.md](docs/KAPCSOLATOK.md).

## 8. Ellenőrzés és visszakapcsolás

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan up
```

Böngészőben ellenőrizd:

1. Belépsz a korábbi szolgáltatói jelszóval; a saját vendégek, foglalások, szolgáltatások, munkatársak és befizetések megvannak.
2. Az „Új időpont” ablakban egy korábban importált vendéget névvel és telefonszámmal is megtalálsz. A kiválasztott vendégnél nincs új másolat.
3. Megnyílik egy régi foglalás. A múltbeli, függő foglalás elfogadási hibája érthető útmutatást ad.
4. A nyilvános foglaló működik; a privát számlázási és API-adatok nincsenek rajta.
5. Mobilon látszanak a menüpontok nevei és használható az „Új időpont” gomb.
6. A főadmin külön jelszót kér. A frissen felvett második szolgáltató nem látja az első vendégeit.

Ha ezek rendben vannak, kapcsold vissza az **egyetlen** cron-sort a cPanelen. PHP 8.2-es szokásos cPanel-példa:

```cron
* * * * * /opt/cpanel/ea-php82/root/usr/bin/php /home/digitmar/idopontfoglalo-php/artisan schedule:run >> /home/digitmar/idopontfoglalo-php/storage/logs/cron.log 2>&1
```

Ha nálad más a PHP 8.2 teljes elérési útja, csak azt cseréld a sor elején. A cron az értesítőket és a hírleveleket is feldolgozza. Ne készíts ugyanarra a rendszerre több párhuzamos cron-sort.

## 9. Visszaállítás hiba esetén

Kapcsold ki a cront és a látogatói hozzáférést. A 4. lépésben készült **teljes alkalmazásmentést és a hozzá tartozó teljes SQL-mentést együtt** állítsd vissza, a mentéskori `.env`-vel, APP_KEY-vel és feltöltésekkel. Ezt célszerű a tárhely támogatásával vagy az előzetesen kipróbált tesztmásolaton begyakorolva elvégezni.

Az új migrációk visszafelé szándékosan nem törlik a táblákat. Ne próbálj `migrate:rollback` paranccsal visszaállni. A mentés után felvett új adatokat a teljes visszaállítás elveszítheti: ezért a visszaállítást még a rendszer újra megnyitása előtt végezd, vagy egyeztess az adatok külön mentéséről.
