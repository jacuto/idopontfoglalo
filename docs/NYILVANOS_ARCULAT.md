# Áttekinthető nyilvános oldalak

A vizuális irányhoz a felhasználó által megadott [Reservio főoldal](https://www.reservio.com/hu) és [árazás](https://www.reservio.com/hu/arkepzes) szolgált referenciaként: világos alap, kék kiemelések, egyszerű hierarchia és könnyen összevethető csomagok. A saját megjelenéshez saját szövegek és jelképes, helyben rajzolt SVG-naptár tartozik; más szolgáltató képei, logói, ügyfélszámai és értékelései nem kerültek át.

A korábbi generált szalon- és edzőfotók, illetve a tényleges alkalmazásról készített naptárkép kikerültek a csomagból. A szolgáltatók saját feltöltött fotóit továbbra is megjelenítjük. Saját borítókép hiányában jelképes naptár látható, mobilon ez háttérbe szorul, hogy a foglalás gyorsan elérhető legyen.

## Keresés belépés nélkül

A `/` és `/szolgaltatok` oldalon közvetlenül megnyílik a kereső. A főoldal megnyitásakor nem kell a privát `/api/state` végpontot lekérdezni, és a nyilvános oldal bejelentkezett szolgáltatóként is megnyitható. A szolgáltatói munkaterület továbbra is a `/?provider=AZONOSÍTÓ` címen érhető el.

A keresőmezők fehérek, határozott kékesszürke kerettel, külön halványkék keresőpanelen. Fókuszban erősebb kék körvonal jelenik meg. A főoldal külön jelzi, hogy a kereséshez nem szükséges belépés. Név, kategória és település szerinti keresés, helymeghatározás és közvetlen foglalás megmarad.

## Kevesebb részlet egyszerre

A szolgáltatói oldalon először négy alapvető funkció jelenik meg; a továbbiak külön gombbal nyithatók meg. A három csomag fő előnyei rögtön látszanak, az összes funkció és a külön díjas tételek lenyithatók. A meglévő díjak változatlanok: Alap 4 000 Ft/hó, Csapat és Egyedi ajánlatkéréses. Előfizetési és számlázási működés nem módosul.

## Feltöltés

Nincs adatbázismigráció, új Composer-függőség vagy `.env`-változó. A csomagból a `public/showcase.js`, `public/showcase.css`, `public/i18n.js`, `public/boot.js` és a sablon is kerüljön fel. Az előző kiadás képfájljai esetleg megmaradhatnak a tárhelyen, de ez a változat már nem hivatkozik rájuk. Az `.env` és `storage` tartalmát őrizd meg.

```bash
cd /home/digitmar/idopontfoglalo-php &&
php artisan optimize:clear &&
php artisan view:cache
```

Böngészőben Ctrl+F5. A nézetek gyorsítótárazását feltöltés után kell elvégezni.

## Belépés és teljes funkciólista

A szolgáltatói belépés középre rendezett, a közösségi belépés az e-mailes űrlap alatt, kis térközzel található. A vendégfiók belépőoldala a kék-fehér megjelenést használja, a bal oldali bevezető a jobb oldali kártyához felül igazodik.

A `/szolgaltatok` oldalon közvetlenül a kereső után jelennek meg a találatok; a kezdőoldalon továbbra is használhatók a kategóriák. Az automatikusan kiválasztott első szabad nap nem vált ki „választott nap” figyelmeztetést. Ilyen tájékoztató csak kézi napválasztás utáni sikertelen kereséskor jelenik meg.

Az Árak oldalon 71 funkció szerepel hét témakörben, külön megjelölve a központi főadmin eszközeit és a külön díjas külső szolgáltatásokat. Az összes témakör egy gombbal is megnyitható. A listát a `public/pricing-features.js` tartalmazza angol és német fordítással együtt; ezt az új fájlt is fel kell tölteni.

A vendégfiókban a fő tartalom mellett hasznos tudnivalók és nyilvános szolgáltatóajánlók jelennek meg. A számlázási település megadása esetén arra a településre szűrünk; helyadatot külön engedély nélkül nem kérünk. Mobilon az oldalsáv a fő tartalom alá kerül. A szolgáltatókereső találatai továbbra is közvetlenül a kereső után jelennek meg, a kategóriák és a vendégeknek szóló előnyök a találatok alatt. A vendégbelépő oldalon ismét látszik a meglévő illusztráció.
