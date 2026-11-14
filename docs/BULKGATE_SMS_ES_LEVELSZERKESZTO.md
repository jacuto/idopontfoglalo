# BulkGate és levélszerkesztő – 2026. november 12.

Főadmin → Központi kapcsolatok → SMS / BulkGate. A BulkGate portál Modules & APIs részében hozz létre aktív HTTP API-t; a Login credentials alatt található Application ID és Application token kerül a két mezőbe. A titkos token üresen hagyva megmarad. A rendszerfeladó (gSystem) az ajánlott alapérték, feladóérték nélkül. Egyedi név/szám/profil csak a BulkGate által engedélyezett adatokkal használható. Szükséges elegendő egyenleg és megfelelő napi limit. Mentéskor nincs SMS-küldés.

A Simple transactional API használatos, nem WhatsApp vagy Viber. A szám nemzetközi számjegyekkel megy ki; GSM-7/Unicode felismerés és hosszellenőrzés történik. A díj szegmensenként értendő, az ékezetek növelhetik a költséget. A teljes kezelési link megmarad; túl hosszú üzenetet nem csonkítunk. Hivatalos árak: https://www.bulkgate.com/en/pricing/sms/hu/hungary/

A 16-os migráció szolgáltatói referencia mezőt ad az értesítésekhez. A régi várakozó SMS-ek ellenőrzésre félretéve maradnak; a régi folyamatban lévők bizonytalan állapotba kerülnek. Az e-mailek változatlanul feldolgozhatók. A Küldő átvette állapot API-elfogadást jelent, nem igazolt kézbesítést. Bizonytalan küldést nem ismétlünk automatikusan; kézi újraküldés előtt ellenőrizd a BulkGate előzményeit, mert az újraküldés új díjat okozhat. A régi Twilio-kulcsok nem szolgálnak tartalékként.

Az Automatikus levelek oldalon Vizuális szerkesztő és HTML-kód nézet használható. Félkövér/dőlt/aláhúzott szöveg, címsor, listák, hivatkozások és sablonváltozók támogatottak. A szerver mentéskor továbbra is megtisztítja a HTML-t. Előnézetben ellenőrizhető a sablon.

A foglalási bevezető alatt ismét van Belépés / Regisztráció link. A GYIK a /gyik címen érhető el; a /sugo kompatibilis marad. A négy lábléchivatkozás a copyright felett jelenik meg.

Frissítés az alkalmazás könyvtárában: `php artisan optimize:clear`, `php artisan raero:upgrade`, `php artisan raero:check`. Az .env, APP_KEY, adatbázis és storage megőrzendő. A migráció előtt legyen biztonsági mentés.

Ellenőrzés: szimulált API-válaszok, kulcsvédelem, bizonytalan küldés, kézi újraindítás, migráció, vendégmeghívás és böngészős felületi próba. Éles SMS-küldés nem történt.

## Értesítések szétválasztása – 2026. november 14.

Beállítások → Alapvető beállítások → Értesítések. A kiemelt Pro emlékeztetőnél az időzítés és az e-mail/SMS egymástól függetlenül kapcsolható. A További értesítések alapból csukott részében külön vendég- és szolgáltatói sorok vannak új foglalásra, elfogadásra, módosításra, lemondásra, csoportos elmaradásra és meg nem jelenésre. Két üres pipa kikapcsolt értesítést jelent. Az automatikusan megerősített új foglalás egy létrehozási visszaigazolást kap, nem külön elfogadási üzenetet is. A szolgáltatói SMS a megadott értesítési számra, ennek hiányában az adott munkatárs számára megy; nem kap vendégkezelési tokent.

A régi kapcsolókhoz tartozó bekapcsolások megmaradnak, amíg az új űrlap mentése meg nem történik. Az új mátrix minden csatornát külön tárol, és a küldési sor feldolgozásakor újból ellenőrizzük. Az emlékeztető módosítása új verziót készít és újraütemezi a jövőbeli, megerősített időpontok emlékeztetőit. Az elavult sor nem küldhető ki. Pro nélkül az emlékeztető sem az API-n, sem a küldőben nem aktiválható.

Az SMS-ár rövid összefoglalója lenyitható részleteket és BulkGate-hivatkozásokat tartalmaz. A 2026.11.14-én ellenőrzött standard gSystem magyar ár 0,033–0,0396 EUR + áfa / szegmens (Telekom 0,033; Yettel 0,038; One / DIGI / UPC 0,0396). A 400 Ft/EUR csak szemléltető átváltás, nem rögzített díj vagy élő árfolyam. Gazdaságos útvonal, feladó és feltöltés módosíthatja a tényleges árat. Hosszú vagy Unicode SMS több díjazott szegmens lehet. Az ár nem jelent új automatikus terhelést a Ráérőben.

Az Egyszeri regisztrációs meghívó SMS-ben kapcsoló alapból ki van kapcsolva. Bekapcsolva új vendégfelvitel és új foglalás indíthat meghívót, ha nincs regisztrált fiók az elérhetőséghez. Nem járja végig a régi névjegyzéket. A szalonon és vendégen / normalizált elérhetőségen alapuló adatbázis-egyediségi védelem miatt nem ismétlődik minden foglaláskor, lejáratkor és ki-be kapcsoláskor sem. A link 30 napos; a küldés előtti kikapcsolás vagy közben létrejött regisztráció leállítja a meghívót. Az e-mailes meghívó külön, lenyitható kiegészítő beállítás marad.

A munkatársak három gombja egy sorba rendeződik. A foglalópanel saját világoskék háttérrel és hangsúlyos kerettel jelenik meg. Google-térkép teljes irányítószám, település és utca/házszám esetén töltődik, a korábbi teljes címmezőt is támogatva; hiányos utcanév alapján nem készül pontatlan térkép. A Statisztika munkatárs / naptár szűrőt és munkatársankénti összehasonlítást kapott. A naptár ebben a rendszerben munkatárshoz tartozik. Az Excel ugyanazt a szűrőt használja és külön Munkatársak lapot tartalmaz. A vendégkereső először foglalásszám, majd legutóbbi foglalás szerint rendez; a lemondott/elutasított foglalásokat nem számolja. A munkatársi jogosultság csak a saját adatokon belül enged keresést.

Nincs új adatbázis-migráció ehhez a részhez; a szokásos adatmegőrző frissítés futtatható. Szimulált tesztek és helyi böngészős próba ellenőrzi a működést; éles SMS-küldés nem történt.
