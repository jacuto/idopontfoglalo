# Fejlesztési átadás – 2026. november 1.

> November 7-i bővítés: [naptár, képek, exportok és központi kapcsolatok](NOVEMBER7.md). A közös SMTP-, OAuth- és SMS-kezelésre az új útmutató érvényes.

> Újabb működési szabályok: [FINOMITASOK.md](FINOMITASOK.md). Ez tartalmazza a később kért felületet, vendégadatvédelmet, méréskikapcsolást és a 21 napos előfizetési határidőt; ezekben az alábbi korábbi leltárt felülírja.

## Kiindulási állapot és vizsgálat

Tároló: `jacuto/idopontfoglalo`, `main`. Kiinduló commit: `c59c6060497be1e8bd92254ba6629e8d6e973afd`.

A meglévő kód Laravel 12, PHP 8.2, MySQL/MariaDB alkalmazás volt. Egyetlen `salon_state` rekordban tárolta a szalon szolgáltatásait, munkatársait, vendégeit, foglalásait, befizetéseit és beállításait. Volt napi/heti/havi naptár, perces nyitvatartás, egyéni/csapatos mód, vendég-/munkatárs-/foglalás-CSV, szolgáltatási ár- és időtartam-pillanatkép, festési hatóidő, nyilvános foglalás, titkos foglaláskezelő link, email-/SMS-sor, vendég-OAuth, Stripe, Billingo és Számlázz.hu. A korábbi tesztek ezek jelentős részét már ellenőrizték.

Nem készült külön alkalmazás vagy látványdemó. Ezek a vezérlők, szolgáltatások, felületek és tesztek bővültek. Az eredeti adatállapot továbbra is az eredeti rekordban van. A fejlesztés nem kapcsolódott az éles tárhelyhez vagy adatbázishoz.

## A kért funkciók állapota

