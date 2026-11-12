# Gyorsabb felvitel, minimumlétszám és befizetések

Az új kód feltöltése után a korábbi [adatmegőrző frissítést](../FRISSITES.md) végezd el. A meglévő `.env`, APP_KEY, `storage`, feltöltések, lábléc és adatbázis megmarad. Ehhez a bővítéshez nincs új Composer-függőség vagy törlő migráció. `php artisan optimize:clear`, `php artisan raero:upgrade`, `php artisan raero:check` futtatása után Ctrl+F5-tel frissíts.

## Vendégoldal

Ha a kiválasztott nap betelt, a foglaló automatikusan megkeresi a **mai naphoz legközelebbi szabad napot**, és átállítja a dátumot. Erről külön rövid jelzést mutat. Időpontot még a vendég választ. A keresés szolgáltatásra és munkatársra szűr; figyelembe veszi a nyitvatartást, szüneteket, foglalásokat, férőhelyeket és a korábban beolvasott Google-foglaltságot. A keresés újabb kérésre cserélődésekor a régi válasz nem írja felül az aktuális választást.

Normál szolgáltatásnál legfeljebb a következő 366 napot vizsgálja, csoportos óránál az összes ismert jövőbeli meghirdetett alkalom közül keres. Hosszabb szabadság teljesen lezárt napjait gyorsan átugorja. A beágyazott foglaló is ugyanígy működik, továbbra is csak az engedélyezett beágyazó weboldalakról.

Az elérhetőség-megjegyzés súgója közvetlenül a szöveg végén, az ⓘ ikonon jelenik meg. Az ikonra kattintás nem változtatja meg a megjegyzési pipát. A „Telefonszám vagy email megadása szükséges” ismétlődő magyarázó mondat eltűnt, a kötelező adatellenőrzés megmaradt.

## Naptár és gyors szalonfoglalás

- A Nézet gomb jól látható keretet, feliratot és középre igazított nyilat kapott az Új időpont mellett. Kis kijelzőn a felirat helyett a nyíl marad; a gombnak van felolvasási címkéje és súgója.
- A nap és idő kiemelésekor a beállítási fogaskerék is fehér. A fogaskerék most egyszínű, öröklődő színű SVG, így az emoji-betűkészlet nem írhatja felül a színét.
- A kijelölési súgó Macen csak ⌘-t ír, más gépen csak Ctrl-t.
- Az oldalsáv tetején lévő balra mutató nyíllal csukható össze. Becsukva a képernyő bal szélén benyúló füllel nyitható vissza. A rejtett menü nem kap billentyűzetfókuszt.
- A felső Üzenet / Foglalási oldalam / Kilépés műveletek kaptak felső térközt.
- Alapértelmezett szolgáltatás hiányában az első aktív, az adott munkatárs által végezhető nem csoportos szolgáltatás kerül kiválasztásra. Saját alapértelmezés továbbra is beállítható.
- A második szolgáltatás választója a kis **＋** gombbal nyitható, alapból nem foglal új sort.
- Naptári idősávra kattintva a nap már adott, ezért a dátummező rejtett. A **Nap módosítása** gombbal előhozható. Az időpontválasztó látszik.
- A vendégkeresés magyarázata a Vendég neve címke melletti ⓘ ikonba került; a kiválasztott vendég visszajelzése megmaradt.

## Szolgáltatások és vendégadatok

Egyéni szolgáltatásnál nem látszik és nem szerkeszthető a férőhelyszám. Minden foglalási típusnál csak az ahhoz tartozó rövid magyarázat jelenik meg. A munka–hatóidő–munka mező címkéjének végén lévő ⓘ egy 95 perces hajfestés 30–35–30 perces példáját mutatja. Csoportos óránál a hatóidős mező rejtett. A szolgáltatáskép és az időtartamjelvény közé térköz került.

Új vendég létrehozásakor kötelező a név és legalább egy érvényes elérhetőség. A telefon és email mentés előtt szerveroldali ellenőrzést kap. A magyar szám például `+36 30 123-4567` helyett `06301234567` alakban mentődik; a felületeken tagolva, a hívóhivatkozásban nemzetközi alakban szerepel. A böngésző már a telefonszámmező elhagyásakor is jelez hibás formát. A regisztrált vendég saját elérhetőségeinek jogosultságvédelme megmarad. Az új vendég mentése külön végpontot használ, nem menti újra a teljes vendéglistát.

## Foglalás állapotának javítása

