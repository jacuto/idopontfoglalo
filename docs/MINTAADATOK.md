# Mintaadatok a jelenlegi telepítésben

A `tools/test-data.php` külön, csak SSH/CLI alól futtatható adatfeltöltő. Nem módosít meglévő alkalmazáskódot, beállítást vagy szolgáltatói fiókot. Nem kell hozzá új migráció. A telepített adatbázis legyen naprakész.

Előtte készíts teljes adatbázismentést a tárhely kezelőfelületén. A szkript két új **TESZT** szolgáltatói fiókot és 120, mindkét szalonhoz kapcsolódó, jelszóval beléphető mesterséges vendégfiókot hoz létre. Ezek a szolgáltatók a szolgáltatókeresőben is megjelenhetnek: nevük és bemutatkozásuk egyértelműen tesztet jelez. Meglévő szolgáltatóba nem tölt adatokat.

Mindkét szalonban 120 vendég, 21 munkatárs, 31 szolgáltatás, 360 múltbeli/jövőbeli és eltérő állapotú foglalás, több mint 100 mesterséges befizetés, 180 üzenet (egy beszélgetésben 65), három kupon és három bérlet lesz. Lehet lapozást, keresést, rendezést, módosítást, törlést, vendégoldali foglalásokat és belső üzeneteket próbálni. A munkatársaknak nincs külön belépésük; a szolgáltató kezeli őket.

A befizetések kizárólag az új TESZT szalonok saját statisztikájában szerepelnek. Nem készül valós számla, üzemeltetői bevétel, SMS-egyenleg vagy Stripe-tranzakció. Az előfizetési számlák/banki webhookok valódi fizetés nélküli end-to-end tesztelésére ez a csomag nem alkalmas.

## Feltöltés

Csak a `tools/test-data.php` fájlt kell feltölteni a telepítés ugyanilyen nevű alkönyvtárába (nem a `public` alá). SSH-n:

```bash
cd /home/digitmar/idopontfoglalo-php
php tools/test-data.php create
```

A végén megjelennek a szolgáltatói belépési linkek, a véletlenszerű szolgáltatói jelszó, a vendégbelépési címek és a külön vendégjelszó. Másold ki őket. A `.invalid` végű e-mail-címek szándékosan nem létező címek, de a szkript által létrehozott tesztfiókba ezekkel lehet bejelentkezni; nem kell e-mailes aktiválás. Telefon nincs megadva, nehogy valódi címzettnek küldjön SMS-t a rendszer. A telefonszámos keresés kipróbálásához csak saját/ellenőrzött tesztszámot használj, értesítés nélkül.

Ugyanezek az adatok később:

```bash
php tools/test-data.php status
```

A jelszavakat csak a saját tárhelyeden generálja a szkript; nem szerepelnek a forráskódban. A privát `storage/app/manual-test-data/manifest.json` fájl őrzi őket és a létrehozott rekordok azonosítóit. Ezt ne tedd nyilvánossá és ne töröld a tesztcsomag eltávolítása előtt. Az ismételt `create` nem duplikál adatokat. Másik vendég/szolgáltató próbájához használj kijelentkezést vagy inkognitóablakot.

## Küldés és pénzügyek

Az adatfeltöltés nem használ értesítési, számlázási vagy fizetési API-kat és nem hoz létre küldési sort. Az új szalonok értesítései, meghívói, véleménykérői, automatikus számlázása és előrefizetése kikapcsoltak; nincs feltöltött SMS-egyenleg, naptárkapcsolat vagy fizetési kulcs. A központi és meglévő szolgáltatói beállítások változatlanok.

**Ez nem rendszerszintű sandbox:** a tesztfiókokban se kapcsold be a valódi küldést, ne adj meg valódi ügyféladatot, és ne csatlakoztass élő fizetési/számlázási integrációt. A kézi küldés, jelszó-visszaállítás vagy valódi fizetés kezdeményezése a meglévő rendszer normál működését használná. A tesztszalonok ingyenes Pro hozzáférést kapnak, a meglévő előfizetéseket nem módosítjuk.

## Eltávolítás és újrakezdés

```bash
php tools/test-data.php remove --delete-test-accounts
```

Ez a két **teljes TESZT fiókot** törli, a bennük tesztelés közben létrehozott/módosított adatokkal együtt. A jelenlegi valódi szalonokat és vendégeiket nem érinti. A szkript ellenőrzi a saját tesztazonosítóját; leáll, ha a tesztvendég időközben másik szalonhoz is kapcsolódott vagy valódi üzemeltetői előfizetési befizetés történt. A tesztjegyzéket privát archívumba helyezi. Ezután új `create` futtatással új tesztcsomag és új jelszavak készíthetők.

A tesztelés közben külön feltöltött médiák és létrehozott külső szolgáltatói kapcsolatok takarítása nem része ennek az adatfeltöltőnek; teszteléshez ilyet ne hozz létre. A feltöltött képek törlését a normál felületen próbáld ki és ott is takarítsd el.
