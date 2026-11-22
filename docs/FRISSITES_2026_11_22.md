# 2026. november 22. – vendégkezelés, importálás és kezelőfelület

## Változások

- Egységes színes foglalási állapotok a listákban, adatlapokon és a vendégfiókban; az állapot szövege is megmarad.
- Az admin menüpontok és a beállítások fülei saját URL-t kapnak. A böngésző Vissza/Előre gombja visszaállítja a kiválasztott felületet és a listák szűrését, rendezését, lapját. A megnyitott, módosított űrlap elhagyása figyelmeztetést ad.
- E-mail alapján élő vendégjavaslat, böngészős automatikus kitöltéskor is. Az adott szolgáltatónál már foglalt e-maillel új profil nem hozható létre. A már kiválasztott vendég saját adatai nem váltanak ki figyelmeztetést. A korábban létrejött duplikációkat a frissítés nem vonja össze és nem törli.
- Telefonszám alapján csak javaslat jelenik meg; több vendég használhatja ugyanazt a számot. A névkeresés a szavak fordított sorrendjét is kezeli. Meglévő vendég kiválasztása eltünteti a korábbi kapcsolati ütközés hibáját, de nem nyeli el az időpont más hibáit.
- A névjegy-, fájl-, korábbi CSV- és Google Naptár-importok előnézetet és három szabályt kínálnak: hiányzó adatok kiegészítése (alapértelmezett), beolvasott adatok felülírása, meglévő rekordok kihagyása. Csak a véglegesítés ment. Üres importmező nem törli a meglévő értéket; az import kizárólag az adott importtípus támogatott, beolvasott mezőit módosítja.
- Több lehetséges névjegyegyezésnél külön választás szükséges. Eltérő név és egyező telefonszám esetén sincs automatikus összevonás. Az import nem hoz létre vendégbelépést, hírlevél-feliratkozást és nem küld értesítést.
- Az előnézet után más által módosított rekordokat a véglegesítés kihagyja. Pénzügyi, bérletes vagy kedvezményes kapcsolattal rendelkező foglalást nem ír felül import; ezeket a saját szerkesztőben kell módosítani. A régi, azonosító nélkül rögzített Google Naptár-blokkok automatikus felülírása szintén tiltott.
- Vendéglista: e-mail és utolsó korábbi alkalom, jobbra igazított telefon; mobilon az azonosítást segítő adatok a név alatt látszanak. Mobiltelefon SVG-ikon a hívási műveleteknél.
- Foglalási események: dátum, keresés, szolgáltatás, állapot sorrendű, tördelhető szűrők; keskenyebb asztali felbontásnál a hívás ikonná rövidül, hogy a műveletek egy sorban elférjenek.
- Munkatárslista: keresés és import/export közös eszközsorban, találatszám a rendezési fejlécnél.
- Közös felső bezáró X a szerkesztőablakokon, a fölösleges alsó Bezárás gomb nélkül. A checkboxok szövege mellettük marad, több sor esetén is.
- Világosabb automatikus befizetési beállítások; külön kézi, automatikus és online eredetjelölés. Az automatikus könyvelés nem banki ellenőrzés. Az alapértelmezett megjegyzés továbbra is opcionális.
- A napi, egymás melletti naptárnézetből ugyanazzal a gombbal vissza lehet állni az előző nézetre.
- A vendégfiók Kilépés gombja a profilkártyára került; a foglaláskártyák több hasábot használnak asztalon és összerendeződnek mobilon.
- Az apptelepítés beállítása az Alapbeállítások / Belépés és biztonság részben található. Az asztali lábléc hivatkozása visszafogott marad.

## Frissítés és visszaállítás

Teljes forráscsomag, benne az előállított JS/CSS és gzip fájlokkal. Nincs új futásidejű függőség vagy adatbázisséma-migráció. A meglévő `.env`, APP_KEY, `storage`, `vendor`, adatbázis és cronbeállítás megőrzendő. Az alkalmazás gyökere és a `public` fájlok együtt frissítendők; az új admin URL-ek a meglévő Laravel front controller átirányítást használják.

Telepítés előtt a működő fájlokról és az adatbázisról is készüljön mentés. Előző teljes kiadás: `b94ad0a86549a22a3ed8f24e5d5eb6a66c9944e4`. A régi forrás visszatehető; az új kiadásban véglegesített import vagy kézi adatváltoztatás visszavonásához adatmentés szükséges. Pusztán a forrás visszamásolása nem vonja vissza az adatváltoztatásokat.

A Google/Outlook kapcsolat továbbra is a megfelelő külső OAuth-beállításoktól függ; ez a csomag nem konfigurál Google/Microsoft fiókot. Az éles szerveren nem történt telepítés vagy adatmódosítás.

## Ellenőrzés

- Teljes PHPUnit-csomag: 373 teszt, 2750 ellenőrzés; 4 környezetfüggő teszt kihagyva, hiba nélkül. A végül hozzáadott három importbiztonsági esettel a külön új csomag 13 tesztje és 79 ellenőrzése is hibátlan.
- Chromium: 390, 1024 és 1280 pixeles nézetek; navigáció, űrlapelhagyás, autofill, meglévő vendég kiválasztása, importelőnézet/szabályváltás/véglegesítés, nézetváltás visszaállítása, bezárógombok és elrendezések.
- PWA/importpiszkozat: háttérbe helyezés és újratöltés utáni visszaállítás, kijelölések és lapozás megtartása; telepítési útmutató, offline oldal és biztonságos cache.
- Frontendegységtesztek, közös UI-primitívek, build-ellenőrzés; valódi helyi Apache-on a gzip/identity egyeztetés, pontos tartalom, MIME, Vary és cache fejlécek.

A böngészős ellenőrzés mesterséges helyi adatokkal történt. A Google/Microsoft élő bejelentkezést és az egyes telefonok natív telepítési párbeszédablakát nem helyettesíti.
