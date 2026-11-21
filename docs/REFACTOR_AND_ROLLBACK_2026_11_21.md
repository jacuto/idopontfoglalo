# Működésmegőrző átszervezés és visszaállítás

## Rögzített kiindulópont

- Korábbi teljes kiadás: `598885929cd7b3d1ae215e0e53b58759f9dfd0bf`.
- Forrás ZIP: https://github.com/jacuto/idopontfoglalo/archive/598885929cd7b3d1ae215e0e53b58759f9dfd0bf.zip
- Forrásfa: `49283dfd2b78843a7cdd6f55d295ac4d9c4df174`.

Ez a változtatás nem módosít adatbázissémát, üzleti adatot, fizetési szabályt vagy jogosultságot. Éles telepítés nem történt. A korábban külön feljegyzett gombelrendezés és vendégfoglalási kártyák áttervezése nem része ennek a csomagnak.

## Mit változtat

1. Tizennyolc felülírt, a végső hívási útvonalakból már nem elérhető implementáció és négy nem használt függvény-pillanatkép eltávolítása. Érintett példák: korábbi munkatársűrlap, befizetési űrlap, üzenetoldal és beszélgetés, hírlevéloldal, számlázási oldal.
2. Az oldalrajzolás húsz és a naptár tizenkét függvény-felülírása helyett egy-egy stabil belépési pont és a közös `RaeroUI.lifecycle`.
3. A befizetési űrlap, a kupon/bérlet lapozás, a beállítási navigáció és a közösségi belépés további felülírásainak beépítése a végleges implementációba.
4. Párhuzamos írások közös CSRF-előkészítése. Az írások külön megmaradnak, nem kerülnek gyorsítótárba és nem ismétlődnek meg pluszban. A korábbi egyszeri 419-es újrapróbálás megmarad.
5. A meglévő JavaScript és CSS előállítása tartalom alapján verziózott csomagokba, változatlan sorrendben. A sütihozzájárulás fejléces scriptje külön marad, hogy a hozzájárulási sorrend ne változzon.

## A közös életciklus

`RaeroUI.rendering` és `RaeroUI.calendarRendering` ugyanazt a kis könyvtárat használja. Egy modul `add(név,{before,after})` hívással regisztrálja a saját részét. A `before` lépések kívülről befelé, az `after` lépések belülről kifelé futnak, pontosan a korábbi beágyazott hívások sorrendjében. A `before` visszatérési értéke az adott hívás saját kontextusa; beágyazott újrarajzolás nem írja felül.

A naptár előfizetés miatti zárolása `RaeroUI.lifecycle.SKIP` értékkel kihagyja a belső rajzolást, de megtartja a korábban is lefutó külső lépéseket. Az aszinkron emlékeztető-újrarajzolás `runBefore('launch-tools:1',calendarWorkspaceBase)` hívással kizárólag a korábban elmentett belső részt futtatja; így nem indít új emlékeztetőbetöltési kört.

Új modult ne `const old=render; render=function(){old();...}` mintával bővítsünk. A szükséges életciklushoz regisztráljunk névvel ellátott lépést. A regisztrációk sorrendjét a `frontend.sources.json` adja meg, a duplikált regisztráció hibát jelez.

## Mérhető eredmények

| Mérőszám | Korábbi kiadás | Új csomag |
| --- | ---: | ---: |
| Alkalmazás JavaScript, tömörítetlen bájt | 922 967 | 859 122 |
| Azonos gzip módszerrel tömörített JavaScript, bájt | 312 912 | 259 591 |
| JavaScript-kérések, süti scriptjével együtt | 46 | 2 |
| CSS-kérések az alkalmazásoldalon | 12 | 1 |

A JavaScript mérete 6,92%-kal, a számított gzip-méret 17,04%-kal kisebb. A gzip-adat összehasonlító fájlmérés; a tényleges hálózati tömörítést az éles webszerver beállítása határozza meg. A kérésdarabszámot böngészőben is ellenőriztük.

