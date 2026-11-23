# Kiegészítő felületi javítások – 2026. november 22.

- A közös checkbox-elrendezésben a szöveg balra zárva, közvetlenül a jelölőnégyzet után jelenik meg. A folytató sorok a szöveghez igazodnak. A dinamikusan létrejött és korábban külön osztály nélküli checkboxos címkék is ezt használják.
- Az Új időpont ablak fejlécében külön hely marad a bezáró X számára; a Nap módosítása gomb kis képernyőn tördelődik.
- Egységes, tömör SVG telefonkagyló ikon a hívásgombokban és a telefonszámok mellett. A nyilvános adatlap, vendégadatlap és jóváhagyási lista is ugyanazt a közös ikont használja.
- A jóváhagyási listában a telefonszám jobbra, a név/időpont mellé kerül. Kisebb képernyőn szükség szerint tördelődik. Az elmúlt időpontról szóló, külön kért tájékoztató szöveg kikerült; a szerveroldali elfogadási és időpont-ellenőrzési szabályok változatlanok.
- A Ráérő app telepítési része elkülönített, színezett kártya saját appikonnal.
- A Naptárak menü a naptár levágó keretén kívül jelenik meg. A szabad képernyőterülethez igazítja az irányát és magasságát; belül görgethető, az összes művelet elérhető. Escape-pel és kívülre kattintva bezárható. Új naptárnézet megnyitásakor a régi lebegő panel nem marad az oldalon.
- A megosztási gomb saját menüt nyit: Link másolása, Facebook, WhatsApp, Viber, E-mail, Messenger, QR-kód. A linkmásolás kizárólag a foglalási linket másolja; az üzenetküldők a beállításokban mentett szöveget is megkapják.
- A QR-kód a meglévő helyi generátort használja, külső képszolgáltatás nélkül. PNG/SVG letöltés és nyomtatás, valamint visszalépés a megosztási menühöz.

## Megosztási sajátosságok

A Facebook a link megosztását nyitja meg, előre kitöltött üzenetet a Facebook nem garantál. A Viber megnyitásához telepített alkalmazás szükséges. A Messenger támogatott mobilon a rendszer megosztójával választható ki; asztalon a szöveg és a link másolása után a Messenger megnyílik, ott beszélgetést kell választani és beilleszteni. Automatikus üzenetküldés nem történik. A saját menüben mindig elérhető a Link másolása.

## Telepítés

Teljes forráscsomag, előállított és gzip-pelt JS/CSS fájlokkal. Új függőség vagy adatbázis-migráció nincs. A meglévő `.env`, `storage` és `vendor` megőrzendő. A korábbi kiadás: `7d6bb64f07081b2d4cf1e56ea7bd39b456eaf96e`. Az éles rendszeren nem történt telepítés.

## Ellenőrzés

Frontendegységtesztek, közös UI-primitívek, build-ellenőrzés és helyi Chromium böngészős ellenőrzés 390/750/1280 pixeles szélességgel, illetve alacsony képernyőn. Másolás és megosztási payloadok, QR-cél és PNG-letöltés, checkboxok, popupfejléc, naptárválasztó műveletei, állapotváltás és paneltakarítás. A külső alkalmazások tényleges üzenetküldése helyett tesztelt megnyitási/másolási hívások; nem küldtünk üzenetet vagy módosítottunk éles adatot.
