# November 17. – átlátható listák, vendégadatlap és képfeltöltés

## Listák

- Vendégek: alapból 25, választható 25/50/100 tétel oldalanként. Név, e-mail, telefon, foglalásszám és legutóbbi foglalás szerint rendezhető. Több munkatársas működésben jellemző munkatárs szerint is szűrhető és rendezhető.
- A jellemző munkatárs a legtöbb nem lemondott/elutasított és nem meg nem jelenésként jelölt foglalással rendelkező munkatárs; holtversenyben mindegyik megjelenik. Nincs külön, kézzel karbantartandó vendég–munkatárs-hozzárendelés.
- Szolgáltatások és munkatársak: 10/15/20 kártya oldalanként, kereséssel és rendezéssel.
- Foglalási események: 25/50/100 sor, keresés, dátumtól–dátumig, szolgáltatás és állapot szűrő; vendég, időpont, szolgáltatás, állapot, utolsó módosítás szerinti rendezés.
- Befizetések: külön lapozás és rendezés a rögzítésre váró összegeknél és a befizetési előzményeknél; 25/50/100 sor. A korábbi első 50 függő találatot mutató korlát megszűnt.
- Üzenetek: kereshető, rendezhető beszélgetéslista, továbbá az egyes beszélgetések üzenetei is lapozhatók. Nincs automatikus üzenetküldés az új listanézet miatt.
- Előfizetés: külön lapozható Havi díjtételek, Tranzakcióim és Számláim, 25/50/100 tétellel.

A keresés és rendezés a teljes adott listát érinti, nem csak az aktuális oldalt. Szűrőváltás az első oldalra visz. Túl magas oldalszámot az utolsó létező oldalra igazítunk. A választások az oldalak közötti navigálás során megmaradnak; friss betöltéskor a megadott alapméret érvényes.

Fejlesztői megjegyzés: a lapozás jelenleg a meglévő munkatér állapotlekérésére támaszkodó böngészős megjelenítési lapozás. Nem állítja, hogy az `/api/state` most már csak 25 rekordot tölt le. A teljes állapot szerveroldali szétválasztása külön adatkezelési változtatás lenne. A vendég–munkatárs összesítés egyetlen bejárásból készül.

## Vendégadatlap és navigáció

A vendég neve alatt külön **Adatlap megnyitása →** hivatkozás jelzi, hogy ez a részletes nézet. Az adatlap tetején a telefonszám és az Üzenet gomb, jobb felső sarkában bezáró ikon van. A telefonszám alul nem ismétlődik. Az alsó műveletsor: kiemelt **Adatok szerkesztése**, **Eltávolítás**, **Feketelistára teszem / Tiltás feloldása**. Mobilon a műveletek szükség szerint több sorba kerülnek. A meglévő szerveroldali jogosultsági határok megmaradtak.

Az oldalsávban az Előfizetés alatt, legutolsóként található a Beállítások. A naptár fejlécében az Új időpont a jobb szélső művelet. A munkatárskártya törlő X ikonja rögzített méretű, középre igazított vezérlő.

## Saját emlékeztetők kapcsolója

Beállítások → Alapbeállítások → Használt funkciók → **Saját naptári emlékeztetők**. A korábban már használt funkció alapból bekapcsolva marad. Kikapcsolva a gomb, a naptári események és az esedékességi figyelmeztetés eltűnnek, a szerver sem adja ki az eseményeket és nem enged módosítani. Az adatokat megőrizzük, visszakapcsoláskor újra látszanak. Ez a saját teendőket kapcsolja, nem a vendégnek küldött időpont előtti emlékeztetőket.

## Nyilvános adatlap

- Kategóriák: egységes, kompakt jelölők; pipa és név egy sorban, kiválasztásnál kék kiemelés.
- Bemutatkozás: kisebb rövid leírás, egységes szélességű részletes szerkesztő, külön foglalási és lemondási feltételek.
- Nyitvatartás leírása: az Elérhetőség és kereshetőség fülön.
- Képek: külön profilkép/logó, borítókép és üzletfotó kártyák, korlátokkal, előnézettel, cserével és törléssel.
- Az OTP SZÉP-kártyás előrefizetés új/alap beállításként nincs kijelölve. A már kifejezetten mentett fizetési módokat nem vonjuk vissza.
- Stripe, SimplePay, OTP SZÉP, Barion, Billingo, Számlázz.hu és hírlevél-paraméterek mellett rövid súgószöveg és szükség szerint külső hivatkozás segít.

## Képek – szerveroldali korlátok

Minden kézi képbeküldésnél JPG, PNG vagy WebP engedélyezett, maximum **5 MB / kép**, legfeljebb **6000 × 6000** bemeneti felbontással. Hibás vagy ténylegesen nem dekódolható kép elutasításra kerül. Nagy felbontásnál a szerver a rendelkezésre álló memóriát is ellenőrzi; ha a biztonságos feldolgozáshoz kevés, kisebb felbontású képet kér.

A mentett kép újrakódolt, tömörített WebP. Az eredeti nagy kép nem kerül mellé eltárolásra. Az arány és az átlátszóság megmarad, kisebb képet nem nagyítunk fel. JPEG esetén az EXIF-tájolást figyelembe vesszük, a mentett képen a bemeneti metaadatokat nem őrizzük meg.

| Típus | Mentett kép hosszabbik oldala |
| --- | --- |
| Profilkép / logó, vendégprofilkép | legfeljebb 800 pixel |
| Munkatárs- és szolgáltatáskép | legfeljebb 1000 pixel |
| Borítókép és üzletfotó | legfeljebb 1500 pixel |

Darabszám: 1 logó/profilkép, 1 borítókép, legfeljebb 5 üzletfotó, munkatársanként és szolgáltatásonként 1 aktuális kép. Csere/törlés után a már nem hivatkozott saját médiafájl és rekord felszabadul; másik aktuális helyen még hivatkozott fájlt megőrizzük. A korábban feltöltött képeket ez a kiadás nem tömöríti át tömegesen.

**Tárhelyfeltétel:** PHP GD bővítmény WebP támogatással. Ezt a cPanel PHP-bővítményei között lehet bekapcsolni; a `php artisan raero:check` figyelmeztet, ha hiányzik. A webszerver PHP-beállításaiban az `upload_max_filesize` legalább 5M, a `post_max_size` legalább 8M legyen. A többi funkció GD nélkül is működik, de új kép feldolgozásához a bővítmény szükséges.

## Ellenőrzés és frissítés

A teljes automatikus ellenőrzésben 284 teszt, 2051 állítás: 280 sikeres, 4 külön MySQL-környezetet igénylő teszt kihagyva. Külön böngészős próbák: 61 vendég és esemény, 31 szolgáltatás/munkatárs, 61 havi díjtétel/tranzakció/számla, 125 üzenet; teljes listás keresés, szűrés, rendezés, lapozás, vendégműveletek, kép- és kategóriaelrendezés, emlékeztető kapcsoló, mobil szélesség.

A kiadás fájlcserével frissíthető, új adatbázistáblát nem vezet be. A `.env`, APP_KEY és storage megőrzendő. A GitHub-feltöltés nem éles telepítés. A FRISSITES.md alapján fájlcsere után:

```sh
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

Böngészőben Ctrl+F5.
