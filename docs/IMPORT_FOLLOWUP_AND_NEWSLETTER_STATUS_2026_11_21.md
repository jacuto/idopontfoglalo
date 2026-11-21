# Import, Google Naptár és hírlevélállapot – 2026. november 21.

## Rövidített importfelület

Egyetlen Hozd át a meglévő vendégeidet! értesítő helyettesíti a telefonos és postafiókos értesítőt. Az × és Később megcsinálom hét napra rejti el; a Nincs importálható vendéglistám tartósan kikapcsolja. A régi forrás szerinti elrejtéseket és sikeres importokat figyelembe vesszük. A Vendégek → Importálás menüpont mindig megmarad.

Számítógépen a telefonos forrás nem látszik. Mobilon rövid szöveg és gombok maradtak; a részletes adatkezelési tájékoztató ⓘ ikonon egérrel, billentyűzettel vagy koppintással érhető el. A központi OAuth technikai magyarázata eltűnt az ügyfélfolyamatból. A Főadmin → Kapcsolatok mezői megmutatják a pontos visszatérési címeket. A bezáró ikonok SVG-geometriával középre kerültek.

Importált telefon: +36/0036 → 06, szóközök és elválasztó jelek eltávolítása, külföldi országkód megőrzése. Betűk, mellékek és összekevert számok nem találgatással kerülnek át: ellenőrizendőként jelezzük őket. Meglévő adatot nem írunk felül.

Az Üzenet az üzemeltetőnek a felső gombsor helyett a lábléc kis hivatkozásai közé került. Ez továbbra is csak a megfelelő bejelentkezett szolgáltatói felületen jelenik meg.

## Google Naptár egyszeri import

Vendégek → Importálás → Meglévő időpontok Google Naptárból. Külön, csak olvasási OAuth engedélyezés; nem kapcsol be tartós vagy kétirányú szinkronizálást. A Google Calendar API legyen engedélyezve ugyanabban a projektben, amelyhez a névjegyeknél használt Google-kulcs tartozik. Engedélyezett Web redirect URI:

`https://SAJAT-DOMAIN/auth/calendar-import/callback`

Ez a névjegyimport és a meglévő belépési/naptárkapcsolati címek mellé kerül. Kért hatókörök: calendar.calendarlist.readonly és calendar.events.readonly. A Google hozzájárulási képernyőjén a hatóköröket, szükséges tesztfelhasználókat és esetleges ellenőrzést is be kell állítani. Éles Google-fiókkal a külső konfiguráció után ellenőrizhető; a fejlesztői ellenőrzés helyettesített válaszokkal történt.

A szolgáltató kiválaszt egy naptárt és legfeljebb 62 napot. Legfeljebb 2000 esemény, 10-es előnézeti lapozással. Ismétlődő események konkrét előfordulásai jönnek át. Lemondott, szabadként jelölt és már Ráérőből kiírt eseményeket kihagyjuk. Egész napos eseményeknél a Google kizáró végdátumát és időzónáját használjuk; többnapos események napokra bontva kerülnek át. A kért időszak határain kívül nem hozunk létre foglaltságot.

Alapértelmezés a foglalt idősáv, vendéglétrehozás nélkül. Külön választható rendes foglalás, aktív munkatárssal és egyéni szolgáltatással; a szolgáltatás időtartamának egyeznie kell az eseménnyel. Eltérő időtartamú, egész napos vagy csoportos eseménynél foglalt idősávot kell választani. A normál jogosultság-, nyitvatartás-, kapacitás- és ütközésellenőrzések érvényesek. A kiválasztott esemény saját Google-foglaltsága nem akadályozza önmaga importját, más külső foglaltság viszont igen.

Az esemény címe névjavaslat, nem igazolt név. A leírásból egyértelmű telefon és e-mail, a meghívottak közül egyetlen nem saját/szervező/erőforrás e-mail javasolható. Több jelölt esetén kézi ellenőrzést kérünk. Vendégadatlap csak az Ellenőriztem, vendégadatlapként is átveszem kijelöléssel készül vagy egészül ki. Az Átvétel ellenőrzése még nem ment; megmutatja az egyezéseket és a változtatások számát. Ezután külön Kijelöltek átvétele ment. A mentés zárolt állapotban újra ellenőriz; hiba esetén a teljes kérés visszagörgetődik.

Nincs automatikus fiókösszekapcsolás, meghívó, visszaigazolás vagy hírlevélküldés az importból. A normál későbbi kézi foglaláskezelésre a meglévő rendszer működése érvényes. A naptárból importált foglalásokat nem írjuk vissza Google-be, mert az másolatot hozna létre; későbbi módosításukat a forrásnaptárban külön kell kezelni. Ugyanazon naptári esemény azonos munkatárshoz ismételten nem importálódik. Az ideiglenes tokenek és előnézetek titkosított gyorsítótárban 20 percig érhetők el, a meglévő importtakarítás eltávolítja a lejártakat. Nem foglalható idősávból a meglévő 500-as korlát marad.

## Késleltetett hírlevelek

Új migráció tárolja a külső állapotot, az ellenőrzés időpontját és külön ellenőrzési hibát. A bizonytalan küldési kimenetel nem egyenlő végleges elutasítással. A kezelőfelület ilyen esetben ezt mondja el, és figyelmeztet az újraküldés elkerülésére.

A raero:newsletter-status parancs a meglévő schedule:run cronból percenként fut; legfeljebb 10 kampányt ellenőriz, kampányonként legalább két perc elteltével. Az elmúlt hét nap nem törölt, külső azonosítóval rendelkező unknown/submitted/blocked kampányait figyeli. Kizárólag GET állapotlekérdezést végez, soha nem küld újra. Küldőfiók-váltás esetén az eredeti fiók nélkül nem találgatja az állapotot.

Mailchimp sending/sent, illetve megfelelő Brevo állapot esetén a korábbi hiba törlődik és az állapot frissül. A sent végállapot nem lép vissza egy későn beérkező sending válasz miatt. Piszkozatot jelentő külső állapot nem válik sikeressé. Lekérdezési hiba nem lesz új küldési hiba, és később ismét ellenőrizzük. Az ismeretlen és átadott kampány megnyitott szerkesztője 30 másodpercenként ellenőriz; az automatikus frissítés a lap elhagyásakor leáll. Külső kampányazonosító nélkül nem állítunk igazolható távoli eredményt. A küldő szerinti sent nem egyéni kézbesítési igazolás.

Telepítés után a meglévő cron marad. Kézi ellenőrzés:

```
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
php artisan raero:newsletter-status
```

## Ellenőrzés

Teljes PHP-tesztcsomag: 356 teszt, 2633 állítás, négy MySQL-környezetet igénylő kihagyás. Külön ellenőrzés: normalizálás, közös elrejtés/tartós kikapcsolás, naptárengedély/OAuth-state, teljes API előnézet és ismételt mentés, idősáv/foglalás, egész napos DST-határ, önmagával való Google-foglaltság kezelése, más esemény ütközése, 20 perc késéssel sikeressé váló Mailchimp-kampány újraküldés nélkül. Böngésző: számítógép/mobil, rövid felület, hover/tap információ, lábléchivatkozás, tartós kikapcsolás és menüelérés, naptár 10-es lapozás és kétlépéses mentés, automatikus hírlevélállapot-frissítés.
