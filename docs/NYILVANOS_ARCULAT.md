# Fotós, kontrasztos nyilvános megjelenés

A főoldal/kereső, szolgáltatói csatlakozás, árak, kapcsolat és vendégfoglaló oldal egységes, sötétzöld–krém–réz arculatot kapott. A kereső fehér, árnyékos panelként emelkedik ki, a fő műveletek meleg színű gombokat kaptak. A kártyákhoz és képekhez finom rámutatási effekt tartozik; a csökkentett mozgást kérő böngészőbeállítást tiszteletben tartjuk.

A `salon-editorial.webp` és `training-editorial.webp` erre a bemutatóra generált marketing-hangulatképek. Nem valós szolgáltatói referenciafotók vagy ügyfélajánlások. A `product-calendar.webp` a rendszer tényleges naptárának képernyőképe kizárólag helyben létrehozott, nem mentett mintaadatokkal. A képek helyi WebP-fájlok, megjelenítésükhöz nem kell külső képoldalt felkeresni. A szolgáltató saját borítóképét nem írjuk felül: ha nincs saját kép, a foglalóoldal külön jelölt hangulatképet mutat.

A működő kereső, regisztrációs és foglalási űrlapok eseménykezelői megmaradnak. Nincs új adatbázismigráció, Composer-függőség vagy `.env`-változó. Nincsenek kitalált ügyfélszámok, értékelések vagy garanciák a bemutatóban.

Frissítéshez a teljes csomagból a `resources/views/app.blade.php`, `public/showcase.js`, `public/showcase.css`, `public/i18n.js` és a három új `public/images/*.webp` fájl is kerüljön fel. A lap sablonja fájlmódosítási idővel verziózza a stílust és a JavaScriptet. Feltöltés után:

```bash
cd /home/digitmar/idopontfoglalo-php &&
php artisan optimize:clear &&
php artisan view:cache
```

Böngészőben Ctrl+F5. Az `.env` és `storage` tartalmát továbbra is őrizd meg.