| Terület | Elkészült működés | Határ / további teendő |
|---|---|---|
| 1. Egyszerű felület | Naptár és Új időpont, függő foglalások jelzése, üzenetjelzés, magyar súgók, visszajelzések, rácsos listák, középre igazított avatarok. Mobilon olvasható nevű, vízszintesen görgethető menü. | Valós kezdő felhasználókkal külön használhatósági felmérés nem történt. |
| 2. Elkülönítés | Külön főadmin-, szolgáltatói és vendégmunkamenet. Vállalkozásonként külön zárolt adatállapot és titkosított kapcsolat. Közvetlen API és keresés is szűrt. Importált vendég önálló szolgáltatói rekord, fiók nélkül. Egy vendég több szolgáltatóhoz kapcsolódhat. | Szolgáltatókat jelenleg a főadmin vesz fel; nyilvános önkiszolgáló szolgáltatói regisztráció nincs. |
| 3. Vendégadatlap | Név, telefon, email, profilkép/alapavatar, szerkesztés; összes/lemondott/megjelent/nem megjelent szám; közelgő/korábbi időpontok; belső szolgáltatói értékelések és bérletek. | Korábbi foglalásnál nem találunk ki utólag megjelenést. Bizonytalan régi rekordkapcsolatot nem vonunk össze automatikusan. |
| 4. Vendégprofilkép | Saját feltöltés és csere; JPEG/PNG/WebP, legfeljebb 2 MB, legfeljebb 4096×4096; privát tárolás és jogosultságvizsgált kiadás. | A szolgáltató üzletképei szerkeszthető HTTPS-képcímek; külön szolgáltatói médiatár-feltöltő nincs. |
| 5. Kölcsönös értékelés | Kézzel igazolt, megerősített látogatás után mindkét irányban, 1–5 pont és szöveg, látogatásonként/irányonként egy. Vendégértékelés nyilvánosan, átlaggal és darabszámmal. A vendégről írt értékelés belső. | Import, függő/lemondott/elutasított vagy nem megjelent alkalom nem jogosít. |
| 6. Vendégkereső | Három karaktertől, név és normalizált telefonszám, maximum 20 saját találat; azonos neveknél telefon/email; le/fel/Enter/Esc és kattintás; stabil vendégazonosítóval nincs kiválasztásból eredő másolat. | Telefonszám ország-/körzetszám-átírása nincs; szóközök, kötőjelek és egyéb írásjelek nem számítanak. |
| 7. Foglalási események | A szolgáltatások neve részleteket megnyitó hivatkozás. Múltbeli függő foglalás elfogadása érthető, teendőt adó hibával tér vissza; áthelyezhető jövőbe vagy elutasítható. Megjelenés külön mező. | Függő foglalást nem fogadunk el visszamenőleg úgy, mintha időben visszaigazolták volna. |
| 8. Nyilvános adatlap | Bemutatkozás, leírás, logó/borító/fotók, cím, megközelítés, térkép, telefon/email/web/Facebook/Instagram, nyitvatartás, szolgáltatások, munkatársak, értékelések, ajánlatok, bérletek, feltételek; beépített foglalás. Üres részek elrejtve. | Képek URL-ről; automatikus geokódolás/térképszolgáltatás nincs, térképes link van. |
| 9. Kuponok | Kód, név, érvényesség, szolgáltatások, százalék/forint, aktív állapot, teljes és vendégenkénti korlát, nyilvános/kódos ajánlat, első alkalom és ingyenes első alkalom; szerveroldali árazás és lefagyasztott ár. | Nyilvános kuponhoz bejelentkezés kell a korlátokhoz. A kupon érvényességét a foglalás rögzítésekor ellenőrizzük. |
| 10. Bérletek | Létrehozás/szerkesztés, név/leírás/ár/alkalom/szolgáltatások/napok/feltételek/aktivitás, nyilvános lista, kézi kiadás vendégnek, hátralévő alkalmak/lejárat, foglalási felhasználás, zárolt egyszeri levonás és egyszeri visszaadás. Belépett vendég is választhat saját bérletet. | Online bérletvásárlási Checkout és a bérletértékesítés önálló pénzügyi bizonylatfolyamata nincs; vásárláshoz kapcsolatfelvétel és kézi kiadás van. A kiadás nem igazol befizetést. |
| 11. Belső levelezés | Saját kapcsolt vendég és szolgáltató beszélgetése, olvasatlan jelzés, olvasottra állítás, tartós mentés és külön emailértesítési sor. Főadmin külön közvetlen üzenetet küldhet szolgáltatónak/vendégnek, akik válaszolhatnak. | Importált, fiók nélküli vendég nem kap belső postafiókot automatikusan. |
| 12. Hírlevél | Külön főadminfunkció, külön SMTP-kapcsolat, szolgáltatói/vendégcélcsoport, tárgy/tartalom/előnézet/tesztküldés, piszkozat/időzítés, háttérsor, küldési állapotok, kifejezett hozzájárulás, aláírt leiratkozás és küldés előtti kizárás. | Szöveges levelek. SMTP-elfogadás követhető, tényleges postaládába érkezés/bounce/megnyitás nem; szolgáltatóspecifikus ilyen webhook nincs. |
| 13. Főadmin | Külön `/foadmin`, külön helyben beállított jelszó; szolgáltatófelvétel/lista, naptárkeret, próbaidő/előfizetés, befizetések, számlák, saját kulcsok, bankadatok, közvetlen levelezés és hírlevél. | Teljes felhasználó-életciklus (pl. fióktörlés, jelszó-visszaállítás emailben) nincs kiépítve. A szolgáltató naptárkerete főadmin által módosítható. |
| 14. Előfizetés | 4 000 Ft fizetendő havi alapár, két naptári hónap kártyamentes próba, saját Stripe Checkout és aláírt webhookok, átutalási közlemény és idempotens főadmin-jóváírás, állapot/időszak/befizetés/számlaszám/PDF. Saját Billingóból AAM számla, ismétlés elleni védelemmel és bizonytalan eredmény egyeztetésével. | A többnaptáras csomag külön automatikus Stripe-árazása nincs; a keret főadmin által adható. Az alapár szerveroldalon ellenőrzött. |
| 15. Üzemeltetői adatok | Név/cím/adószám/nyilvántartási szám és bank/IBAN/SWIFT külön főadmin által megadható és szerkeszthető. A személyes és banki értékek a privát alkalmazásadatbázisba kerülnek, nem a nyilvános forrásba. | Az átadott beszélgetésben megadott értékeket első használatkor a főadminban kell rögzíteni. A Billingo kibocsátói adatait a külső fiókban is helyesen kell beállítani. |
| 16. Két pénzügyi folyamat | A vendég befizetései/számlái és az üzemeltető előfizetési naplója/számlái/titkos kulcsai külön vannak. | A korábbi vendégszámlázás továbbra is a teljesen rendezett foglalást számlázza; előlegszámla nincs. |
| 17. Régi funkciók | A korábbi naptárnézetek, szerkesztések, CSV-k, munkatársarchiválás, perces nyitvatartás, egyéni/csapatos mód, több szolgáltatás és hatóidő, kézi/automata visszaigazolás, vendégmódosítás/lemondás, értesítések/emlékeztetők, bevételek/időszak/kördiagram, OAuth, számlázási adatok, mérési hozzájárulás és iframe nélküli alapmodul megmaradtak. | Automatikus aldomain-/DNS-/SSL-kezelés nincs. A beágyazott komponensben a teljes oldal új kupon-/bérletválasztója még nincs. |
| 18. Kapcsolatok | Külön szolgáltatói és főadmin konfigurációs űrlap, titkosított tárolás, maszkolt titkok, hiányzó kapcsolat jelzése, dokumentált callback/webhook/cron. | Valós külső fiókok, domainengedélyek, hitelesített feladó, kulcsok és szolgáltatói jóváhagyások még szükségesek. |
| 19. Átadás | Meglévő kódban megvalósított funkciók, régi és új backendtesztek, valódi MariaDB többfolyamatos versenytesztek, Chromiumos felületteszt, adatmegőrző útmutató. | A konkrét éles adatbázis másolatán történő ügyféloldali próba és az éles külső integrációk kipróbálása még hátravan. |

## Fontos működési szabályok

### Vendégfiók és saját vendégnyilvántartás

