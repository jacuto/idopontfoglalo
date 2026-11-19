# Azonnali és időzített hírlevélküldés – 2026. november 19.

A kész kampánynál két kifejezett mód választható: Azonnali küldés és Időzített küldés. Azonnali küldéskor a felület megerősítést kér a címzettek számával; elutasításkor nem történik küldési kérés. Jóváhagyás után a gomb letilt és a kiválasztott kampány közvetlenül feldolgozásra kerül. Ismételt kérés, dupla kattintás vagy az ugyanakkor futó cron nem indíthat második átadást. A meglevő állapotgép és sorzár védi az átadást; bizonytalan eredmény nem kerül automatikus újrapróbálásra.

Időzített küldéshez dátum és idő kötelező. Az időpont UTC-ből az alkalmazás adatbázisban használt időzónájába kerül, így nem indul el a nyári/téli időeltolódás miatt idő előtt. Az időzített küldést a percenkénti Laravel scheduler indítja, tehát a küldés jellemzően a megadott percben vagy az azt követő feldolgozáskor kezdődik.

Új, legfeljebb három címzettes levél előkészítése közvetlenül elindul a mentés utáni kérésben, küldési jóváhagyás nélkül még nem küld levelet. Nagyobb lista továbbra is korlátozott adagokban készül elő a háttérben. A tényleges, jóváhagyott azonnali küldés minden előkészített kampánynál közvetlenül indul. A postaládába érkezés idejét a külső küldő és a fogadó szolgáltató szabja meg.

A szolgáltatói hibaüzenetekben megjelenik a Brevo/Mailchimp neve, HTTP-kód és a művelet. Ismert hibáknál külön magyarázat: API-kulcs, küldési keret, hitelesítés, fiókkorlátozás, címzettlista, tartalom. A külső válasz `errors` részleteit is osztályozzuk, de nyers API-választ, titkos kulcsot vagy címzettadatot nem jelenítünk meg. A már korábban eltárolt általános hiba utólag nem rekonstruálható.

27 célzott backendteszt / 236 ellenőrzés sikeres, beleértve az azonnali átadást cron nélkül, megerősítési követelményt, ismételt kérést, jövőbeli ütemezés védelmét, célzott kampányfeldolgozást és az új hibakategóriákat. Böngészőben a megerősítés megszakítása, elfogadása, a módválasztó és időpontmező, valamint 390 px-es megjelenítés ellenőrizve. Teszt közben nem ment ki valódi levél.

## Frissítés

A csomag a korábbi módosításokat is tartalmazza. Az éles `.env`, APP_KEY, adatbázis és `storage` maradjon meg.

```bash
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

Nincs új migráció ebben a részfrissítésben. A már beállított cron maradjon meg, az időzített levelekhez, nagyobb címzettlisták előkészítéséhez és emlékeztetőkhöz szükséges.
