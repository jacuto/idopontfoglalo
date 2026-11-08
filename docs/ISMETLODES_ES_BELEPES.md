# Ismétlődés, szolgáltatói belépés és részletablakok

## Frissítés

Az `.env`, APP_KEY, adatbázis és `storage` megőrzendő. A teljes frissítést töltsd fel, beleértve a `public/followup.js`, `public/i18n.js`, stílusok és az új `2026_11_08_000012_add_provider_security.php` migráció fájljait.

```bash
cd /home/digitmar/idopontfoglalo-php &&
composer install --no-dev --prefer-dist --optimize-autoloader &&
php artisan raero:upgrade &&
php artisan raero:check
```

Böngészőben Ctrl+F5. Új környezeti változó nem kell. A főadmin közösségi belépési kulcsai és központi SMTP-je szükséges az adott funkciókhoz. Az OAuth callbackcímek változatlanok. A levelekhez és a folyamatos sorozatok bővítéséhez a meglévő percenkénti `php artisan schedule:run` cron szükséges.

## Ismétlődő foglalás

Szolgáltatói új időpontnál a kis **Ismétlődő időpont** jelölő alatt kezdőnap, opcionális végdátum, gyakoriság és opcionális darabszám adható meg. A kezdőnap átveszi az időpontnál kiválasztott napot, és külön módosítható. Az időpont órája marad. A végdátum beleszámít: az arra a napra eső alkalom is létrejön. Pozitív darabszámnál az első alkalom is beleszámít; darabszám és végdátum együtt a korábban elért feltételnél zárja a sorozatot.

**0 vagy üres darabszám + üres végdátum: folyamatos sorozat.** Az adatbázisba a rendszer egy évre előre hozza létre az alkalmakat, legfeljebb 370-et egy lépésben. A `raero:recurring` parancs naponta pótolja az újabb alkalmakat. Ez nem egy végtelen ciklus és nem egyszerre több évtizednyi adat létrehozása. Az egy évnél hosszabb véges sorozat is így folytatódik. A végső felső darabszám 5200, a 0 nem felső korlát.

Az újonnan létrehozott adag minden alkalma átesik a normál ellenőrzésen. Ütközéskor az adott adag nem mentődik részlegesen. A napi folytatás hibáját a naptár figyelmeztetése mutatja; javítás után a rendszer újrapróbálja, vagy a sorozat leállítható. A cron leállása alatt elmaradt, már múltbeli alkalmakat a rendszer nem pótolja visszamenőleg. A sorozat jövőbeli alkalmainak lemondása a további létrehozást is leállítja; múltbeli foglalások és befizetések megmaradnak. A korábbi, csak darabszámot tároló sorozatok nem válnak maguktól folyamatos sorozattá.

A havi ismétlés az eredeti napból számol, nem sodródik el a rövid hónapok miatt. A magyarországi helyi idő az irányadó; nem létező óraátállítási időpontot a rendszer jelez.

## Szolgáltatói közösségi belépés

A `/belepes` és a `/szolgaltato` csatlakozás oldal Google-, Facebook- és Apple-gombot is kapott. A gomb csak a főadmin által beállított szolgáltatóval működik. Új közösségi fióknál az azonosítás után a vállalkozás neve, települése, kategóriája és kapcsolattartó e-mailje kitölthető; jelszó nem kötelező ehhez a regisztrációhoz. A közösségi azonosítás eredménye tíz percig használható a regisztráció befejezésére.

Meglévő szolgáltatóként előbb lépj be, majd **Beállítások → Alapvető beállítások → Belépés és biztonság** alatt kapcsolj közösségi fiókot a vállalkozáshoz. Pusztán egyező e-mail-cím nem ad hozzáférést meglévő vállalkozáshoz. Egy közösségi azonosító egy vállalkozáshoz kapcsolható. A vendégként használt közösségi belépés ettől külön szerepkör marad.

## Jelszó és elfelejtett jelszó

A szolgáltatói belépésnél az **Elfelejtetted a jelszavad?** gomb a fiók e-mailjére kér visszaállítást. A link egy óráig és egyszer használható. A főadmin HTML-sablonjai között külön **Szolgáltatói jelszó-visszaállítás** sablon szerepel. A válasz nem árulja el, hogy az e-mail-cím regisztrálva van-e.

A szolgáltató alapbeállításaiban a **Jelszó megváltoztatása** kéri a jelenlegi jelszót és kétszer az új, legalább 12 karakteres jelszót. Közösségi regisztráció után a **Jelszó beállítása e-mailben** lehetőséggel állítható be első jelszó. A jelszó-változtatás a korábbi jelszavas munkameneteket érvényteleníti; a közösségi belépés továbbra is használható. Az eredeti 1-es szolgáltató új jelszavát is titkosított hashként az adatbázis tárolja, nem szükséges az `.env` fájlt módosítani. A CLI telepítőjelszó visszaállítása nem írja felül ezt az új adatbázisos jelszót.

A hagyományos vendégfiók saját profilján is van jelszóváltoztatás; az elfelejtett jelszó korábbi lehetősége megmarad. A kizárólag közösségi vendégbelépés jelszavát a közösségi szolgáltatónál lehet módosítani.

## Megjelenítés

A vendég adatlapjának fejlécében **Felhívom** gomb jelenik meg, ha van telefonszám, a kapcsolati sorban pedig az e-mail. A telefonszám nem ismétlődik. A foglalás részleteinek tetején a kezdés és befejezés is látható; az időtartamot a foglaláskor mentett szolgáltatások adják.

A kereső, csatlakozás, árak és foglalóoldal megjelenése átdolgozott: visszafogott tipográfia, kisebb kártyák, hangsúlyos kereső, díszítő naptármakett nélkül. A szolgáltató saját borítóképét továbbra is megjelenítjük.
