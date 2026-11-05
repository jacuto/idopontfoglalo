# Napi használat és előfizetés – további módosítások

A `df7ad4160a3f284e7a2bb5853062fec11c3a1c7c` változatra épülő fejlesztés, a meglévő Laravel alkalmazásban. Az éles tárhelyet és adatbázist nem módosítottuk.

## Egyszerűbb felület

- A „Kedvezmény vagy bérlet” rész csak akkor jelenik meg, ha a szolgáltatónál van aktuális aktív kupon vagy aktív bérlet / még használható kiadott bérlet. Ha csak kupon van, csak a kuponmező jelenik meg; ha csak bérlet, csak a bérletválasztó. A nem nyilvános kupon kódját a nyilvános API továbbra sem adja ki.
- A nyilvános foglalási sorrend: **szolgáltatás → nap → időpont**. A szolgáltatás előtt a napmező rejtett, a nap kiválasztása előtt az időpontok rejtettek, és nincs időpontlekérés. Szolgáltatásváltás törli a korábbi nap-/időpontválasztást. Ez az iframe nélküli alapmodulban is működik.
- A mérési hozzájáruláskérő és a módosítógomb eltűnt. **Mérés sem indul**: nincs Analytics-, GTM-, Ads- vagy Meta Pixel-betöltés/foglalási esemény. A korábbi tárolt azonosítókat nem töröltük, de nem adjuk ki őket a nyilvános foglaló beállításaiban, és ebben a kiadásban nem használjuk őket.
- A vállalkozás neve egyszer szerepel az oldalsávban, a beállításokra vezető alsó részen. A felső ismétlés megszűnt. Mobilon a navigáció alatt található ugyanaz a beállításgomb.
- A Kuponok és bérletek, valamint a Nyilvános adatlap a **Beállítások → További beállítások** részben érhető el. Kikerültek a napi menüből.
- A Foglalási események menüszöveg nem törik két sorba.
- A három naptári összesítő alacsonyabb; mobilon egymás mellett maradnak. A Jóváhagyásra váró foglalások száma külön listát nyit, részletekkel, telefonnal és elfogadás/elutasítás lehetőséggel. Múltbeli kérésnél az áthelyezésre vezető út látszik.
- A felugró ablakok a háttérre kattintva és Esc-re bezáródnak. Az Esc a nyitott vendégkereső listát és az időpontfelvételi ablakot is bezárja.
- A beállítások rövid magyarázatokat kaptak a nyitvatartásról, jóváhagyásról, értesítésekről és közösségi belépésről.

## Vendégek és kapcsolatfelvétel

A Vendégek és a Foglalási események oldalon a név adatlapra mutató hivatkozás. Ha egy korábbi vendéget már eltávolítottak a listából, a foglalási előzmény részletei nyílnak meg. A megjelenített telefonszám `tel:` hivatkozás, amely mobilon a hívást indító alkalmazást nyitja meg; asztali gépen telepített hívóalkalmazás szükséges.

Mindkét listán van Üzenet gomb. Kapcsolt, regisztrált vendégnek helyben belső üzenet írható. Fiók nélküli vendégnél a felület ezt egyértelműen jelzi, és emailcím esetén a levelezőprogramot megnyitó emailhivatkozást ajánlja fel. Nem hozunk létre fiókot vagy látszólagos belső kézbesítést egy importált emailcímhez.

A szolgáltató eltávolíthat vendéget a saját listájából. Ez **helyi, előzményt megőrző eltávolítás**: a vendégfiók, más szolgáltató kapcsolata, a korábbi foglalások és befizetések nem törlődnek. A vendég eltűnik a saját keresőből és aktív listából. Új, bejelentkezve leadott foglalásával ismét megjelenhet; az eltávolítás nem általános foglalási tiltás.

A megerősített kérés szerint:

- **Regisztrált vendég:** emailjét és telefonját csak ő szerkesztheti a Vendégfiók → Saját elérhetőségeim részen. A szolgáltatói mezők írásvédettek, és a szerver a közvetlen PATCH-, teljes listás mentési és importkéréseket is ellenőrzi.
- **Importált / fiók nélküli vendég:** elérhetőségét a saját szolgáltatója továbbra is szerkesztheti.

A saját kapcsolattartási email külön mező a Google-/Facebook-belépési azonosítótól. Így a vendég módosíthatja az elérhetőségét anélkül, hogy az OAuth-belépése vagy más fiókhoz tartozása megváltozna. Az új elérhetőség a hozzá kapcsolt szolgáltatók aktuális vendégadatlapján látszik; a korábbi foglalások eredeti adatpillanatképe megmarad.