Az időpont adatlapján és a Foglalási események sorában van **Állapot módosítása / Állapot** gomb. A jövőbeli, még le nem zárt foglalás átállítható Jóváhagyásra vár, Megerősítve, Lemondva vagy Elutasítva állapotra.

Visszaállításkor újra ellenőrizzük az időpontot, a csoportos helyet, a kupon felhasználási keretét és a bérletet. A lemondáskor visszaadott bérletalkalom csak akkor foglalható vissza, ha még elérhető és érvényes; kétszer nem vonódhat le. Közben elfoglalt időpontot nem állít vissza. Elmaradt teljes csoportos órát egy résztvevő státuszával nem lehet újraindítani. A lezárt Megjelent / Nem jelent meg alkalmak védelme megmaradt.

A befizetések és visszatérítések az állapotmódosítástól függetlenül megmaradnak. A beállított értesítések sorba kerülnek; nem történik automatikus visszatérítés.

## Csoportos minimumlétszám

Naptár → Csoportos órák → Új csoportos alkalom vagy Résztvevők / feltételek:

- **Minimumlétszám:** 0 esetén nincs minimumfeltétel. Például 3 esetén legalább 3 megerősített jelentkezés kell. A jóváhagyásra váró jelentkezés férőhelyet foglal, de a minimumba nem számít bele.
- **Kezdés előtt hány perccel legyen meg:** például 120 = két órával előtte, 1440 = egy nappal előtte, 0 = kezdéskor.
- A minimum nem lehet nagyobb a férőhelynél, a létrehozáskori ellenőrzési határidőnek pedig a jövőben kell lennie.

A határidő elérésekor elégtelen létszámnál az óra **Elmaradt** állapotot kap. A jelentkezések lemondódnak, a foglalt bérletalkalmak felszabadulnak, a vendégeknek és a szolgáltatónak értesítések kerülnek sorba. Üres óránál is értesíthető a szolgáltató a beállított értesítési címén. A megadott határidő után már nem menthető meg késői új jelentkezéssel az akkor elégtelen létszámú óra.

Ha határidőre megvolt a minimum, az ellenőrzés eredményét rögzítjük. Egy későbbi egyéni lemondás önmagában már nem indít új automatikus elmaradást. A feltétel a határidő után nem írható át. A szolgáltató a teljes órát az **Elmaradtnak jelölés** gombbal kézzel is lezárhatja, amennyiben a résztvevők látogatása még nincs véglegesen igazolva. Az elmaradt órák visszanézhetők a csoportos listában; a foglalás adatlapján az ok is szerepel.

A minimumellenőrzést a meglévő percenkénti `php artisan schedule:run` cron indítja. A parancs külön is futtatható: `php artisan raero:group-check`. A foglalási tranzakció a lejárt létszámfeltételt a cron két futása között is figyelembe veszi. A párhuzamos ellenőrzés nem készít dupla lemondást vagy értesítést. A küldés a külön értesítési sorból történik, működő központi SMTP / szükség esetén BulkGate kapcsolat kell hozzá. Hiányzó kapcsolatnál az értesítés blokkolt tételként megmarad. A minimum miatti elmaradáshoz saját főadmin HTML-sablon is tartozik.

## Befizetések és alapértelmezések

A Befizetés rögzítése időszakai között megjelent az **Elmúlt 1 hét**.

A Befizetések oldalon a **Rögzítésre váró befizetések** részben automatikusan feltűnnek az elkezdődött, elfogadott időpontok fennmaradó fizetendő összegei. A részfizetéseket levonjuk; a teljesen rendezett, bérlettel fedezett vagy lemondott alkalom nem jelenik meg. A meg nem jelenési díj is megjelenhet, ha külön felszámították és még nincs rendezve. A lista a megnyitott oldalon percenként frissül, kereshető, és alapból az elmúlt hetet mutatja.

Ezek fizetendő tételek, nem igazolt pénzbeérkezések. A **Beérkezés rögzítése** gomb előre kiválasztja a foglalást; az összeg, a fizetésmód és a tényleges időpont ellenőrizhető. Csak mentés után jelenik meg tényleges bevételként a statisztikában. Kétszer ugyanazt a fennmaradó összeget nem engedi befizetni.

A **Befizetések → Alapértelmezett paraméterek** gombnál állítható az automatikus megjelenítés, az előre kitöltött fizetési mód és megjegyzés. Ezek minden rögzítéskor felülírhatók. Az automatikus lista kikapcsolása nem töröl befizetést. A meglévő számlázás, Stripe-visszaigazolás és kézi befizetések továbbra is működnek.