A szolgáltató vendégnyilvántartása és a regisztrált vendégfiók két külön fogalom. Az import nem hoz létre fiókot. A telefon/email alapján keresés csak a szolgáltató saját rekordjait adja vissza; nem ad fiókhozzáférést.

Megszűnt a régi emailalapú automatikus foglalás-hozzárendelés. A vendég csak a bejelentkezve rögzített vagy a **titkos foglaláskezelő link birtokában, kifejezetten kapcsolt** foglalást kapja meg. Már másik fiókhoz tartozó linket nem lehet átkapcsolni. A szolgáltató nem injektálhat tetszőleges `guestId`-t a vendégmentési API-ba. A már igazoltan kapcsolt vendég kiválasztásakor a szolgáltató új foglalása is a meglévő kapcsolathoz kerülhet.

A régi foglalások vendégrekordját csak egyetlen, név/normalizált telefon/email szerint egyértelmű helyi egyezésnél kapcsoljuk össze a statisztikához; ebből nem lesz vendégfiók-hozzáférés. Az eltérő/hiányos régi rekordok kézi egyeztetést igényelhetnek.

### Látogatás és értékelés

A foglalási állapot (`pending`, `confirmed`, `cancelled`, `rejected`) és a megjelenés (`attended`, `no_show`, `unmarked`) külön adat. Kizárólag megerősített, már befejeződött időpontnál jelölhető megjelenés. Az idő múlásától nem lesz igazolt látogatás. A lezárt látogatás állapotát és időpontját a normál felületen nem írjuk át; téves lezárást az üzemeltetővel, a kapcsolódó értékelés-/bérletelőzmények egyeztetésével kell javítani.

### Bérlet és kupon

A kupon/bérlet jogosultságát, árat és keretet a szerver ellenőrzi a foglalási adatállapot zárolása alatt. A böngésző által beküldött árat nem fogadjuk el. Egy bérletalkalom foglaláskor **lefoglalt**, megjelenéskor vagy meg nem jelenéskor **felhasznált**, lemondás/elutasítás után egyszer **visszaadott**. A foglalás időpontjának is a bérlet lejáratán belül kell lennie. A kupon és bérlet nem vonható össze. Már lemondott bérletes foglalást nem aktiválunk újra; új foglalást kell felvenni.

A régi foglalás ára nem követi a kupon későbbi módosítását, a kiadott bérlet pedig saját feltétel-pillanatképet tartalmaz. Egy bérlethez rendelt szolgáltatáscsomag egy foglaláson egy alkalomnak számít, ha minden kiválasztott szolgáltatás megengedett.

### Megőrzés és CSV

A frissítés nem törli az eredeti vendég-, foglalás-, katalógus-, befizetés- vagy beállításadatokat. A régi APP_KEY, `.env`, `storage` és feltöltések megőrzése a tárhelyes frissítés kötelező része. A CSV import/export a korábbi mezőivel és szolgáltatáspillanatképeivel megmaradt. **A CSV nem teljes mentés**: nem állít helyre fiókjogosultságot, hiteles megjelenést, kupon-/bérletnaplót, üzenetet vagy előfizetési pénzügyi előzményt. Teljes visszaállításhoz a teljes SQL- és fájlmentés együtt kell.

## Ellenőrzések és külső függőségek

A végleges teszteredmények a [TESZTELES.md](TESZTELES.md) fájlban vannak. A külső szolgáltatások HTTP-válaszai a tesztekben szimuláltak; nem állítunk éles kézbesítést, fizetést vagy számlázást. A MariaDB-adatbázis és a Chromium ellenőrzés valódi, külön tesztkörnyezeten futott.

Éles használathoz szükséges: saját központi Stripe- és Billingo-fiók/kulcsok, központi SMTP/feladóhitelesítés, szolgáltatónkénti saját kapcsolatok, OAuth-alkalmazások/callbackek, szükség esetén BulkGate, valamint a tárhelyes frissítés és cron beállítása. Részletek: [KAPCSOLATOK.md](KAPCSOLATOK.md).

## Befejezetlen részek összefoglalva

- Automatikus aldomain-, DNS-, egyéni domain- és SSL-provizionálás.
- Online bérletvásárlási Checkout és önálló bérletértékesítési számlafolyamat.
- Az új kupon-/bérletválasztó átvezetése a beágyazott webkomponensbe.
- Szolgáltatói médiatár fájlfeltöltéssel; jelenleg üzletképek HTTPS-képcímről.
- Automatikus többletnaptár-díjszámítás; jelenleg főadmin által kezelhető jogosultsági keret van.
- SMTP-n túlmutató kézbesítés-/bounce-/megnyitáskövetés és HTML-sablonszerkesztő.
- Teljes önkiszolgáló szolgáltatói fiókéletciklus, emailes jelszó-visszaállítás.
- Éles tárhelyfrissítés, valódi ügyféladatbázis-másolaton próba és külső szolgáltatói végpontokkal végzett átvételi teszt.

Ezeket nem mutatja késznek vagy sikeresen végrehajtottnak a felület. Az éles weboldal nem frissült a kód átadásától.