## Havi díjak, tranzakciók és zárolás

Az Előfizetés oldalon külön szerepelnek a **havi díjtételek**, a **tényleges tranzakciók** és a **számlák**.

1. A két próbahónap egy-egy **0 Ft-os, díjmentes tétel**. Ezek nem tényleges befizetések, és nem készül róluk Billingo-számla.
2. A próba után az adott havi időszak kezdetekor **4 000 Ft-os függő díjtétel** jön létre. A fizetési határidő pontosan az időszak kezdetétől számított **21 nap**; a felület a pontos dátumot és időt mutatja.
3. A határidő letelte után a szolgáltató **be tud lépni**, használhatja az előfizetését és a levelezést, de a naptár használata zárolt. Új foglalás, időpontlekérés, foglalásmódosítás és foglalásimport a szerveren is védett. A publikus látogató általános szünetelési üzenetet kap, nem a szolgáltató tartozásának részleteit.
4. A bankkártyás fizetés csak hiteles Stripe-eseményből kerül be a tényleges tranzakciók közé. Az ismételt esemény nem hoz létre új befizetést vagy új jóváírást. A tranzakciónál láthatók a kapott Stripe-azonosítók és az időszak adatai; titkos ügyfélkulcsot vagy teljes nyers webhook-tartalmat nem adunk vissza.
5. Átutalásnál a főadmin **Havi díjak és fizetési határidők** részében az adott függő tételnél választható a **Fizetettnek jelölés**. Meg kell adni a tényleges banki beérkezés dátumát és egyedi banki azonosítóját.
6. Egy banki azonosító nem rendezhet két külön havi tételt. Egy már rendezett hónapot másik banki azonosítóval sem lehet ismét jóváírni.
7. A fizetés a megfelelő havi tételt rendezi. Újabb hónap kifizetése nem törli a régebbi tartozást; a naptár csak akkor oldódik fel, ha nincs határidőn túli, rendezetlen havi díj.
8. A próbaidő alatt korábban önként elindított Stripe-előfizetés a meglévő próba végéig Stripe-próbát kap; a próba indulásakor továbbra sem kötelező bankkártyát megadni. Egy 0 Ft-os Stripe-esemény nem lesz tényleges befizetés vagy Billingo-számla. A Stripe-próba minimális előkészítési ideje miatt az utolsó két próbanapban új kártyás előfizetést a próba végén lehet indítani; a rendszer addig díjmentes marad, átutalás korábban is lehetséges. Meglévő élő Stripe-előfizetésnél nem indítunk második előfizetést: a már nyitott Stripe-számla fizetési oldalát használjuk, ha van ilyen.

A tárolt tranzakció minden rögzített adata lenyitható: összeg, mód, dátum, szolgáltató, hivatkozás, időszak, forrás és rendelkezésre álló külső azonosítók. Korábbi tranzakció hiányzó adatánál nincs kitalált érték.

Az állapot olvasáskor is frissül, tehát a zárolást nem lehet a cron kihagyásával megkerülni. A meglévő `schedule:run` most óránként a `raero:subscriptions` feladatot is indítja; ehhez **nem kell második cron-sort felvenni**. A korábbi adatok és jóváírt időszakok megmaradnak. A határidő nélküli, régi előfizetési rekordokhoz nem találunk ki visszamenőleges tartozást.

## Statisztika és mintaadatok

Van **Előző hónap** és **Elmúlt 30 nap** gyorsszűrő. A napi bevételi vonaldiagram a tényleges befizetés napja alapján számol, visszatérítésekkel csökkentve, és a nulla bevételű napokat is mutatja. A pontoknál dátum/összeg olvasható; a napi összegek táblázatban is megnyithatók.

Az elkülönített helyi tesztpéldányba több mint két hónapot lefedő, mesterséges vendég-, foglalás-, függő kérés- és befizetésadatokat töltöttünk. A felület külön jelzi a mintaadatokat. Nem történt éles adatfeltöltés vagy valódi kártyaterhelés.

Külön tesztpéldányon, az alkalmazás könyvtárában:

```sh
php artisan raero:sample-data --provider=1
```

