# Ráérő app és folytatható névjegyimport – 2026. november 21.

## Telepíthető Ráérő app

Mobilon bejelentkezés után rövid, bezárható ajánló: Telepítsd a Ráérő appot! / Nyisd meg legközelebb egy érintéssel. Telepítés, Később és ×. Az elrejtés az adott böngészőn hét napig él. A Beállítások részen Ráérő app telepítése gomb, a láblécben azonos nevű hivatkozás marad. A munkatársi felület láblécéből is elérhető. Telepített, standalone alkalmazásban nincs ajánló. A telepítés tényleges appinstalled eseményét megjegyezzük, a támogatott böngésző felugrójának elutasítása nem számít sikeres telepítésnek.

Android/Chromium: ha a böngésző kiadta a beforeinstallprompt eseményt, a gomb közvetlenül a böngésző saját telepítőablakát indítja. A felhasználó jóváhagyása szükséges, automatikus ikonelhelyezés nincs. Ha még nincs telepítőesemény vagy más böngészőről van szó, rövid menüútmutató jelenik meg. iPhone/iPad: három ikonos lépés: Megosztás → Főképernyőhöz adás → Hozzáadás; szükség esetén Safari megnyitása. Az elnevezés és a támogatás OS-/böngészőverziótól függhet. Nem kínálunk külön APK-t, profilt vagy áruházi alkalmazást.

A név Ráérő, külön 180/192/512 px PNG ikonnal és SVG favikonnal. A jel a meglévő sötétkék óra motívumot használja, kis méretben is felismerhetően, maszkolható biztonságos középső területtel. A PNG-források base64 szövegként kerülnek a forráscsomagba, a /pwa/icon-{size}.png útvonal valódi image/png választ ad.

A /app.webmanifest stabil alkalmazásazonosítót (/raero-app), standalone megjelenítést és /app indulócímet tartalmaz. A /app bejelentkezve a saját szolgáltató munkaterére irányít. Egyébként a böngészőben megjegyzett szolgáltatói belépésre vagy a közös belépőoldalra visz. A manifest nem tartalmaz tokent, vendégadatot vagy OAuth-paramétert. A támogatott launch_handler a már megnyitott appablakot helyezi előtérbe.

HTTPS szükséges (fejlesztéskor localhost megengedett). A weboldal frissítéseit használja az app is. Új service worker nem erőltet újratöltést aktív import közben. Offline csak egy rövid Nincs internetkapcsolat képernyő jelenik meg Újrapróbálom gombbal; a munkaterület internetet igényel.

A service worker kizárólag az offline képernyőt és a három nyilvános ikont tárolja. HTML-oldalt, API-választ, vendégadatot, feltöltést, sessiont és tokent nem gyorsítótáraz. Navigáció hálózatról történik, hálózati hibánál jön a semleges offline képernyő. PWA-telepítés és a böngészőnként megjegyzett felületi beállítások nem engedélyezik a hirdetési vagy statisztikai sütiket.

## Folytatható névjegyimport

A beolvasott névjegyek az első listában egységesített magyar számmal jelennek meg (+36/0036 → 06); kézi módosítás után a mező elhagyásakor is egységesítünk. Hibás/betűs/összekevert szám változatlanul ellenőrzendő marad; a szerver a végső, hiteles ellenőrző.

A szerkeszthető névjegylista és a végső egyezésellenőrzés automatikus piszkozatot ment: név, telefon, e-mail, kijelölések, csoport és keresés, lapozási oldal, az aktuális lépés és görgetési hely. A rövid Piszkozat mentve felirat jelzi, amikor a szerver már visszaigazolta. A piszkozat még nem vendéglista-import, és nem indít meghívót vagy értesítést.

A piszkozat titkosítva a szerveren, 24 óráig érhető el, szolgáltatóhoz és tulajdonosi/admin munkatársi azonosítóhoz kötve. Szerkesztő vagy vendég nem fér hozzá; más szolgáltató sem. Böngészői sessionStorage-ban kizárólag egy véletlen ablakazonosító szerepel, névjegyek nem. Az ugyanabba az ablakba történő visszatérés/újratöltés megpróbálja automatikusan visszanyitni a még nyitottnak mentett importot. Új appablakból, másik eszközről vagy szándékos bezárás után Félbehagyott vendégimportod van / Folytatom jelzésből indulhat újra.

Az ellenőrzési lépés folytatásakor a szerver új egyezéselőnézetet készít, mert az eredeti 20 perces import-előnézet lejárhatott vagy a vendéglista közben megváltozhatott. A korábbi, még érvényes kijelöléseket megőrizzük. Importálás továbbra is kizárólag a végső gombbal történik. Sikeres import után a saját piszkozat törlődik. Külön Elvetem gombbal, megerősítéssel is törölhető. Új import előtt a rendszer felajánlja a félbehagyott import folytatását vagy elvetését.

Verzióellenőrzés védi a több ablakból végzett munkát: régi ablak nem írhat felül és nem törölhet újabb piszkozatot. Ilyenkor üzenet és Friss piszkozat megnyitása lehetőség jelenik meg. A meglévő raero:contact-import-cleanup ütemezett takarítás a lejárt piszkozatokat is törli. Mentéshez hálózat kell; az OS azonnali bezárása vagy megszakadt kapcsolat előtt még vissza nem igazolt utolsó módosítások megőrzése nem garantálható. Mentési hiba látható és újrapróbálható.

Ez a névjegyimport szerkesztési és ellenőrzési lépésére vonatkozik. A natív telefonos névjegyválasztó/fájlválasztó még át nem adott eredményét a weboldal nem tudja menteni. A külön Google Naptár-import 20 perces előnézetének élettartama változatlan.

## Telepítés és ellenőrzés

A meglévő .env, APP_KEY és storage megőrzendő. Kódcsere után optimize:clear, raero:upgrade és raero:check; új külső szolgáltatói kulcs vagy külön cron nem szükséges. A meglévő percenkénti schedule:run cron marad. Nincs új adatbázismigráció.

PHP-tesztek: manifest és PNG-méretek, biztonságos indulócím, piszkozat állapota/kijelölései, nincs valódi vendég/értesítés mentés, több ablak verzióütközése, tenant- és jogosultsági elkülönítés, 24 órás lejárat. Böngészőteszt: rövid ajánló és bezárás, állandó beállításgomb, telepítési esemény átadása, telepítettség, iPhone háromlépéses útmutató, offline cache kizárólag nyilvános fájlokkal, 25 névjegy javítása és kijelölése, háttérből visszatérés, két újratöltésen át listás és ellenőrzési állapot, bezárás/folytatás, tényleges import utáni törlés. Valódi Android-/iPhone-telepítés végső készülékpróbát igényel; automatizált környezetben a natív telepítőablak eseményét helyettesítettük.
