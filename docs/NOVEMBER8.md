# Egyszerűbb időpontfelvitel és vendégfiók – 2026. november 8.

## Frissítés

A csomag fájljainak feltöltése előtt készíts adatbázis- és fájlmentést. A saját `.env`, az `APP_KEY` és a `storage` maradjon meg. Nem szükséges új környezeti változó vagy Node-build. A korábbi privát láblécimportot nem kell újra futtatni.

```sh
cd /home/digitmar/idopontfoglalo-php && \
composer install --no-dev --prefer-dist --optimize-autoloader && \
php artisan raero:upgrade && \
php artisan raero:check
```

A migráció a vendégfiókhoz szerkeszthető nevet és titkosított számlázási adatokat, valamint külön meghívótáblát ad. Az adatokat nem törli. A szokásos percenkénti `schedule:run` cron továbbra is szükséges az értesítésekhez. Frissítés után töltsd újra a böngészőben az oldalt.

## Hol találod?

**Beállítások → Alapvető beállítások → A munkádhoz szükséges funkciók:**

- Párhuzamos foglalás és csoportos órák: alapból kikapcsolva.
- Szolgáltatási szakaszok: alapból kikapcsolva, a csoportos típussal együtt sem jelennek meg.
- Kuponok és bérletek: külön kikapcsolhatók. A már kiadott bérlet felhasználhatósága megmarad; az új ajánlatok nyilvános listáját elrejtjük. A korábban létrehozott kuponok és bérletek szerkeszthetők maradnak.

A kapcsolók az új konfigurációs lehetőségeket szabályozzák: a meglévő párhuzamos szolgáltatás, szakasz és meghirdetett csoportos alkalom megmarad, a foglalásellenőrzés figyelembe veszi. Egy meglévő szolgáltatás típusának vagy szakaszainak módosításához előbb kapcsold vissza a funkciót. Az egyéni vállalkozó a szolgáltatásnál az elsődleges szakemberét látja munkatársjelölők helyett. Az elsődleges szakember a vállalkozás beállításainál módosítható.

Ugyanezen az oldalon található a **QR-kód letöltése / nyomtatása** gomb. A QR az aktuális szolgáltató `/foglalas?provider=…` oldalára mutat. SVG és PNG tölthető le, a nyomtatási nézet külön ablakban nyílik meg. Készítése helyben történik, külső QR-szolgáltatásnak nem küldünk adatot.

## Vendégmeghívók

Alapértelmezés: **Ne küldjön meghívót**. Választható csak e-mail, csak SMS, illetve e-mail vagy SMS előnyben részesítése a másik elérhetőség hiányában. A fallback az adat hiányára vonatkozik, nem kézbesítési hiba utáni második üzenetre.

Csak a szolgáltató által újonnan felvitt vendég kap meghívót: az Új vendég és az Új időpont felvitelénél. A rendszer nem küld utólag minden régi vendégnek, importnál vagy nyilvános foglaláskor. Ha az e-mail vagy telefonszám már regisztrált fiókhoz tartozik, nem küld meghívót. Azonos szolgáltató és vendég/elérhetőség esetén nem ismétli meg a küldést.

A központi SMTP/Twilio-beállításokat a főadmin adja meg. Hiányzó kapcsolatnál a feladat várakozik a küldési sorban; a vendég mentését ez nem akadályozza. Az e-mail szövege a főadmin **Vendégfiók meghívó** HTML-sablonjában módosítható.

A link 30 napig használható. Megnyitása nem kapcsol össze semmit: belépés/regisztráció után, a saját vendégfiókban történik a kapcsolás. A véletlenszerű titkos link birtoklása igazolja a kapcsolatot; azonos e-mail alapján nem adunk hozzáférést foglalásokhoz. Egy linket egy vendégfiók használhat; megváltozott elérhetőségnél a régi link érvénytelen. A meghívó és a küldési feladat tartalma titkosítva tárolódik.

## Vendégfiók és űrlapok

A név szerkeszthető; a Google/Facebook újabb belépéskor sem írja felül a saját nevet. A kép a név mellett, kör alakban jelenik meg. Egérrel vagy billentyűzettel fölé lépve, érintőképernyőn folyamatosan látszik a Kép cseréje felirat. A meglévő képfeltöltési ellenőrzések érvényesek.

A számlázási adatok külön lenyitható részben menthetők és törölhetők. Saját fiók, illetve igazoltan kapcsolt vendég esetén a saját szolgáltató számlakészítő űrlapja olvashatja őket. Más szolgáltató és puszta e-mail-egyezés nem jogosít hozzáférésre. A korábbi számlák és foglalások pillanatképei megmaradnak.

A Nap, Időpont és Vendég neve címkék a mezők bal oldalára kerültek. A naptárból választott nap továbbra is rejtve marad a Nap módosítása gombig. A telefonmező számon kívüli jeleit eltávolítjuk; a +36/0036 előtagot 06-ra alakítjuk. A hiányos telefon és a hibás e-mail ellenőrzése a szerveren is megmaradt. A nemzetközi számok meglévő szerveroldali támogatását nem töröltük.

Minden teljes alkalmazásoldalon egyetlen egyszerű lábléc jelenik meg: Copyright 2026 digitmarketing.hu. A régi lábléc adatai megmaradnak az adatbázisban, de nem kerülnek a nyilvános API-ba vagy a felületre.

## Külső komponens

QR: qrcode-generator, Kazuhiko Arase, MIT-licenc; a forrás és licenc a `public/vendor` könyvtárban van. A vendorelt qrcode.js SHA-256 lenyomata: `79ec86f82856005b1c887905cfccfcfbec3821ca61c7fd5a952faa5f778f791c`.
