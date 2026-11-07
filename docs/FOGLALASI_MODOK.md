# Gyors időpontfelvitel, párhuzamos szolgáltatások és csoportos órák

Az új kód feltöltése után a meglévő `.env`, `APP_KEY` és `storage` megtartásával futtasd a szokásos `composer install`, `php artisan optimize:clear`, `php artisan raero:upgrade` és `php artisan raero:check` parancsokat. Az új migráció csak a vendégfiókok naptárajánlási jelzését adja hozzá, adatot nem töröl. Éles frissítéshez továbbra is a [teljes útmutató](../FRISSITES.md) az irányadó.

## Szalon saját foglalásai

Beállítások → Alapvető beállítások → **Gyors szalonfoglalás**:

- Kiválasztható az alapértelmezett szolgáltatás. A saját Új időpont ablakban ez előre ki lesz választva, és átállítható. Munkatársos oszlopból nyitva csak akkor választjuk ki, ha az adott munkatárs végzi. A vendégoldalon nincs automatikus szolgáltatásválasztás. Csoportos óra nem lehet általános alapértelmezett, mert ahhoz egy meghirdetett alkalmat kell választani.
- Név nélkül a szalon külön megerősítést kap. Az időpont neve **Név nélküli vendég** lesz. A kérdésben a **Később ne kérdezze meg** megjegyezhető a vállalkozásnak; ugyanitt a beállításokban a pipa kivételével visszakapcsolható.
- A név nélküli, elérhetőség nélküli időpont nem gyárt új, használhatatlan vendégadatlapot. Később a meglévő időpont helyett konkrét vendéggel is felvehető alkalom; a név nélküli foglalás saját azonosítóval rendelkezik. A vendég saját foglalásánál a név és legalább egy elérhetőség továbbra is kötelező.

A naptár összecsukó gombja az Új időpont mellett kis, visszafogott nyíl. Nem foglal külön sort; az Új időpont kiemelt gomb maradt.

## Melyik szolgáltatástípus mire való?

A Szolgáltatások szerkesztésénél három típus választható:

| Típus | Működés | Példa |
|---|---|---|
| Egyéni | Egyszerre egy aktív vendég; a hatóidő szabad lehet | Hajvágás, festés munka–hatóidő–munka szakaszokkal |
| Párhuzamos | Ugyanaz a szolgáltatás egy munkatársnál legfeljebb N vendégnek; eltérő kezdések is lehetnek | Két- vagy háromfős személyi edzés |
| Csoportos | Előre meghirdetett, közös kezdésű órára külön férőhelyek | 10:15-ös csoportos edzés, legfeljebb 8 résztvevő |

A létszámhatár nem engedély más, eltérő szolgáltatás egyidejű aktív munkájára. Különböző párhuzamos szolgáltatások közös erőforráskerete nincs összevonva: ez szándékosan konzervatív ütközésvédelem. A korábban rögzített foglalások pillanatképei megmaradnak; egy utólagos kapacitásnövelés nem írja át a régi foglalások feltételeit.

Festésnél például `30,35,30` perc esetén az első 30 és az utolsó 30 perc foglalja a szakembert. A középső 35 percben másik munka beférhet. Ha egy másik vendég már ebben az időben hajvágáson van, nem fogadható mellé még egy ugyanakkor dolgoztatandó hajvágás. Az egymás után, a hatóidőbe ténylegesen beleférő rövid munkák foglalhatók. Ha két festést a munkaszakaszok alatt is párhuzamosan tud végezni a szakember, ezt külön, tudatosan Párhuzamos móddal és létszámkorláttal állítsd be.

A férőhelyek ellenőrzése és mentése ugyanabban a szolgáltatói adatbázis-tranzakcióban történik. A függő jóváhagyás is elfoglal egy férőhelyet. A lemondott és elutasított foglalás nem foglal helyet.

## Csoportos órák kezelése

