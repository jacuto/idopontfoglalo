# BulkGate és levélszerkesztő – 2026. november 12.

Főadmin → Központi kapcsolatok → SMS / BulkGate. A BulkGate portál Modules & APIs részében hozz létre aktív HTTP API-t; a Login credentials alatt található Application ID és Application token kerül a két mezőbe. A titkos token üresen hagyva megmarad. A rendszerfeladó (gSystem) az ajánlott alapérték, feladóérték nélkül. Egyedi név/szám/profil csak a BulkGate által engedélyezett adatokkal használható. Szükséges elegendő egyenleg és megfelelő napi limit. Mentéskor nincs SMS-küldés.

A Simple transactional API használatos, nem WhatsApp vagy Viber. A szám nemzetközi számjegyekkel megy ki; GSM-7/Unicode felismerés és hosszellenőrzés történik. A díj szegmensenként értendő, az ékezetek növelhetik a költséget. A teljes kezelési link megmarad; túl hosszú üzenetet nem csonkítunk. Hivatalos árak: https://www.bulkgate.com/en/pricing/sms/hu/hungary/

A 16-os migráció szolgáltatói referencia mezőt ad az értesítésekhez. A régi várakozó SMS-ek ellenőrzésre félretéve maradnak; a régi folyamatban lévők bizonytalan állapotba kerülnek. Az e-mailek változatlanul feldolgozhatók. A Küldő átvette állapot API-elfogadást jelent, nem igazolt kézbesítést. Bizonytalan küldést nem ismétlünk automatikusan; kézi újraküldés előtt ellenőrizd a BulkGate előzményeit, mert az újraküldés új díjat okozhat. A régi Twilio-kulcsok nem szolgálnak tartalékként.

Az Automatikus levelek oldalon Vizuális szerkesztő és HTML-kód nézet használható. Félkövér/dőlt/aláhúzott szöveg, címsor, listák, hivatkozások és sablonváltozók támogatottak. A szerver mentéskor továbbra is megtisztítja a HTML-t. Előnézetben ellenőrizhető a sablon.

A foglalási bevezető alatt ismét van Belépés / Regisztráció link. A GYIK a /gyik címen érhető el; a /sugo kompatibilis marad. A négy lábléchivatkozás a copyright felett jelenik meg.

Frissítés az alkalmazás könyvtárában: `php artisan optimize:clear`, `php artisan raero:upgrade`, `php artisan raero:check`. Az .env, APP_KEY, adatbázis és storage megőrzendő. A migráció előtt legyen biztonsági mentés.

Ellenőrzés: szimulált API-válaszok, kulcsvédelem, bizonytalan küldés, kézi újraindítás, migráció, vendégmeghívás és böngészős felületi próba. Éles SMS-küldés nem történt.
