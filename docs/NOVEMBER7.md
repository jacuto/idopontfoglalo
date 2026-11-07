# November 7-i fejlesztések

Az éles adatbázis és tárhely változatlan; az új kódot az adatmegőrző [frissítési útmutatóval](../FRISSITES.md) kell telepíteni. Meglévő `.env`, `APP_KEY`, `storage` és feltöltések maradjanak meg. Új függőség nincs a november 5-i verzióhoz képest. PHP Phar, DOM/XML, mbstring és fileinfo szükséges az Excelhez, HTML-hez és képekhez.

## Használat

- **Nem jelent meg:** megerősített foglalás kezdetétől jelölhető. A díj opcionális, alapértéke a beállításokban adható meg. Nem készül automatikus befizetés vagy bankkártyaterhelés. A díj a Befizetésekben rögzíthető, legfeljebb a fennmaradó összegig. A korábbi szolgáltatásár megmarad. A lezárt megjelenés és díj végleges, a bérletlevonás nem ismétlődik.
- **Befizetés:** vendég / telefon / szolgáltatás szerinti kereső; időszakválasztó; legfeljebb 20 fizetésre váró találat, dátummal és tartozással. Egy foglalást kifejezetten ki kell választani. A foglalás részleteiből előre kiválasztva is megnyitható.
- **Letöltés:** Statisztika → Excel; Naptár → Naptár letöltése → időszak és Excel vagy ICS. Az XLSX valódi munkafüzet, nem átnevezett CSV. A szövegek szövegcellák, képleteket nem futtatnak. Az ICS egyszeri export, nem élő feliratkozás.
- **Kezdési gyakoriság:** Alapvető beállítások → 5 / 10 / 15 / 20 / 30 perc vagy egész óra. Az egész órás kezdés a következő egész órához igazodik; más lépésköz a napi nyitáshoz. A vendég először órát választ, csak több elérhető kezdésnél kérünk percet. Az óránkénti kezdésnél nincs perclista. A nyilvános API is ellenőrzi a lépésközt.
- **Elérhetőségek:** email ellenőrzése és kisbetűs mentése; új vagy módosított magyar telefon mentése `06301234567`, megjelenítése `06 30 123 4567`, hívás és SMS esetén `+36301234567`. Külföldi szám országkóddal is megadható. Ez formai ellenőrzés, nem SMS-es vagy emailes tulajdonosi hitelesítés. Régi, hibás telefonszámok nem törlődnek; új vagy módosított számnál kötelező az érvényes forma.
- **Helytakarékosság:** bal alsó menügombbal az oldalsáv kihúzható; az összesítők és naptárvezérlők külön összecsukhatók. Mindkettőt a böngésző megjegyzi; alapból látszanak.
- **Képek:** vendég Saját fiókom; szolgáltató Nyilvános adatlap; munkatárs Profilkép; szolgáltatás Kép feltöltése. Valódi JPG/PNG/WebP, méretkorláttal. A profilképek kör alakúak, kép nélkül monogram látszik. A Google/Facebook-kép átvehető a szolgáltatóhoz/munkatárshoz is; az Apple nem ad képet. Szolgáltatásnál kép nélkül illusztrált kártya látszik.
- **Formázás:** szolgáltatás, munkatárs és vállalkozás leírása, foglalási feltételek szerkeszthetők félkövér/dőlt/aláhúzott szöveggel, listával és hivatkozással. A szerver engedélyezett HTML-elemekre szűr, eltávolítja a szkripteket, eseménykezelőket, beágyazásokat. A SQL-műveletek paraméterezett Laravel-lekérdezésekkel készülnek.

## Google Naptár és több naptár

A Google Cloud Console-ban a közös OAuth-projekthez engedélyezd a **Google Calendar API**-t. A webes OAuth-kliens engedélyezett visszahívási címei közé add hozzá pontosan:

```text
https://foglalo.digitmarketing.hu/auth/google-calendar/callback
```

A belépéshez használt `/auth/google/callback` is maradjon. A Calendar jogosultság külön felhasználói hozzájárulás: egy egyszerű Google-belépés önmagában nem kapcsolja a naptárt. Scope: `openid email profile`, `calendar.events`, `calendar.calendarlist.readonly`. Külső alkalmazásnál a Google felülvizsgálata szükséges lehet; teszt módban a használókat tesztfelhasználóként kell felvenni. A Google teszt módú refresh tokenje lejárhat; ilyenkor újracsatlakozás szükséges.

Szolgáltató: Beállítások → Integrációk → munkatársankénti Google Naptár-kártya. Vendég: Saját fiókom → Google Naptár. Mindegyik külön kikapcsolható és bontható. Alapértelmezés a Google-fiók fő naptára; másik szerkeszthető naptár azonosítója is megadható. A régi Google-naptárban maradt eseményeket a kapcsolat bontása / célváltás nem törli.

