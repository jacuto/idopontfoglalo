# Frissítés a működő PHP-s változatról

Ez a verzió **megtartja a jelenlegi foglalásokat, vendégeket, szolgáltatásokat és jelszót**. Nem kell új adatbázist létrehozni vagy újratelepíteni. A tárhelyen végzett frissítést a felhasználó hajtja végre; a fejlesztői környezetben végzett teszt nem jelent automatikus tárhelyfrissítést.

## 1. Biztonsági mentés

Mentsd a MySQL-adatbázist (cPanel → phpMyAdmin → Export), a helyi `.env` fájlt és a `storage` könyvtárat. Az új integrációs kulcsok a `storage/app/private/connections.enc` fájlban titkosítva tárolódnak; a visszaállításukhoz **ugyanaz az APP_KEY szükséges**.

## 2. A programfájlok frissítése

Töltsd le a GitHub `main` ágának ZIP-jét. A belső projektmappa tartalmával frissítsd a `/home/digitmar/idopontfoglalo-php` könyvtár programfájljait. Az új fájlokat és a rejtett fájlokat is töltsd fel.

**Ne írd felül vagy töröld a saját `.env` fájlodat és a `storage` könyvtárad meglévő tartalmát.** A ZIP-ben csak `.env.example` és `storage/.gitignore` van. Ne hozz létre új APP_KEY-t. Ne futtass `migrate:fresh` parancsot.

SSH-ban:

```sh
cd ~/idopontfoglalo-php
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear
php artisan raero:check
```

Az új migráció az értesítési sort és a vendégfiókok tábláját adja hozzá. Az alkalmazás visszamenőleg kompatibilis beállításokat rendel a régi adatokhoz.

A dokumentumgyökér továbbra is **`/home/digitmar/idopontfoglalo-php/public`**, PHP 8.2-vel. Node.js nem szükséges. Frissítsd a böngészőt; a stílusok és a JavaScript fájlverziózott címen töltődnek.

## 3. E-mail és SMS

Beállítások → E-mailes értesítések:

- szolgáltató értesítési e-mail-címe;
- vendégértesítés és szolgáltatói értesítés külön kapcsolható;
- időpont előtti emlékeztető külön kapcsolható, óraszám és e-mail/SMS csatorna választható.

Beállítások → Szolgáltatói kapcsolatok → SMTP: kiszolgáló, port (általában 587 STARTTLS vagy 465 TLS), felhasználónév, jelszó, feladó e-mail és név. Valós küldéshez a levelezőszolgáltató által engedélyezett feladó és hitelesítés kell. A TLS-ellenőrzés nincs kikapcsolva. Küldéskor a rendszer a vendégnek foglaláskezelő és vendégfiók-hivatkozást is ad.

A cPanel **Cron-feladatok** menüjében percenkénti futtatás (`* * * * *`):

```sh
/opt/cpanel/ea-php82/root/usr/bin/php /home/digitmar/idopontfoglalo-php/artisan schedule:run >> /home/digitmar/idopontfoglalo-php/storage/logs/cron.log 2>&1
```

Ez a foglalási értesítőket és az esedékes emlékeztetőket küldi. A naptár mentése nem vár a küldőszolgáltatóra. Hiba esetén újrapróbálás történik, legfeljebb öt próbálkozással; hiányzó kapcsolatnál a feladat várakozik. A küldési sor a Beállításokban látható; az „Esedékes küldések most” gombbal cron nélkül is próbálható. Az értesítés a sikeres átadásról tud, a tényleges postaládába érkezést az SMTP-szolgáltató határozza meg.

Emlékeztető engedélyezése vagy időzítésének megváltoztatása a már meglévő, jövőbeli megerősített foglalásokat is újraütemezi. Lemondott vagy módosított foglalás régi emlékeztetője nem küldődik ki. A korábbi foglalásokra nem küld visszamenőleg újfoglalás-értesítőt.

SMS-hez Twilio Account SID, Auth Token és feladó telefonszám vagy Messaging Service SID adható meg. Telefonszám nemzetközi alakban szükséges, pl. `+36301234567`. Ha nincs e-mail-cím és a vendégértesítés, valamint az SMS-csatorna engedélyezett, az értesítés SMS-ként mehet. Az SMS-ek a Twilio díjazása szerint fizetősek.

## 4. Google- és Facebook-belépés

A vendég regisztráció nélkül is foglalhat. A `/fiokom` oldalon Google- vagy Facebook-fiókkal létrejöhet a vendégfiókja, és később beléphet. A szolgáltatói jelszavas belépés továbbra is működik.

Beállítások → Kapcsolatok → Google és Facebook belépés:

- Google OAuth Client ID és Client Secret. Visszahívási cím: `https://foglalo.digitmarketing.hu/auth/google/callback`.
- Facebook App ID és App Secret. Visszahívási cím: `https://foglalo.digitmarketing.hu/auth/facebook/callback`.
- A szolgáltatóknál az alkalmazásnak a használni kívánt fiókokra engedélyezett/publikált állapotban kell lennie; szükség lehet domainellenőrzésre és szolgáltatói felülvizsgálatra.
- **Adminbelépéshez** külön engedélyezett Google e-mail-lista vagy Facebook felhasználóazonosító-lista kell. Vendégregisztráció nem ad adminjogot.

Google-nél a szolgáltató által igazolt e-mail alapján a korábbi saját foglalások is megjelenhetnek. Facebooknál az automatikus e-mail-alapú összekapcsolás tiltott; a belépve létrehozott foglalások kapcsolódnak a fiókhoz. E-mail nélküli Facebook-fiókkal is lehet belépni, de foglaláskor elérhetőség szükséges.