Nem állítunk konkrét betöltésiidő- vagy CPU-gyorsulást: a helyi tesztszerver nem reprezentálja az éles környezetet. Az összes forrásfájlt és a kész csomagokat együtt tartalmazó fejlesztési ZIP nagyobb lehet. A böngésző a kész csomagokat kéri, a külön forrásfájlokat normál módban nem tölti le.

## Build és gyorsítótár

A fejlesztői gépen:

```bash
npm ci --ignore-scripts
npm run build
npm run check:assets
node --test tests/asset-build.test.cjs tests/api-csrf.test.cjs tests/render-pipeline.test.cjs
```

A Node.js, Acorn, Terser és CleanCSS csak fejlesztési függőség. Az éles tárhelyen nem szükséges npm vagy build: a `public/build/` kész fájljai a kiadás részei. A minifikálás nem nevez át változókat, és nem alkalmaz logikai kódtömörítést. A forrásfájlok megmaradnak szerkeszthető formában.

Az egyszerű script-összefűzés hibás lenne: a későbbi függvénydeklarációk előrehozása megváltoztatná a korábbi függvények pillanatképeit. A build minden eredeti script elején állítja elő az ott eredetileg elérhető függvényeket. Ezt külön tesztek ellenőrzik rekurzióval, generátorral és aszinkron függvényekkel is. Nem használ evalt vagy dinamikus inline scriptet az éles futásban.

A kész fájlok nevében tartalomhash van. Apache alatt a `public/build/.htaccess` változatlan tartalomhoz hosszú gyorsítótárat engedélyez, ha a mod_headers elérhető. A privát oldalak és API-válaszok továbbra sem kerülnek ebbe a gyorsítótárba. A service worker adatkezelése nem változott.

## Forrásmód és visszaállítás

Ha a manifest vagy valamelyik kész fájl hiányzik, az alkalmazás automatikusan a külön forrásfájlokat használja. Ugyanez kézzel is kérhető a `.env` fájlban:

```dotenv
FRONTEND_BUNDLED=false
```

Majd a konfiguráció és a nézet gyorsítótárának ürítése: `php artisan optimize:clear`. Ez csak a csomagolt betöltést kapcsolja ki; a refaktorált forráskód marad használatban.

A teljes kód-visszaállításhoz a fenti korábbi ZIP-et kell visszatenni, a saját `.env`, `APP_KEY`, `storage`, adatbázis és meglévő `vendor` megőrzésével, majd `php artisan optimize:clear`. Az új `public/build` fájlok a régi nézetben nem hivatkozott fájlok lesznek. A ZIP önmagában nem adatbázis-mentés; telepítés előtt a szokásos teljes biztonsági mentés továbbra is szükséges.

## Ellenőrzés és határok

- Teljes PHP regressziós csomag: 363 teszt, 2682 assertion, 4 kihagyott teszt, a build-manifest és a forrásmód ellenőrzéseivel.
- 12 sikeres Node-regresszió: build-sorrend, CSRF-párhuzamosság, render-sorrend, egymásba ágyazott render, zárolás és emlékeztető-részrajzolás.
- A korábbi kiadás tényleges JavaScript/CSS fájljaival böngészős előtte–utána összehasonlítás: naptár és zárolt naptár, vendégek, szolgáltatások, munkatársak, foglalási események, befizetések, statisztika, alapbeállítások, kuponok, nyilvános adatlap; vendég-, munkatárs-, szolgáltatás- és befizetési űrlap; mobil vendéglista és naptár; négy nyilvános útvonal. Látható szöveg, mezők, kijelölt értékek és mintavételezett számított stílusok összehasonlítása.
- Meglévő előzménylapozási, PWA/importpiszkozat- és telefonformátum-böngészőtesztek. A külön forrásfájlokat használó tartalék betöltési mód is böngészőben ellenőrizve.

Nem minden üzleti modul felülírási lánca szűnt meg. Az aszinkron foglalási, jogosultsági és összetett beállítási ágak egy része továbbra is külön bővítés. A nyilvános és admin kód teljes szétválasztása sincs kész: ezek több közös globális függvényt használnak. Ezeket nem távolítottuk el feltételezés alapján. A mostani csomag ellenőrzött, mérhető előrelépés, nem bizonyíték a teljes alkalmazás minden ismétlődésének megszüntetésére.