A parancs csak `local` / `testing` környezetben és az alkalmazás `work/` könyvtára alatti SQLite-adatbázison, vagy `_test` végű MySQL/MariaDB-adatbázison működik. Ismételt futtatása nem duplikálja a mintaadatokat. Nem indít értesítést, fizetést vagy számlázást. Éles környezetben szándékosan hibával leáll.

## Google és Facebook

A gombokhoz alkalmazásazonosító és titkos alkalmazáskulcs kell, nem személyes fiókjelszó. A pontos, lépésenkénti útmutató és a konzolok hivatkozásai: [BELEPES.md](BELEPES.md).

## Frissítés

A korábbi [FRISSITES.md](../FRISSITES.md) adatmegőrző lépései érvényesek. Minden programfájlt, köztük a `public/refinements.js` fájlt is másold át, majd futtasd a `php artisan raero:upgrade` parancsot. Az új migráció nullable kapcsolattartási és tranzakció-metaadatmezőket ad hozzá; nem töröl korábbi adatot, és nem változtatja meg az APP_KEY-t vagy a feltöltéseket. A havi díjtételek a meglévő vállalkozási adatállapotban tárolódnak.

## Naptár: pontosabb célzás és olvasható idősávok

A napi és heti naptár megőrzi a könnyen eltalálható idősávokat; a teljes napot nem zsugorítja egy képernyőre. A látható naptárterület magassága a képernyőhöz igazodik, benne függőlegesen lehet görgetni. Mobilon napi nézet az alapértelmezés, a normál félórás sáv 48 px magas. A **Tágas idősávok** gombbal tovább növelhető a méret, a **Normál idősávok** gombbal visszaállítható. A havi nézet változatlanul elérhető.

A napokat erősebb oszlopvonalak, az órákat váltakozó háttér és hangsúlyosabb egészórás vonalak választják el. Egérmozgatáskor a teljes naposzlop és az idősáv sora együtt színeződik, a metszéspont külön keretet kap. A kiemelt dátum és időtartomány szövegesen is látható a naptár felett. A billentyűzettel fókuszált idősáv is kiemelést kap. A percre pontos nyitás/zárás megmarad; a részórás nyitást követő sávok egész és fél óráknál kezdődnek.

Ellenőrzés: JavaScript szintaxis; a meglévő `tests/refinements.cjs` böngészős ellenőrzés; célzott Playwright-ellenőrzés a sor/oszlop kiemelésre, billentyűzetes fókuszra, képernyőhöz igazításra, nagyításra, idősávból foglalási ablak nyitására és 390 px mobilos szélességre. Külső Google-/Facebook-belépés éles kulcsokkal és Meta-hitelesítés nélkül nem lett végigtesztelve. E változtatás nem érint adatbázist vagy migrációt.

## Közösségi belépés és saját foglalások

- Foglalóoldalról indított belépés után visszatérés az eredeti szolgáltatóhoz; közvetlen fiókbelépéskor saját fiók.
- Fiókban tárolt név, email és telefonszám automatikus kitöltése; saját elérhetőségek megjegyzése külön jelölőnégyzettel, sikeres foglalás után.
- A szolgáltatás, munkatárs, nap és szabad idősáv visszaállítása belépés után; elérhetőség újbóli ellenőrzésével.
- Saját, igazolt fiókkapcsolatú foglalások több szolgáltatótól, szolgáltatói és szolgáltatási hivatkozásokkal; általános új foglalás és üzenetküldés nélkül a saját adatlapon.
- Értékelés a megfelelő szolgáltatóhoz küldve; üzenetértesítések külön, megnevezett címzetthez tartozó beszélgetési oldalon.

Ellenőrzés: teljes PHPUnit SQLite-csomag 72 teszt, 418 ellenőrzés, 2 kihagyott InnoDB-párhuzamossági teszt. Az új tesztek Google- és Facebook-kapcsolatot szimulálva ellenőrzik a biztonságos visszairányítást, a saját elérhetőségek megőrzését és más vendégek adatainak kizárását a több szolgáltatós előzményekből. A `tests/guest-flow.cjs` Playwright-ellenőrzés szimulált közösségi belépéssel vizsgálja a visszatérést, az űrlap visszaállítását, az automatikus kitöltést, a telefonszám megjegyzését, a több szolgáltatós listát, a megfelelő szolgáltatóhoz küldött értékelést, a külön üzenetoldalt és a mobilos szélességet. A valódi külső Google-/Facebook-hitelesítést ez a teszt nem végzi el.

## Közösségi profilkép

