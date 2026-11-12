# Nyilvános tájékoztatók és külön főadminoldalak

## Nyilvános címek

- `/adatvedelem`: adatkezelési tájékoztató, külön üzemeltetői és szolgáltatói szerepekkel.
- `/adattorles`: fiók- és közösségibelépési adatok törlésének kérése. Meta „Data deletion instructions URL” mezőjébe ez a cím való, nem egy callback.
- `/aszf`: szolgáltatói szerződési feltételek; alapból magánszemély is előfizethet. Fogyasztói elállás/felmondás és panaszkezelés is szerepel.
- `/sugo`: kereshető, témák szerint rendezett útmutató; többek között 95 perces hajfestés 30/35/30 perces szakaszokkal, párhuzamos/csoportos foglalás, beosztás, távollét, kupon, bérlet, pénzügyek és jogosultságok.

Az oldalak szerveroldali HTML-ként, belépés és JavaScript nélkül is olvashatók; a súgó keresése külön, kisméretű szkript. Az app és ezek az oldalak közös láblécet használnak. A szövegek magyar nyelvűek.

## Kitöltendő valós adatok

A főadmin **Üzemeltető adatai** része adja a nyilvános kibocsátói nevet/címet, adószámot és nyilvántartási számot. A **Jogi oldalak adatai** részen add meg az elérhető adatvédelmi/ügyfélszolgálati e-mailt, a tárhelyszolgáltató nevét/címét és a tényleges adatmegőrzési szabályokat. Ezek a `platform_settings` rekord meglévő adatainak megtartásával, külön `legal` kulcs alatt tárolódnak. A szöveges értékeket a nézetek HTML-kódolják; a publikus dokumentum nem kap API-kulcsot, banki adatot vagy főadminjogosultságot.

A dokumentumok a megvalósított működéshez igazított kiinduló szövegek. Éles használat előtt ellenőrizni kell a valós adatfeldolgozókat, külföldi adattovábbítást, megőrzést, mentést és fogyasztói feltételeket. A tájékoztató átírása önmagában nem állít be technikai törlési feladatot, és nem helyettesít adatfeldolgozói megállapodást vagy egyedi jogi ellenőrzést. Az adattörlés itt kérelmezési útmutató, nem ellenőrzés nélküli azonnali fióktörlő végpont.

A tartalom alapjául szolgáló hivatalos jogforrások:

- GDPR: https://eur-lex.europa.eu/legal-content/HU/TXT/?uri=CELEX:32016R0679
- Fogyasztói távollévők közötti szerződések: https://njt.jog.gov.hu/jogszabaly/2014-45-20-22
- Fogyasztóvédelem: https://njt.jog.gov.hu/jogszabaly/1997-155-00-00
- A számviteli törvény hatálya alá tartozó bizonylatmegőrzés: https://njt.jog.gov.hu/jogszabaly/2000-100-00-00

## Főadmin navigáció

A kapcsolatok, számlák, kézi számlarögzítés/egyeztetés, levélsablonok, belépési engedélyek, hírlevelek, üzenetek és jogi adatok a `/foadmin/...` alatti saját URL-en, az oldalsáv megtartásával jelennek meg. Nem használják a foglalásokhoz fenntartott modális ablakot. Közvetlen megnyitáskor és frissítéskor is főadminbelépés szükséges. A böngésző előre/vissza navigációja működik. A foglalási és vendégadat-szerkesztő párbeszédablakok megmaradnak.

## Kapcsolók és Stripe

A központi kapcsolatoknál külön Google-, Facebook-, Apple-belépési és Billingo-számlakiállítási kapcsoló van. A korábban működő rendszerekhez alapból engedélyezettek; kezdeti kézi számlázáshoz vedd ki a Billingo pipáját és ments. Kikapcsolás nem törli a kulcsokat. A tiltás nemcsak a gombokra vonatkozik: a szerveroldali belépés/számlakiállítás sem kerülhető meg közvetlen API-hívással.

A Google-belépés kapcsolója nem vonja vissza a külön engedélyezett Google Naptár kapcsolatokat. Billingo kikapcsolásakor sem a Stripe fizetését, sem az API-kulcsát, sem a meglévő Stripe-előfizetést nem módosítjuk. A Billingo tiltása a szolgáltatói Billingo-számlázásra és az automatikus munkára is vonatkozik; korábbi számlák PDF-je és a kézi előfizetésiszámla-nyilvántartás tovább működik. A Stripe saját fizetési nyilvántartása nem Billingo-számla.

## Ingyenesség és hozzáférés

Szolgáltatók → **Előfizetés és ingyenesség**:

- **Bekapcsolva**: a normál előfizetési szabályok és a korábbi jogos tartozások érvényesek.
- **Korlátlan ideig ingyenes**: lejárat nélküli, Pro-funkciókat is engedő helyi hozzáférés; a naptárkeret külön korlát marad. Nincs mesterségesen távoli lejárati dátum.
- **Kikapcsolva**: naptár és új foglalás szünetel, a kapcsolattartás és rendezés elérhető marad.

A módosítások szolgáltatói zárolás alatt történnek, állapot- és időszakelőzménnyel. A korábbi kifizetett tételek, befizetések és számlák nem törlődnek. A díjmentes/szünetelő időszak alatt nem keletkezik új helyi havidíj; visszakapcsoláskor sem pótoljuk ezeket utólag. Átfedő, még ki nem fizetett havi tétel díjmentesített jelölést kap, az eredeti összege és az ok megmarad. A már korábban lejárt, nem érintett időszak tartozását ez nem törli.

Ezek helyi hozzáférési döntések, nem Stripe-módosítások. Ingyenesség előtt meglévő Stripe-előfizetésnél csak GET-tel ellenőrizzük, hogy már nem terhel-e automatikusan; élő, tovább terhelő előfizetés mellett a rendszer elutasítja az ingyenesség mentését. A főadminnak a Stripe-ban külön kell rendeznie a további terhelést. A helyi szüneteltetés önmagában nem állít le banki terhelést; ezt a kezelőfelület kifejezetten jelzi. Új fizetős checkout és csomag-/naptárvásárlás ingyenes vagy szünetelő fióknál nem indítható; a keretet a főadmin állítja.

## Frissítés és ellenőrzés

Nincs új Composer-függőség vagy új migráció ebben a változatban. Az új nézeteket, közös láblécet, `help.js` fájlt és a meglévő módosított fájlokat együtt kell feltölteni; `.env`, APP_KEY és `storage` megtartandó.

```bash
cd /home/digitmar/idopontfoglalo-php &&
php artisan optimize:clear &&
php artisan raero:upgrade &&
php artisan raero:check
```

Ctrl+F5 után töltsd ki a jogi adatok hiányzó mezőit, és ellenőrizd a négy nyilvános URL-t. Az `adatvedelem`, `aszf` és `adattorles` cím ekkor használható a Meta megfelelő mezőiben.
