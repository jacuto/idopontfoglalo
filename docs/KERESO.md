# Vendégkereső, szolgáltatói csatlakozás és egyéni foglalás

## Javítás: egyéni vállalkozás

Egyéni működésnél az elsődleges szakember végzi az újonnan foglalható szolgáltatásokat, függetlenül a szolgáltatás korábbi munkatárskijelölésétől. A szabadidő-kereső, a következő szabad nap keresése, a nyilvános katalógus, a szalonfoglalás és az új csoportos alkalom ugyanazt a szabályt követi.

A szolgáltatásszerkesztőben nincs „Ki végzi?” lista, csak az elsődleges szakember neve. A régi munkatárskijelölések megmaradnak a katalógusban: visszakapcsolva a több munkatársas működést ismét érvényesek. Ha egy szolgáltatást egyéni módban külön elmentesz, az űrlap az elsődleges szakemberhez menti azt. A korábbi időpontok szakemberét és pillanatképét nem írjuk át. A meghirdetett csoportos alkalmak erőforrásait továbbra is ellenőrizzük; azokat nem helyezzük át másik emberhez.

## Főoldal és kereső

Belépés nélküli `/` és `/szolgaltatok`: új, vendégeknek szóló kezdő- és keresőoldal. A kereső egyszerre szűr:

- szolgáltató vagy szolgáltatás neve szerint;
- kategória szerint;
- település szerint.

A kategóriakártyák is szűrnek. A szűrők az URL-ben vannak, így a találatok megoszthatók. A szolgáltatók saját feltöltött borító- vagy szolgáltatásképeit használjuk; kép nélkül saját készítésű illusztráció jelenik meg. Nem mutatunk kitalált értékelést vagy szolgáltatót. A borítóképek dekorációk; a naptárillusztráció nem élő időpontlista.

A keresőhöz tartozó **települést, irányítószámot és kategóriákat** a szolgáltató a **Beállítások → Nyilvános adatlap → Megjelenés a szolgáltatókeresőben** résznél adhatja meg. Több kategória is választható. Üres kategórialistánál a rendszer a szakma és a szolgáltatásnevek alapján sorol be. Régi adatoknál az egyértelműen várossal kezdődő címből is próbálunk települést kiolvasni; utcacímből nem találunk ki várost. Érdemes külön kitölteni a településmezőt.

A nyilvános kereső csak aktív és foglalható katalógussal rendelkező szolgáltatókat mutat. Az új, még üres katalógusú vállalkozás nem kerül bele. Vendégadat, belépési e-mail, bankadat vagy titkos kulcs nem része a keresőnek. A nyilvános vetület egy percig gyorsítótárazható, a nyilvános adatlap mentése kiüríti a szerveroldali katalóguscache-t.

## A közelemben

A gomb megnyomása után a böngésző helyhozzáférési engedélyt kér. HTTPS vagy helyi fejlesztés szükséges. A koordinátát a böngésző egy statikus magyar településpont-adatbázissal veti össze, és kitölti a legközelebbi felismert települést. A szolgáltatókereső ezután a településre szűr.

Ez közelítő településfelismerés, nem közigazgatási határvizsgálat vagy háztól házig távolságmérés. Településhatáron ellenőrizd a javaslatot; a mező szabadon módosítható. Nagyon pontatlan GPS, hozzáférési tiltás vagy távoli külföldi hely esetén kézi településválasztást kínálunk. Nem váltunk észrevétlenül másik városra, ha nincs találat.

A helyadatot nem küldjük a szervernek vagy külső térképszolgáltatónak, és nem mentjük el. Csak a kiválasztott város kerül a keresési URL-be. A statikus településadatokat a rendszer helyben szolgálja ki, nincs geokódoló API-kulcs vagy külső hálózati függőség.

Adatforrás: [GeoNames](https://www.geonames.org/export/), Creative Commons Attribution. Tükör: [tarsolya/hu-localities](https://github.com/tarsolya/hu-localities), `hu.geonames.org.txt`. Feldolgozás: név és koordináta megtartása, ismétlések és hibás koordináták eltávolítása, JSON-kódolás; 3 238 pont, 3 142 külön név. A forrás történeti adatállomány, nem 2026-os határtérkép. Az attribúció a kereső mellett és a `public/data/hu-localities.LICENSE.txt` fájlban is szerepel. JSON SHA-256: `24dd710302bd1e8aeecf5223ef4ce6e0df0a5ca160b0227d6e06e7f39761867c`.

## Csatlakozom

A felső menü **Csatlakozom** pontja a `/szolgaltato` oldalra vezet. Itt saját illusztrációk, funkcióbemutató, díjazás és regisztrációs űrlap található.

A szolgáltató megadhatja a vállalkozás nevét, települését, fő kategóriáját, e-mail-címét és jelszavát. A jelszó legalább 12 karakter, ismétléssel. Sikeres regisztrációkor elkülönített, üres szolgáltatói munkatér jön létre, egy elsődleges szakemberrel, egy naptárkerettel és a meglévő két hónapos díjmentes próbaidővel. A rendszer beléptet a saját munkatérbe. Más vállalkozás mintavendégeit és szolgáltatásait nem másolja át. A szolgáltatásokat és a nyitvatartást a szolgáltató állítja be; ekkor válik foglalhatóvá.

Az e-mail formátumát ellenőrizzük és a már létező szolgáltatói cím újbóli regisztrálását elutasítjuk. Ez a változat nem küld e-mailes tulajdonigazolást: a cím kapcsolattartási és jelszavas belépési azonosító; önmagában nem ad vendégfiókhoz, közösségi szolgáltatói belépéshez vagy főadminhoz jogot. Az OAuth-adminjogokat továbbra is a főadmin kezeli. Nincs automatikus bankkártyaterhelés vagy hírlevél-feliratkozás. A regisztráció CSRF-védett, IP-nként korlátozott, botcsapdát és tranzakciós azonosítókiosztást használ.

A `/belepes` oldalon e-maillel és jelszóval lehet belépni, akkor is, ha a vállalkozásnak még nincs nyilvános katalógusa. A korábbi vállalkozásválasztás és a `/?provider=…`, illetve `/szolgaltato?provider=…` belépési címek is megmaradnak.

## Foglalóoldali belépés

A Ráérő-logó egyszer, a globális fejlécben jelenik meg. Az ikonos Google/Facebook/Apple belépési hivatkozás is a fejlécbe került; az ismétlődő szöveges belépési sáv megszűnt. A kiválasztott szolgáltatás és időpont továbbra is megőrződik a belépési folyamat során.

## Frissítés

Mentsd az adatbázist és a fájlokat, majd töltsd fel a csomagot. A saját `.env`, `APP_KEY` és `storage` megmaradjon. Nincs új Composer-függőség, környezeti változó vagy adatbázis-migráció. A korábbi additív migrációkat az upgrade továbbra is futtatja, ha még hiányoznak.

```sh
cd /home/digitmar/idopontfoglalo-php && \
composer install --no-dev --prefer-dist --optimize-autoloader && \
php artisan raero:upgrade && \
php artisan raero:check
```

A `public/discovery.js`, a `public/images` és a `public/data` is kerüljön fel. A böngészőben töltsd újra az oldalt (Ctrl + F5). A település és kategóriák beállítása után ellenőrizd a nyilvános találatokat.
