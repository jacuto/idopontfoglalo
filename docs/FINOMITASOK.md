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
