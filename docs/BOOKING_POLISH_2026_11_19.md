# Foglalási és előfizetési javítások – 2026. november 19.

## Nyilvános foglalóoldal

- A foglalóűrlap erősebb keretet, kontrasztot és nagyobb címet kapott.
- A Galéria általános címet használ, a tartalom végén marad.
- A plusz és törlés ikonok SVG-vel, mindkét irányban középre igazítva jelennek meg. Az oldalszámok gombon belüli igazítása is középre került; legfeljebb tíz oldalszám jelenik meg.
- A képfeltöltések rövid ajánlást mutatnak: profil/logó 1:1 (800×800), munkatárs/szolgáltatás 1:1 (1000×1000), borító 3:1 (1500×500), üzletfotó 3:2 (1500×1000). Ezek ajánlások, nem kötelező vágások. A korábbi méret- és fájlkorlátok változatlanok.
- A nyitvatartás a naptár tényleges, aktuális heti beállításaiból készül, a napi kivételeket és a munkatársi beosztásokat is figyelembe veszi. A szolgáltató a nyilvános adatlapnál kikapcsolhatja a megjelenítését. Ez a foglalható időpontokat nem módosítja. A nyitvatartás leírása kiegészítő információ marad.

## Webcímek és hibák

Az összes szerkeszthető webcímmezőben látható előtag szerepel. A `https://` alapértelmezett; teljes cím beillesztésekor az előtag nem duplázódik. A korábban megadott `http://` cím valódi előtagja megmarad. A Facebook- és Instagram-domain ellenőrzés a böngészőben és a szerveren is működik.

A hibák közvetlenül az érintett mezőnél látszanak, piros kerettel és szöveggel. Mentéskor a rendszer az első hibás mezőhöz lép; rejtett adatlapfül esetén azt megnyitja. A mentés gomb közelében összefoglaló üzenet jelenik meg. A szervertől érkező mezőszintű hibák is ezt a megjelenítést kapják.

## Előrefizetés

Kikapcsolt előrefizetésnél a szolgáltatások szerkesztésében nem jelenik meg az előrefizetési rész; a korábban elmentett értékeket megtartjuk.

Bekapcsolt előrefizetésnél a szolgáltatási szabály és az általános beállítás hiányos kapcsolat mellett is menthető. A szolgáltató figyelmeztetést és Fizetés beállítása hivatkozást kap. Használható fizetési mód nélkül a beállítás nem érvényesül a vendég foglalásánál. Az előrefizetés akkor működik, ha az engedélyezett módok között ténylegesen beállított kártyás kapcsolat vagy teljes átutalási adat van.

Sikeres foglalás után közvetlenül a befejező képernyőn jelenik meg a fizetendő összeg, az átutalási számlaszám/IBAN, kedvezményezett és közlemény, illetve az engedélyezett kártyás/SZÉP fizetés indítása. A számlaszám kiemelt és másolható. A fizetéshez nem szükséges regisztráció; az egyedi foglalási hivatkozás azonosítja a műveletet.

A szolgáltató a kötelező előrefizetés rendezetlensége mellett is megerősítheti a foglalást. Az állapotválasztó ezt előre jelzi. A szolgáltatói felülbírálás időpontját rögzítjük, de nem hoz létre befizetést és nem állítja fizetettnek a foglalást.

## Vendégfiókhoz kapcsolás

A bejelentkezett vendég **igazolt belépési e-mail-címével** egyező, még másik fiókhoz nem tartozó foglalások automatikusan összekapcsolódnak. Begépelt, nem igazolt cím vagy pusztán telefonszám-egyezés nem elegendő. Másik vendégfiók tulajdonát nem írjuk át. A kézi „Foglalás kapcsolása…” gomb eltűnt.

## Előfizetés és számla

Nagy képernyőn a Csomagod és naptáraid balra, a Megújulás és lemondás jobbra kerül. Kis képernyőn egymás alatt látszanak. A lemondógomb előtt van térköz; a próbaidő vége nem ismétlődik a fizetési résznél.

A bankkártya és átutalás közös fizetési szekciót kapott. Rendezett, érvényes időszaknál egyértelmű Kifizetve állapot látszik; ilyenkor nem ajánl fel újabb azonnali fizetést ugyanarra az időszakra.

Az új előfizetési befizetésekhez a díjcsomag és a plusz naptárak tételeit külön, befizetéskori pillanatképként mentjük. A Billingo ezeket külön sorokban kapja, a naptárakat darabszámmal és egységárral. Régi, ilyen bontást még nem tartalmazó befizetésnél nem találunk ki utólagos bontást a jelenlegi csomagból: annak számlatétele összesített marad. A már kiállított számlákat nem módosítjuk.

## Képfeltöltési hiba és felugró ablak

A szolgáltatás mentése, a képfeltöltés és a nézet frissítése külön fázis. A hibaüzenet megmondja, melyik eredményét nem sikerült ellenőrizni. Az újrapróbálás csak a hibás feltöltést vagy frissítést ismétli. Bizonytalan feltöltés után előbb ellenőrzi, megjelent-e már új kép. Kezelt hálózati hiba nem marad nyers Failed to fetch üzenet. A főadmin összesített, tartalmat és titkos adatokat nem tároló hibanaplója megkapja a hibafajtát.

A jóváhagyásra váró foglalások ablakánál a megnyitó kattintás és a közvetlenül utána érkező háttérkattintás nem zárja be a frissen nyitott ablakot. A háttérre csak akkor záródik, ha a lenyomás és kattintás is szándékosan az ablakon kívül történik. Az Escape és a Bezárás továbbra is használható. Várólistával és közben visszaérkező emlékeztető-frissítéssel is ellenőrizve.

## Telepítés

Teljes csomag, a korábbi javításokkal együtt. Megőrzendő: `.env`, APP_KEY, adatbázis, `storage`. Nincs új függőség vagy adatbázistábla. Mintaadatokat nem kell újragenerálni.

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

Utána Ctrl+F5. A fejlesztés és tesztelés nem küldött valódi fizetést, nem állított ki valódi számlát és nem módosította az éles tárhelyet.
