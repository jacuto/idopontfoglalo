# Pontgyűjtés – 2026. november 20.

## Használat

Alapból kikapcsolva. Beállítások → Alapbeállítások → Használt funkciók → Pontgyűjtés. Itt be-/kikapcsolható és megnyithatók a részletes beállítások.

Két szabály választható:

- Látogatásonként fix pont, például 1 pont minden rendezett látogatásért.
- Elköltött összeg alapján, például minden 1000 kifizetett Ft után 1 pont, lefelé kerekítve.

Legfeljebb 20 jutalom állítható be, megadott pontköltséggel és forintkedvezménnyel. Példa: 5 pontért 2000 Ft. Jutalom korlátozható szolgáltatásokra; üres kiválasztás minden szolgáltatást jelent. Kuponnal és bérlettel nem vonható össze. A kedvezmény nem haladja meg a szolgáltatás árát. A jutalmak külön inaktiválhatók vagy eltávolíthatók, a már lefoglalt beváltás változatlan marad.

A szolgáltató a vendégadatlapján látja a pontokat és az előzményeket. Saját foglalásnál, meglévő vendég kiválasztása után pontjutalmat is választhat. A vendégfiókban szintén megjelenik az egyenleg; a foglalóoldalon bejelentkezett, ellenőrzött e-mail-című, az adatlaphoz összekapcsolt vendég válthat be. A gyűjtéshez nincs szükség vendégfiókra, a szolgáltató névjegyzékében szereplő vendéghez kötjük a pontot. Azonos vendégfiókhoz tartozó saját névjegyek egyenlege ugyanazon szolgáltatón belül összeadódik; más szolgáltató egyenlegével nem.

## Jogosultság és elszámolás

Pont csak megerősített foglalásnál, igazolt megjelenés, elérkezett foglalási idő és teljes rendezett fizetés után jár. Előleg önmagában nem elég. Ingyenes, bérletes, meg nem jelent vagy pontjutalommal kedvezményes látogatás nem ad pontot. Kuponos látogatásnál a ténylegesen fizetendő, kedvezményes összeg számít.

A program bekapcsolásának időszakai rögzítve vannak; nincs automatikus visszamenőleges pontozás a korábbi vagy kikapcsolás alatt történt látogatásokra. A szabály egy foglalás első jóváírásakor rögzül, így egy későbbi szabályváltás nem írja át a korábbi pontokat. Az új szabály a későbbi jóváírásokra vonatkozik.

Az egyenleg append-only pontmozgásokból áll össze a szolgáltató saját, tranzakciósan zárolt szalonállapotában. Újrapróbálás és ismételt feldolgozás nem ad kétszer pontot. A pontok ebben a verzióban nem járnak le; nincs kézi egyenlegfelülírás, pontszint vagy pénzre váltás.

Beváltáskor azonnal lefoglaljuk/levonjuk a pontot, így párhuzamos foglalásokkal sem költhető el kétszer. Lemondás, elutasítás, meg nem jelenés vagy törlés visszaadja a lefoglalt pontot. Visszaállítás csak elegendő pont és bekapcsolt funkció esetén lehetséges. A jutalom pillanatképe megmarad, és az ár módosulásakor a kedvezmény a friss árig korlátozódik. Ingyenessé változó foglalás visszaadja a pontot. Az eredetileg korlátozott szolgáltatáskör utólagos szerkesztéssel sem kerülhető meg.

Fizetésmódosítás, visszatérítés, időpontáthelyezés, lemondás vagy törlés a korábban jóváírt pontokat is korrigálja. Összeg alapú pontozásnál részleges visszatérítés a megtartott kifizetett összeghez igazít; látogatás alapúnál a teljes rendezés feltételének megszűnése visszavonja a látogatás pontját. Ha a pontot már elköltötték, a visszavonás negatív egyenleget eredményezhet. Ez látható, az új jóváírások rendezik, és addig nincs új beváltás.

Kikapcsoláskor nincs új gyűjtés vagy beváltás, az egyenlegek és előzmények megmaradnak. A korábbi elszámolások korrekciója és a lemondott foglalások pontjainak visszaadása továbbra is működik. A már lefoglalt jutalom kedvezménye megmarad.

## Technikai és telepítési részletek

Új végpontok: `/api/loyalty` (tulajdonosi GET/PUT), `/api/clients/{id}/loyalty` (tulajdonosi egyenleg), `/api/guest/loyalty` (kizárólag saját fiók). Személyzeti szerkesztő nem változtathat pontszabályt és nem válthat be tulajdonosi joggal. A nyilvános `/api/quote` nem módosít egyenleget; a tényleges foglalás a szalon zárolt tranzakciójában ismét ellenőriz és foglal.

Nincs új adatbázistábla/migráció. Az új állapotrész a meglévő `salon_state` rekordba kerül, a korábbi foglalásokat és pénzügyi adatokat megőrizzük. A pontkorrekció a meglévő automatikus fizetésrögzítés és előleg-elszámolás után fut; a kedvezményt ezek előtt számoljuk. A percenkénti `raero:loyalty` feladat az időközben elérkezett látogatásokat is rendezi, a meglévő `schedule:run` cron használatával.

Telepítéskor az éles `.env`, APP_KEY, adatbázis és `storage` megőrzendő:

```bash
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

A korábban beállított cron maradjon meg, ne duplikáld. A csomag a hírlevél küldési/kézbesítési késéséről szóló tájékoztatást is tartalmazza. Az elvetett Mailchimp-debug nincs benne.

## Ellenőrzés

Teljes futás: 328 teszt, 2418 ellenőrzés, 4 MySQL-specifikus kihagyás, hiba nélkül. Ezután kiegészített célzott futás: 11 pontgyűjtési teszt / 60 ellenőrzés, valamint összesen 24 kapcsolódó teszt / 150 ellenőrzés. Vizsgált esetek: hiányzó fizetés, jövőbeni vagy nem igazolt látogatás, dupla feldolgozás, refund, törlés, kikapcsolt időszak, szabálypillanatkép, negatív egyenleg, foglalás és lemondás, elégtelen pontú visszaállítás, kupon-/szolgáltatáskorlátozás, más szolgáltató és más vendég védelme, ellenőrizetlen fiók, tényleges vendégfoglalás.

Böngésző: valós helyi konfigurációmentés, szabályváltás, jutalom létrehozása, automatikus egyenleg, vendégadatlap és előzmények, szolgáltatói beváltás, kikapcsolt felület és 390 px mobil. Nyilvános foglalóoldalon vendégadatot és árajánlatot helyettesítő fixture-rel a pontválasztó és az elküldött árajánlatadatok is ellenőrizve; az autentikációt és valós beváltást backendtesztek fedik le. Csak helyi mintaadat módosult, valódi küldés vagy bankkártyás terhelés nem történt.
