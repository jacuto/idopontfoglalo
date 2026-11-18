# Egységes lapozás és javítható nyilvántartások

## Lapozás és megjelenítés

- Minden lapozható listán kattintható oldalszámok, kiemelt aktuális oldal és Előző/Következő gombok. Sok oldalnál az első, utolsó és aktuális oldal környéke látszik, köztes három ponttal. Egyetlen oldalnál nincs lapozósáv. A publikus szolgáltatókereső is ugyanezt a mintát használja, szerveroldali lapozásának megtartásával.
- A foglalóoldal szolgáltatáslistája fix 10 tétel/oldal, darabszámválasztó nélkül, alacsonyabb sorokkal. A név, rövid leírás, időtartam/ár és foglalásgomb széles kijelzőn egymás mellett található; mobilon tördelődik. Az admin listák meglévő 25/50/100 vagy 10/15/20 beállítása megmarad.
- Keresés és Oldalanként felirata a saját mezője mellett van. A vendéglistából kikerült a Jellemző munkatárs oszlop, a szűrés megmaradt, az információ a vendégadatlapra került.
- A vendéglistában a műveletek egy sorban maradnak. A törlésre használt × helyén kukaikon van; az ablakbezáró × megmarad.
- A munkatárskártyák kitöltik a rendelkezésre álló szélességet, oszlopszámuk alkalmazkodik. A kukaikon feljebb került. A szolgáltatások kártyájáról is törölhető/archiválható a szolgáltatás; a foglalási előzmény és az árpillanatkép megmarad.
- A képfeltöltőnél egyetlen rövid korlátozási leírás marad; az információs gomb részletesen megmutatja a korlátokat. Az aktuális szerveroldali 5 MB és képméret-korlátok változatlanok.

## Befizetések

A manuális és a mintaadatként létrehozott befizetés összege, fizetési módja, dátuma és megjegyzése javítható. A törlés érvénytelenítést jelent: a tétel kikerül a látható listából és a bevételből, a régi adatok és a módosítási előzmények megmaradnak. Újra megjelenik a megfelelő fennmaradó tartozás. Az összeg nem haladhatja meg a foglalás értékének más befizetésekkel csökkentett részét.

A szerver nem enged bankkártyás igazolást, visszatérített összeget vagy számlázás alatt álló/számlázott befizetést kézzel átírni. A Stripe/SimplePay/Barion visszatérítése és a számlahelyesbítés továbbra is külön művelet. A régi érvénytelenítő végpont is ugyanezeket az ellenőrzéseket használja. A változatellenőrzés (409 válasz) megakadályozza, hogy két párhuzamos szerkesztés felülírja egymást.

## Kuponok és bérletek

A Kuponok és Bérletek fülek külön tartalmat mutatnak. A Bérletek fülön külön vannak a bérlettípusok és a Kiadott bérletek.

A Kiadott bérletek listája kereshető, rendezhető, lapozható; alapból az aktívakat mutatja. Vendégnévvel, bérlettel, szabad/foglalt/felhasznált alkalmakkal, lejárattal és állapottal. A vendég neve az adatlapot nyitja. A szabad alkalmak és lejárat javíthatók, naplózottan. Új fix lejárat megadása első használatos bérletnél is konkrét lejáratra vált. A meglévő jövőbeli foglalások nem kerülhetnek az új lejáraton kívülre.

A kiadott bérlet törlése visszavonás. Ha jövőbeli, nem lemondott foglalás kapcsolódik hozzá, előbb ezeket kell rendezni; nincs automatikus foglalástörlés, újraárazás vagy bérletalkalom-módosítás. A felhasznált alkalmak és a korábbi foglalásárak megmaradnak. Visszavont bérlet új foglaláshoz nem használható, a választókban sem jelenik meg. A történeti kedvezmény és felhasználás statisztikája visszavonás után is megmarad.

## Statisztikai ablakok

A kupon vagy bérlettípus nevére kattintva statisztika nyílik. A listákon összegző sor is szerepel.

- Kupon: felhasználások, különböző vendégek, eredeti szolgáltatásérték, rögzített kedvezmény Ft-ban/%-ban, kedvezményes foglalási érték és a kapcsolódó igazolt befizetések. Lemondott/elutasított foglalás nem számít felhasználásnak.
- Bérlet: kiadások és vendégek, kiadási érték, foglalt/felhasznált/szabad alkalmak, tényleges felhasználás alapján adott kedvezmény. A kedvezmény a foglalás eredeti szolgáltatásértékének és a kiadáskori egyalkalmas bérletértéknek a különbsége; nem a később átírt szolgáltatásárból számoljuk.
- A bérlet kiadási értéke **nem bevétel**: a hozzárendelés nem bizonyít befizetést. A korábbi, típusazonosító nélkül kiadott bérleteket csak egyértelmű név/ár egyezésnél soroljuk típushoz. Minden kiadás látható a részletes listában; nem egyértelmű régi kiadásokból nem találunk ki statisztikai hozzárendelést.

## Naptár, diagram és üzenetek

- A levágott naptári foglalás teljes szövege rámutatáskor az alatta lévő tartalom fölé nyílik. Amíg a kurzor az eredeti foglaláson vagy a kinyitott részen van, nyitva marad. A naptár sorait nem tolja el. A rövid, teljesen látható tartalomnál nincs fölösleges lenyílás.
- A diagramon a pontos dátum/összeg a kurzor mellett azonnal látható, a böngésző késleltetett alapértelmezett súgója helyett.
- Az üzenetek időrendben, a legfrissebbel alul jelennek meg. Saját üzenet jobbra, beérkező balra, eltérő színnel. Megnyitáskor az utolsó oldal és a legfrissebb üzenet látszik. A listában „Te:” vagy a küldő neve mutatja az előnézet irányát. A fejlécben továbbra is a beszélgetőpartner neve/adatlapja, az üzenetnél idő és olvasottság látszik.

## Telepítés

Nincs új migráció vagy külső függőség. A teljes csomagot frissítsd; új PHP-szolgáltatások és két új publikus fájl is van. A `.env`, az APP_KEY, a `storage` és az adatbázis megmarad. Nem kell újra betölteni a mintaadatokat.

```bash
php artisan optimize:clear
php artisan raero:check
```

Utána Ctrl+F5. Az éles rendszeren nem futott automatikus telepítés vagy pénzügyi művelet. A helyi ellenőrzés külön tesztadatbázison és mintafiókokkal történik.