Ez **vendégregisztráció**, nem új, önálló vállalkozások nyilvános létrehozása. A több szalont elkülönítve kiszolgáló SaaS-regisztráció és wildcard aldomainek továbbra is külön fejlesztés.

## 5. Beágyazás iframe nélkül

Beállítások → Beágyazás: engedélyezd a befogadó weboldal pontos eredetét, például `https://digitmarketing.hu` (a `www` külön eredet). A generált kód:

```html
<script
  charset="utf-8"
  src="https://foglalo.digitmarketing.hu/embed.js"
  defer
></script>
<raero-booking endpoint="https://foglalo.digitmarketing.hu"></raero-booking>
```

Natív webkomponens, Shadow DOM-mal; a gazdaoldal stílusait nem írja felül. Nem használ iframe-et vagy harmadik fél sütijét. Csak a nyilvános katalógust és foglalási API-t éri el. A módosítási link a foglaló saját oldalára vezet. Sikeres foglaláskor `raero:booked` eseményt küld személyes adatok nélkül; a befogadó weboldal saját, hozzájárulásnak megfelelő méréséhez felhasználható.

## 6. Mérés

GA4 (`G-…`), GTM (`GTM-…`), Google Ads (`AW-…` és konverziócímke), Meta Pixel (szám) adható meg. Teljes, tetszőleges JavaScript nem tölthető fel. A saját foglalóoldalon a mérés hozzájárulás után töltődik be; a foglalás mérés nélkül is működik. A GTM-konténer csak statisztikai és marketing-hozzájárulás együttes meglétekor indul. Foglalási eseménybe nem kerül név, telefonszám, e-mail vagy módosítási token.

A webkomponens nem tölti be automatikusan a szalon mérőkódjait a gazdaoldalon. Ott a befogadó oldal saját hozzájárulás- és méréskezelése az irányadó.

## 7. Befizetések és számlák

Befizetések → készpénz, bankkártya vagy átutalás kézi nyilvántartása, részbefizetéssel is. A statisztika a tényleges befizetésekből számol, a Stripe-visszatérítéseket levonja. A foglalás tervezett értékét külön mutatja. Több szolgáltatásnál a bevételt az árak arányában osztja fel.

Stripe vendégfizetés: titkos API-kulcs + webhook titok. A webhook URL:

```text
https://foglalo.digitmarketing.hu/api/stripe/webhook
```

Események: `checkout.session.completed`, `charge.refunded`. A foglaláskezelő oldalon megjelenik a fizetési gomb, ha a Stripe-kulcs be van állítva és maradt tartozás. A befizetést **csak érvényes aláírású, megfelelő összegű webhook** írja jóvá; a sikeres visszairányítás önmagában nem. A webhook többszöri elküldése nem hoz létre dupla befizetést. Az első próbához Stripe tesztkulcsot és teszt-webhookot használj.

Billingo: API-kulcs és számlatömb-azonosító. Számlázz.hu: Számla Agent-kulcs. Válaszd ki a számlázót és a megfelelő áfakulcsot a Beállításokban. A saját számlázási adataidat ugyanitt adhatod meg; a vendég vevőadatait a Befizetések → Számla készítése művelet kéri be.

A számla **valódi szolgáltatói művelet**, nem bemutató. Csak teljesen rendezett foglalásról készül egy számla; előlegszámla, sztornó és helyesbítő számla még nem támogatott. Számlázz.hu esetén jelenleg magyarországi vevőcím támogatott. Bizonytalan API-eredménynél a rendszer megállítja az újraküldést a kettős számlázás elkerülésére; a számlázóban kell ellenőrizni, majd a Befizetések „Egyeztetés” gombjával rögzíthető az eredmény. Csak a biztosan el nem készült számla küldhető újra. Kézi befizetés érvénytelenítése vagy Stripe-visszatérítés nem sztornózza automatikusan a számlát.

A központi, **szalonoktól beszedett előfizetéshez** külön `PLATFORM_STRIPE_SECRET_KEY`, `PLATFORM_STRIPE_PRICE_ID`, `PLATFORM_STRIPE_WEBHOOK_SECRET` szerverváltozók vannak. Ne keverd ezeket a szalon saját vendégfizetési kulcsával. A központi ár 4 000 Ft/hó HUF-előfizetés kell legyen. A platform webhookja `/api/platform-stripe/webhook`; szükséges események: `checkout.session.completed`, `customer.subscription.updated`, `customer.subscription.deleted`. A teljes SaaS-csomagkezelés, az automatikus két hónapos próbaidő, az átutalásos előfizetés és a te Billingo-fiókodból kiállított előfizetési számlák még nincsenek kész; a felület ezeket nem jelzi aktívnak.

## 8. Import / export és egyéni működés

- Vendégek és munkatársak CSV-import/export; vendég szerkesztése, munkatárs szerkesztése/törlése.
- Foglalási események CSV-import/export; szolgáltatás-pillanatképpel együtt. A `services` és `serviceSnapshot` mező JSON. Az exportált fájl visszaolvasható.
- Az import legfeljebb 2000 sort fogad egy kérésben, hibánál az egész adag visszagördül. Azonos azonosítót frissít. Az új foglalások kezelőtokenjét a szerver generálja; export nem tartalmaz kezelőtokeneket. Import nem küld értesítőt.
- Munkatárs törlése archiválás: régi foglalások megmaradnak. Jövőbeli aktív foglalásait előbb át kell helyezni vagy lemondani. Kizárólag hozzá rendelt szolgáltatása is archiválódik.
- Egyéni módban az elsődleges szakemberhez lehet foglalni; a munkatársmenü és a munkatársszűrő eltűnik. A korábbi adatok nem törlődnek.