1. Hozz létre Csoportos típusú szolgáltatást, munkatárssal, időtartammal, **fejenkénti árral** és létszámmal. Hatóidős szakaszokat ehhez ne adj meg.
2. Naptár → **Csoportos órák → Új csoportos alkalom**. Válassz napot, kezdést, munkatársat és férőhelyet. Más foglalással, szünettel vagy másik csoportos órával nem ütközhet.
3. Az alkalom jelentkezők nélkül is lefoglalja a munkatárs idejét. Heti/napi naptárban külön jelzés mutatja. Havi nézetből is elérhető a Csoportos órák kezelője.
4. A vendég a szolgáltatás kártyáján látja a következő alkalmakat és szabad helyeket, vagy napot választva a meghirdetett kezdések közül választ. Telített órára nem foglalhat; tetszőleges más kezdést az API sem fogad el.
5. A szalon a Résztvevők / férőhelyek ablakból külön vendéget adhat hozzá. Minden résztvevőnek saját foglalása, fizetése, bérlethasználata és lemondási hivatkozása van. Egy foglalás egy férőhely.
6. A férőhelyek növelhetők, a foglalt létszám alá nem csökkenthetők. A teljes óra lemondása minden aktív jelentkezést lemond, felszabadítja a bérletfoglalásokat és sorba állítja a beállított értesítéseket. Pénzvisszatérítés nem indul automatikusan.

A meghirdetett alkalom időtartama és ára rögzített. Ezek vagy a szolgáltatás/munkatárs megszüntetése előtt le kell mondani az érintett jövőbeli alkalmakat. Másik időpontot új alkalomként hirdess meg. Jelenleg egyenként lehet alkalmakat létrehozni; nincs automatikus heti ismétlődéssorozat. A Google-be a résztvevői foglalások szinkronizálódnak; az üres alkalom önmagában nem külön Google-esemény.

## Vendég saját Google Naptára

A szalon nem csatlakoztathatja a vendég naptárát: a szerver is kizárja ezt, akkor is, ha a böngészőben vendégmunkamenet is maradt. A munkatársi naptárkapcsolat ettől külön működik.

Ha a központi Google-kapcsolat be van állítva, bejelentkezés/regisztráció után a vendégoldalon egyszer megjelenik az összekapcsolási ajánlat. A választást a vendég teszi meg; automatikus jogosultságadás nincs. A felajánlást fiókonként jegyezzük meg, nem csak az adott böngészőben. A **Most kihagyom** után később a Saját fiókom Google Naptár-részében kapcsolható. A foglalás közben indított összekapcsolás visszavezet a foglalási oldalra, és visszatölti a megjegyzett szolgáltatást/napot/idősávot, ha az még elérhető.

## Vendégoldali megjelenés

Az elérhetőség-megjegyzés hosszú magyarázata az ⓘ ikonra mutatva vagy arra fókuszálva jelenik meg; mobilon megérinthető. A foglalási oldal lábléce egyértelműen a rendszer üzemeltetőjét, Digit Marketinget jelöli, elkülönítve a foglalható szolgáltató saját adataitól. Az adatok forrása: https://digitmarketing.hu/elerhetosegek .

A foglalási CSV a név nélküli jelzést, meg nem jelenési díjat és csoportos alkalom azonosítóját is megőrzi. Csoportos foglalás csak a saját, már létező és egyező alkalomhoz importálható; a férőhelykorlát importkor sem kerülhető meg. Teljes költöztetéshez/mentéshez az adatbázismentést használd, ne csak a foglalási CSV-t.

### A láblécadatok betöltése

A valós elérhetőségek külön `lablec-privat.json` fájlban érkeznek, és nem szerepelnek a nyilvános forráskódban. Töltsd fel a webgyökéren kívülre, például `/home/digitmar/lablec-privat.json`, majd:

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan raero:footer-import /home/digitmar/lablec-privat.json
```

A parancs csak a kifejezetten nyilvános láblécmezőket menti külön beállításba. Nem teszi közzé a privát számlázási/banki konfigurációt. A mezők: `name`, `email`, `phone`, `address`, `taxNumber`, `website`, `facebook`, `contactUrl`. Feltöltés/import nélkül a lábléc csak a névre és az elérhetőségi oldalra hivatkozik.