Google- és Facebook-belépéskor az átadott profilkép is elmenthető, ha a fióknak még nincs képe. Saját feltöltést nem ír felül, képhiba esetén a belépés folytatódik. A helyben tárolt képet továbbra is csak a vendég és a hozzá kapcsolódó, jogosult szolgáltató láthatja. Nincs új migráció vagy `.env`-változó.

Új tesztek: mindkét közösségi szolgáltató képadatának feldolgozása szimulált HTTP-válaszokkal, privát képmegjelenítés, meglévő feltöltés megőrzése, nem engedélyezett képcímek kizárása, hibás/túl nagy kép és átirányítás elutasítása, hálózati hiba melletti fiókbetöltés. Valós külső fiókkal történő belépés és képletöltés nincs éles környezeten tesztelve.

## Listák, naptár és kivételes időbeosztás

- Foglalási események: a névre kattintva a foglalás részletei nyílnak meg. A külön Részletek gomb és a kiírt telefonszám megszűnt; a sor végén Üzenet és Hívás akció van.
- Vendégek: az adatlap a névre kattintva nyílik meg, nincs ismételt Adatlap gomb. Szerkeszt és Üzenet gomb, valamint feliratozott X eltávolítás érhető el; a telefonszám gombként hívható. A gombok között térköz van. Az eltávolítás továbbra is csak a saját nyilvántartásra vonatkozik.
- A naptár nem zsugorítja olvashatatlanra a napot. Normál és tágas idősávok választhatók. Mobilon napi nézet, a naptárhoz igazított fejléc és normál félórás sávonként 48 px méret segíti a használatot. Az összesítők egy sorban, értékkel együtt jelennek meg.
- A bevételi diagram teljes időtartománya elfér vízszintes csúszka nélkül. A napi összegek táblázata kihagyja a nulla összegű napokat; a diagram ezeknél is mutatja a folytonos időtávot.
- Beállításfülek: Alapvető beállítások, Kuponok és bérletek, Nyilvános adatlap, Integrációk.
- A szolgáltatás kiválasztásakor megjelenő dátum alapból ma, a nyilvános oldalon és a beágyazható modulban is. Az időpontot továbbra is a vendég választja ki.
- Napi nyitvatartási kivételek, teljes vagy részleges szabadság, munkatársra vagy egész vállalkozásra vonatkozó kieső idő és heti ismétlődő ebédszünet. Részletes használat: [IDOBEOSZTAS.md](IDOBEOSZTAS.md).

Ellenőrzés: a teljes PHPUnit-csomag MariaDB 11.4-en 78 teszt, 484 ellenőrzés, kihagyás nélkül; SQLite-on 78 teszt, 473 ellenőrzés, 2 kihagyott InnoDB-párhuzamossági teszttel. Az új tesztek a percre pontos, kibővített nyitvatartást, zárt napot, szüneten átnyúló foglalást, szünet pontos határait, munkatársankénti kiesést, többnapos szabadságot, adatelkülönítést, validálást és a meglévő foglalások megőrzését vizsgálják. A `tests/usability.cjs`, `tests/calendar.cjs`, `tests/refinements.cjs` és `tests/guest-flow.cjs` böngészős ellenőrzések sikeresek. A tesztek külön adatbázison és mesterséges helyi adatokkal futottak; az éles tárhelyhez és adatokhoz nem nyúltunk.

## 2026. november 5. – egyszerűbb foglalás és többnapos beállítás