A Ráérő létrehozás/módosítás/lemondás eseményeit sorba állítjuk. A háttérfeladat felküldi/frissíti/törli őket. A külső, foglalt Google-események a következő 180 napra blokkolják az új foglalásokat. Ezek címét/leírását nem tároljuk. Az olvasott foglaltság időszakosan frissül, ezért a két rendszer közötti késést figyelembe kell venni. Ez nem azonos a Google-ben átírt Ráérő-időpont visszaimportálásával: a Ráérő-foglalást a Ráérőben kell módosítani. Több szolgáltatónál ugyanahhoz a vendégfiókhoz tartozó saját foglalások a vendég naptárába kerülhetnek, más vendégé nem.

A szerver percenkénti meglévő cronja szükséges:

```cron
* * * * * cd /home/digitmar/idopontfoglalo-php && php artisan schedule:run >> /dev/null 2>&1
```

Kézi ellenőrzés: `php artisan raero:calendar-sync --limit=20`. Valós Google-fiókkal a fejlesztői tesztekben nem történt összekötés; a tesztek a Google HTTP-válaszait helyettesítik. Sikertelen szinkron nem jelenik meg sikeresként. A token titkosítva kerül tárolásra, API-válaszba nem kerül.

Több munkatársnál egy munkatárs egy naptár. A Naptárak választóval több is megjeleníthető, napi nézetben egymás melletti oszlopokkal. Az üres oszlopba kattintva az adott munkatárs kerül kiválasztásra. A meglévő előfizetési naptárlimit továbbra is érvényes.

## Központi kapcsolatok és automatikus levelek

A Google / Facebook / Apple OAuth, Twilio és SMTP mezők kizárólag a főadmin `/foadmin` kapcsolatbeállításaiban érhetők el. A szolgáltatói API is tiltja az írásukat. A korábbi 1-es szolgáltató közös kulcsai átmenetileg háttérként használhatók; a főadmin új mentése az elsődleges. Más szolgáltató régi, saját OAuth-/SMTP-beállítása nem írhatja felül a közöset. A szolgáltatói adminbelépéshez engedélyezett fiókokat a főadmin külön, szolgáltatónként állítja.

A közös SMTP egyszerűbb és jobban ellenőrizhető kézbesítést ad. A foglalási email feladója a rendszeré; a válaszcím a szolgáltató értesítési emailcíme. A szolgáltatók Stripe-/számlázói kapcsolatai továbbra is sajátok. A `.env.example` üres kulcshelyeket tartalmaz; valódi titkokat csak a privát `.env`-be vagy főadminba írj.

A főadmin Automatikus levelek (HTML) gombjával az új/módosított/lemondott foglalás, emlékeztető, meg nem jelenés és belső üzenetek sablonja, tárgya és engedélyezése állítható; előnézettel. Változók: `{{business}}`, `{{name}}`, `{{details}}`, `{{manage_url}}`, `{{account_url}}`, `{{provider_url}}`. Ezek adatként helyettesítődnek; Blade/PHP-kód nem fut. A már elküldött levelek nem változnak meg.

## Privát bankadatok és minták

A mellékelt külön `uzemelteto-privat.json` fájlt a webgyökéren kívül helyezd el, például `/home/digitmar/uzemelteto-privat.json`, majd:

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan raero:operator-import /home/digitmar/uzemelteto-privat.json
```

A fájl a megadott üzemeltetői adatokat tartalmazza; nem része a nyilvános GitHub-csomagnak. A főadminban módosítható. Az átutalásnál csak kedvezményezett, bank, számlaszám, IBAN, BIC és közlemény látszik; a lakcím és adóazonosítók nem kerülnek oda.

Mesterséges profil- és foglalási mintaadat a már meglévő `raero:sample-data` paranccsal kizárólag elkülönített local/testing adatbázisba tölthető, éles adatokat nem írunk át és nem találunk ki helyettük nyilvános címet.

## Terhelés és ellenőrzés

A foglalási ellenőrzés és mentés egy szolgáltatói sorzáron belül történik; külön szolgáltatók nem ugyanazt a sort zárolják. A naptár-API hívás nincs a foglalás mentési tranzakciójában. Indexelt, korlátozott méretű háttérfeladatok és determinisztikus Google-eseményazonosítók akadályozzák a dupla felküldést. Az idősávlista csak az adott nappal átfedő foglalásokat vizsgálja, a Google-foglaltságot egyszer olvassa be. A rendszer továbbra is szolgáltatónként JSON-állapotot tárol: hosszú, nagy adatmennyiségű történetnél relációs táblákra bontás indokolt. A tesztek nem helyettesítik a saját tárhelyen végzett kapacitásmérést.
