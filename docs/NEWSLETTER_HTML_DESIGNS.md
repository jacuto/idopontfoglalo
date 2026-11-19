# HTML hírlevéldesignok – 2026. november 19.

Az öt sablon zöld, lila, terrakotta, palakék és királykék színvilágot kapott. Színes fejléc, hangsúlyos címsor, kiemelt információs blokkok, valódi szöveges foglalásgomb és rendezett lábléc készül. Táblázatos szerkezet és inline stílusok, továbbra is szöveges alternatívával. Nincs script, külső font vagy képként elküldött szöveg. A HTML önmagában nem garantálja a beérkező mappába kerülést.

A sablonazonosító külön adatmezőben megmarad mentéskor, előkészítéskor és másoláskor. A szerkeszthető tartalom szigorúan tisztított HTML; a design stílusait a szerver adja hozzá. A sandboxolt előnézet ugyanazt a renderert használja, mint a két külső hírlevélküldő felé átadott HTML. Előnézetkor nincs külső küldési API-hívás. A korábban kiküldött levelek megjelenése nem módosul visszamenőleg.

A szolgáltató saját foglalási címe szerveroldalon az APP_URL és az aktuális szolgáltatóazonosító alapján készül: `/foglalas?provider=ID`. A sablon betöltése automatikusan hozzáadja a gombot; nincs kézzel kitöltendő webcímmező. Meglévő levélhez külön gombbal is hozzáadható. A központi, szolgáltatóknak szóló hírlevélbe nem kerül vendégfoglalási cím.

A hírlevéllista törlésgombja az elérhető műveletek közül utolsó, minden kártyán a jobb szélre igazítva. A feldolgozás alatt álló vagy bizonytalan küldések korábbi védelme megmarad.

Telepítés a szokásos forrásfrissítéssel, az éles `.env`, APP_KEY és `storage` megőrzésével:

```bash
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

Új nullable helyett alapértékes mező: `marketing_campaigns.template_key`, alapértéke `general`. A meglévő adatok megmaradnak. Az APP_URL az éles, kívülről elérhető címet tartalmazza.

Ellenőrzés: 23 célzott backendteszt (sablonok, jogosultság, tenantlink, mentés/másolás, szolgáltatói payloadok, hírlevélfolyamat); Chromiumban mind az öt design szerveres előnézete, automatikus foglalási cím, design megőrzése, törlésgombok sorrendje és jobb széle, valamint 390 px-es megjelenítés. Külső hírlevél nem ment ki a tesztek során. Gmail/Outlook/Apple Mail valódi klienses tesztküldését az üzemeltető saját feladófiókjával lehet elvégezni.
