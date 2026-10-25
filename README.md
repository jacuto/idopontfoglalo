# Ráérő — időpontfoglaló, első változat

Magyar nyelvű, mobilon is használható webalkalmazás szalonoknak. Ez egy **egyetlen szalon kipróbálására készült prototípus**, nem a kész, több vállalkozást kiszolgáló előfizetéses szolgáltatás.

## Indítás

Node.js **24 vagy újabb** szükséges. Nincs telepítendő külső futásidejű függőség.

```sh
cd /workspace/idopontfoglalo
npm start
```

Alapértelmezett port: **3000**, felülírható a `PORT` változóval. Szolgáltatói felület: `/`, vendégfoglalás: `/foglalas`, egyedi foglaláskezelés: `/modositas?token=…`.

Az első indítás létrehoz egy véletlen szolgáltatói jelszót a `data/admin-password` helyen. Ezt helyben olvasd ki a belépéshez; ne tedd verziókezelésbe vagy megosztott naplóba. Saját jelszó az `ADMIN_PASSWORD` környezeti változóval adható meg. Újraindításkor újra be kell jelentkezni.

Az adatok SQLite-adatbázisban, a `data/app.db` fájlban maradnak meg. A `DATA_DIR` változóval más tárolókönyvtár választható. Mentéshez állítsd le a folyamatot, majd mentsd a teljes adatkönyvtárat. A szerver egyszerre egy folyamatban fusson.

## Kipróbálható funkciók

- Heti naptár, munkatársszűrés, gyors időpontfelvitel üres cellára kattintva.
- Foglalás részletei, időpontváltoztatás, jóváhagyás, elutasítás, lemondás.
- Munkatársak felvétele és szolgáltatáshoz rendelése.
- Szolgáltatások felvétele és szerkesztése: ár, időtartam, munkatársak, automatikus megerősítés.
- Háromszakaszos kezelések: munka / szabad hatóidő / munka; hatóidőben megengedett másik vendég.
- Több szolgáltatás egy foglalásban, ugyanannál a munkatársnál, egymás után.
- Vendégoldali szabadidőpont-választás, konkrét szakember vagy „Bárki megfelel”. Egy elérhető szakembernél rejtett a választó.
- Vendégek mentése, név- és telefonszám-alapú keresés/autokitöltés a szolgáltatónál.
- CSV-import és -export (`name;phone;email` fejléc, idézőjelezett mezők támogatásával).
- Véletlen, titkos hivatkozással vendég általi módosítás és lemondás, fiók nélkül.
- Foglalási események oldala az aktuális állapotokkal; kézi frissítés.
- Napi nyitási és zárási idő, jóváhagyási szabály és emlékeztető-preferenciák mentése.
- Beágyazókód a beállításokban. A befogadó weboldalnak el kell érnie a telepített alkalmazást.
- Jelszavas szolgáltatói belépés; a nyilvános katalógus nem ad vissza ügyféltörzset vagy mások foglalásait.

A szabad időpontok számítása `Europe/Budapest` időzónában történik. A szolgáltatói naptár és a kézi dátummezők a böngésző helyi időzónáját használják, ezért ebben a változatban magyarországi időzónájú böngészővel használd.

A bemutató szalon neve és a szolgáltatásadatok a beállításokban átírhatók. Nincsenek előre létrehozott vendégek vagy foglalások.

## Még nincs élesítve / elkészítve

- E-mail, SMS, meghívók, emlékeztető-küldés: a preferenciák mentése működik, üzenetet nem küld.
- Stripe, átutalás-egyeztetés, Billingo, Számlázz.hu: nincs fizetés vagy számlakiállítás. Az előfizetési oldal a tervezett árat mutatja, nem aktív előfizetést.
- 4 000 Ft/hó, egy munkatárs/egy naptár, két hónap próba: üzleti tervként megjelenik, de a csomagkorlátozás és próbaidő-nyilvántartás még nincs implementálva. Több munkatárs most kipróbálható.
- Több vállalkozás regisztrációja, saját aldomain, szerepkörök, vendégfiókok és jelszó-visszaállítás.
- Szakmánként külön munkafolyamat, heti munkarend, szabadság, szünet, pufferidő, lemondási határidő.
- Módosítási kérésnél az eredeti időpont megtartása: jelenleg a módosítás közvetlenül átírja az időpontot, és szükség esetén új jóváhagyásra állítja.
- Teljes auditnapló, automatikus szolgáltatói értesítés és böngészőn kívüli push.
- Éles üzemeltetéshez HTTPS, jogosultsági és adatvédelmi felülvizsgálat, visszaélésvédelem, automatikus mentés és a fenti integrációk fejlesztése szükséges. Valós vendégadat helyett tesztadatokkal próbáld ki.

## Ellenőrzés

```sh
npm test
```

A tesztek külön ideiglenes adatbázison futnak, nem módosítják a bemutató adatait. Ellenőrzik a hozzáférésvédelmet, a jóváhagyást, az ütközést, a szabad időpontokat, a módosítást/lemondást, a hatóidős párhuzamosságot és a több szolgáltatásos automatikus megerősítést.

## Tárhely.eu / cPanel Passenger

A `server.js` támogatja a Passenger által `require()`-rel történő betöltést is. Az alkalmazás root a tárhely saját könyvtárához viszonyított útvonal, például `idopontfoglalo-app` (nem URL). Közvetlenül ebben legyen a `server.js`, a `package.json` és a `public` mappa. A könyvtár legyen a nyilvános dokumentumgyökéren kívül.

- Node.js: 24 vagy újabb; mód: Production.
- Indítófájl: `server.js`.
- URL: a külön létrehozott aldomain, például `foglalo.digitmarketing.hu`, üres útvonallal.
- Környezeti változók: saját `ADMIN_PASSWORD`, valamint `TZ=Europe/Budapest`.
- Feltöltés vagy frissítés után Mentés, majd Restart szükséges. Nincs telepítendő külső NPM-függőség.
- „It works!”: a szolgáltató tesztalkalmazása fut; ellenőrizd a rootot és az indítófájlt.
- Elhúzódó betöltés: ellenőrizd a Passenger-naplót. A korai változat Passenger alatt nem kezdett figyelni; a friss `server.js` ezt javítja.

A Passenger indítási módját automatizált szimuláció ellenőrzi; ez nem helyettesíti a tárhelyen végzett ellenőrzést.
