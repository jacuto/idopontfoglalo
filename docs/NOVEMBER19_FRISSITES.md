# 2026. november 19. – felületi és működési javítások

## Foglalás és megjelenés

- Kikapcsolt közösségi belépés ikonja nem jelenik meg, betöltés közben sem.
- Minden lapozó középre igazítva, legfeljebb tíz egymást követő oldalszámmal. Nincs vízszintes csúszka.
- A szolgáltatáskártyákon jobb felső törlésikon, az árral egy sorban jobbra igazított Szerkesztés.
- Egységes magasságú munkatárskártyák, igazított alsó műveletekkel.
- Középre igazított bezáró és törlésikonok. A törlés megerősítést kér, megszakításkor nem küld törlési kérést.
- Egységes, kék telefonhivatkozások. A feltöltési tájékoztató csak formátumot és fájlméretet mutat; a képkicsinyítés változatlan.
- A megadott cím térképe a foglalóűrlap alatt jelenik meg. Az üzletfotók a bemutatkozó tartalom legvégén, „Képek az üzletről” címmel láthatók.
- Kikapcsolt bérletfunkciónál a vendég adatlapján sincs Bérletek rész.
- Keskeny képernyőn tördelődő naptárfejléc, kiemelt Új időpont gombbal.
- A naptár feletti emlékeztetősáv csak a következő hét esedékes, befejezetlen emlékeztetőit mutatja. Üres sáv nincs; a naptári események továbbra is a megfelelő napokon látszanak.

## Webcímek és várólista

Elhagyható a https:// előtag a webcímeknél; a megadott http:// és https:// címek is érvényesek. Facebook és Instagram mezőben csak az adott szolgáltatás domainje és annak aldomainje fogadható el. A szerver is ellenőrzi a címeket.

A várólistás jelentkezés a nap, munkatárs és szolgáltatás kiválasztása után jelenik meg, ha egy meglévő foglalás elvesz egy egyébként foglalható kezdést. Teljesen szabad nap, zárvatartás, szünet vagy szabadság önmagában nem indokol várólistát. A nyilvános ellenőrzés csak igen/nem eredményt ad, vendég- és foglalásadatot nem.

## Előfizetés

A csomag, a megújulás és az érvényesség külön, rendezett blokkokba került. A megújulás lemondása mellett látszik a kifizetett időszak vagy próbaidő vége.

A fizetős plusz naptár lemondható az Előfizetés oldalon. A kiválasztott naptár a kifizetett időszak végéig használható, majd archiválódik. A következő havi díj 2 000 Ft-tal csökken. A megerősítés előtt látszik a dátum és az új havidíj. A meglévő foglalások nem törlődnek; a kikapcsolás utáni időre új foglalás nem kérhető. Az utolsó aktív naptár nem kapcsolható ki.

Stripe-előfizetésnél a rendszer a kiegészítő mennyiségét módosítja időarányos terhelés/visszatérítés nélkül, ellenőrzi a Stripe-választ, és azonos kérésazonosítóval engedi az ismétlést. Átutalásnál a következő időszak díjtétele már a kisebb összeget használja. A korábbi díjtételek változatlanok. Egy időben egy naptárlemondás kezelhető; folyamatban lévő módosításnál a képernyőn folytatható ugyanaz a kérés.

## Opcionális automatikus befizetés

Befizetések → Alapértelmezett paraméterek → Automatikus befizetésrögzítés. Alapból kikapcsolva.

Csak elérkezett, elfogadott, **Megjelent** jelölésű foglalás után készül tétel, a fennmaradó összeggel és a megadott alapértelmezett fizetési móddal. A kedvezményeket, bérleteket és korábbi befizetéseket figyelembe veszi. Ez könyvelési automatizmus, nem banki ellenőrzés és nem kártyaterhelés.

A létrehozott tétel követi a foglalás módosítását, lemondását és törlését; minden változásnak marad előzménye. Már számlázott/visszatérített tételt nem ír át, hanem korrekciós figyelmeztetést mutat. A kézzel javított vagy törölt automatikus tételt nem hozza létre újra. Kikapcsoláskor új tételek nem keletkeznek, a korábbi automatikus tételek követése megmarad.

A meglévő Laravel ütemező percenként futtatja a `raero:auto-payments` feladatot; a foglalás változtatásakor a rendezés azonnali. Ha a tárhelyen még nincs beállítva a percenkénti `php artisan schedule:run`, az idő múlásával esedékessé váló könyveléshez azt be kell állítani.

## Telepítés

A teljes csomag tartalmazza a korábbi javításokat. A meglévő `.env`, APP_KEY, adatbázis és `storage` megőrzendő. Nincs új adatbázistábla vagy függőség, a mintaadatokat nem kell újragenerálni.

```sh
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

Végül böngészőben Ctrl + F5. Valódi Stripe-módosítás, levélküldés vagy termelési adatváltoztatás a fejlesztés során nem történt.
