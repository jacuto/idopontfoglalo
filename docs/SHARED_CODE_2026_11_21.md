# Közös programkönyvtár és ismétlődések csökkentése

A funkciók és API-végpontok megtartásával a közös működés a `public/lib/` könyvtárba került. Nincs új futásidejű CDN vagy külső JavaScript-függőség.

## Könyvtárak és használat

- `ui.js`: `RaeroUI.page`, `pagination`, `modal`, `forms`, `action`, `search`, `media`, `clipboard`, szövegkezelés és ikonok.
- `lists.js`: `RaeroUI.WorkspaceList`, helyi és távoli előzménylisták. Szűrés és rendezés után lapoz; a meglévő oldalméretek megmaradnak.
- `components.js`: magyar dátum/idő átalakítás, importált telefonszám megjelenítése, infóikonok, közösségi belépés ikonjai, beállítási tabok, checkboxok és funkciókapcsoló-kártyák. `RaeroUI.files.save` kezeli a CSV, QR és szerveres exportok letöltését és az ideiglenes objektumhivatkozás felszabadítását.

Új keresőhöz `RaeroUI.search(input,{minLength:3,delay:180,load,results,error})` használható: a korábbi kérés később érkező eredménye nem írja felül az új keresést. Az eltérő üzleti keresési küszöbök paraméterezhetők.

Űrlaphoz `RaeroUI.forms.bind(form,{submit,success,error})` használható; a `submit` megkapja a `{form,event,data}` objektumot. Folyamatban lévő mentés alatt a közös `action` megakadályozza a párhuzamos elküldést, hibánál visszaengedi az újrapróbálást.

A `descriptionHtml` és az import infópanel HTML-paramétere kizárólag megbízható alkalmazássablon lehet; a nevek és sima feliratok HTML-escape-eltek.

## Ténylegesen átállított felületek

Vendég/munkatárs mentés, vendégkeresés, üzenet- és hírlevélcímzett-keresés, kupon/bérlet mentés, listák és előzmények, névjegy/naptárimport lapozása, popup nyitás/bezárás, képfeltöltés, szolgáltatás- és munkatársképek előnézete, linkmásolás, beállítási tabok, várólista/emlékeztető kapcsolók, pontgyűjtés és központi emlékeztető checkbox, dátumformázás, CSV/QR/naptár/statisztika letöltés.

A közösségi belépés felületei ugyanazt a meglévő aktív renderert és AuthController folyamatot használják; a felülírt korábbi renderer eltávolítva. A szolgáltató/vendég mód és visszatérési cél megmaradt. A névjegy- és naptár-OAuth eltérő jogosultságai és callbackjei külön maradnak.

## Szerveroldal

`WorkspaceAccess` közös adminellenőrzés, opcionális tenant-ellenőrzéssel. `ImportStaging` közös, titkosított, munkamenethez és szolgáltatóhoz kötött import-előnézet-tárolás. A korábbi cache-kulcsok és lejárati idők kompatibilisek; a 24 órás piszkozat külön protokoll marad.

## Eltávolított kód

Hivatkozások és betöltési sorrend ellenőrzése után: nem használt configSave, memberAccounts, voidPayment, régi trackingConsent/resetConsent; felülírt kupon/bérlet- és csoportosalkalom-rendererek, valamint régi socialButtons. A még aktív bővítési rétegek megmaradnak.

A natív details/summary menük és lenyílók eleve közös böngészőműködést használnak. A foglalási elérhetőség, előleg, emlékeztető, pontgyűjtés és import/export eltérő adatszabályait nem helyettesíti általános UI-kód. Ez a változtatás csökkenti az ismétléseket, nem állítja, hogy a teljes alkalmazás minden hasonló kódrészlete azonosítható vagy összevonható.

## Ellenőrzés

- Teljes PHPUnit: 360 teszt, 2673 assertion, 4 kihagyott teszt.
- Minden saját JavaScript fájl szintaktikai ellenőrzése.
- `tests/shared-ui.cjs`: önálló Playwright regressziók lapozásra, elavult AJAX-válaszra, keresési küszöbre, dupla mentésre/hiba utáni újrapróbálásra, képfájlra, dátumra, telefonra, tabra és checkboxra. Futtatás: `CHROMIUM_PATH=/usr/bin/chromium node tests/shared-ui.cjs` (Node és Playwright fejlesztői környezet szükséges).
- Böngészős ellenőrzés: tényleges vendégkeresés/mentés, szolgáltatás- és képfeltöltő űrlap, kuponok, csoportos alkalmak; mobil és távoli előzménylapozások; PWA és importpiszkozat visszaállítása; importált telefonszámok.

Nincs adatbázismigráció vagy új éles konfiguráció. Éles telepítés nem történt.