- Ctrl/⌘ kattintással vagy húzással több naptári nap jelölhető ki, a heti és havi nézetben is. A naptárban rövid súgó és kijelölésszámláló van. A heti nézet a mai naptól hét napot mutat, a lapozás ezt mozgatja. A napi összesítők feliratai és értékei középre igazodnak.
- Az általános Nyitvatartás és szünetek gomb havi nézetben is elérhető. Külön fülek vannak a nyitvatartásnak, a Nem foglalható időnek és az Ebédszünetnek. A nap saját gombja csak az adott napi nyitvatartást és ebédszünetet mutatja; nem tartalmaz külön szabadságkezelőt. A napi ebédszünet felülírhatja vagy kikapcsolhatja az általános heti szünetet. A munkatársválasztó csak több munkatársas vállalkozásban látszik.
- A szolgáltatások hosszú checkboxlistája helyett egy lenyílóból több szolgáltatás hozzáadható, majd egyenként eltávolítható. Ez a szolgáltatói új időpontnál, a vendég foglalásánál és a beágyazható modulban is működik. Az új szolgáltatói időpont külön nap- és időválasztót használ, kizárólag a szerver szerint szabad, nyitvatartáson belüli időpontokkal. A szabad idősávok tízpercenként indulnak, a napi nyitás percétől számolva. A felesleges két bevezető szöveg kikerült.
- Google-, Facebook- és Apple-ikonos belépési gombok. Az Apple webes belépés kódja, a titkosított konfigurációs felület és az opcionális .env-változók elkészültek. A valódi Developer-fiók és kulcsok később megadhatók; a nem konfigurált gomb inaktív. Az Apple-visszahívás külön kezeli a másik oldalról küldött form POST-ot, a munkamenet megőrzésével, egyszer használható rövid életű relével és ellenőrzött aláírt azonosítóval. Vendégbelépés nem ad adminjogot. Új függőség: `firebase/php-jwt`, a Composer-zárolás szerint.
- A szolgáltatói vendégfelvétel, kapcsolattartási adatok szerkesztése és importja normalizált email-/telefonszám-egyezést vizsgál. A magyar 06, +36 és 0036 alakokat egységesen kezeli. Kézi foglalásnál egy egyértelmű meglévő vendég újra felhasználható; több régi egyezésnél választani kell. A korábbi másolatokról figyelmeztetés jelenik meg. Régi vendégeket, fiókokat vagy előzményeket nem vonunk össze automatikusan. Nyilvános foglalásnál továbbra sem adunk hozzáférést más vendég adataihoz pusztán beírt név vagy elérhetőség alapján; a biztonságos fiókkapcsolás korábbi szabályai megmaradnak.
- Bérletek: opcionális konkrét kezdő és záró dátum, kiadástól vagy első bérletes időponttól számolt érvényesség. A szerver foglaláskor, áthelyezéskor és lemondáskor is figyelembe veszi a már lefoglalt alkalmakat. Üres szolgáltatáskijelölés minden szolgáltatást jelent, külön Minden szolgáltatás opcióval. A korábban kiadott bérletek feltételei nem változnak meg utólag.
- Kuponok: a szolgáltatáskör és a működési kapcsolók külön csoportba kerültek. Közérthető felirat és súgó magyarázza az engedélyezést, a foglalóoldali megjelenést és az új vendégnek szóló ajánlatot.
- A vendégoldal új fejlécet, illusztrált vagy saját borítóképes bemutatkozó részt, jól látható belépést és rendezettebb, mobilos foglalási felületet kapott. Az adminfelület tipográfiája, kontrasztja, kártyái, gombjai és űrlapcsoportjai is megújultak, a meglévő alkalmazás részeként.

### Ellenőrzések és átadás

A teljes PHPUnit-csomag PHP 8.2.35 és MariaDB 11.4 mellett: **85 teszt, 558 ellenőrzés, kihagyás nélkül**. Az új esetek többek között az Apple aláírását, issuer/audience/nonce/lejárat ellenőrzését, rossz aláírás és algoritmus elutasítását, a visszahívás ismétlésének tiltását, a titkos kulcs elrejtését, a vendégjogosultságokat, a duplikációvédelmet, a napi ebédszünetet és a bérletek új dátumkezelését fedik le.

Sikeres Chromium/Playwright-ellenőrzések: `browser.cjs`, `calendar.cjs`, `refinements.cjs`, `usability.cjs`, `guest-flow.cjs`, `evolution.cjs`, `apple-browser.cjs`. Ezek között szerepel a billentyűzetes vendégkeresés, meglévő funkciók, mobilos szélesség, Ctrl-kattintás és húzás, egyszerre több nap mentése, egyéni mód munkatársválasztó nélkül, több szolgáltatás választása, tízperces időpontok, bérlet/kuponűrlapok, ikonok és nyilvános megjelenés. Az Apple külön böngészőtesztje ténylegesen másik eredetű form POST-tal fut, de az Apple szerverválaszait helyi tesztkód szimulálja; valós Developer-fiókkal végzett éles próba még nem történt.

Nincs új migráció vagy adatokat törlő művelet. Az új Composer-függőséget telepíteni kell. A meglévő `.env`, APP_KEY, feltöltések és szolgáltatói kapcsolati fájlok megmaradnak. Az éles tárhelyet és adatbázist nem módosítottuk.

Útmutatók: [Időbeosztás](IDOBEOSZTAS.md), [Bérletek](BERLETEK.md), [Apple-belépés](APPLE_BELEPES.md), [Tárhely-frissítés](../FRISSITES.md).
