# November 15–16. fejlesztés: naptári eszközök, előrefizetés és SMS-átutalás

A fejlesztés a meglévő alkalmazást folytatja. Nem telepít automatikusan az éles tárhelyre, és nem cseréli le az APP_KEY kulcsot, a `.env` fájlt, az adatbázist vagy a feltöltéseket.

## Hol keresd az új funkciókat?

- **Naptár fejléc:** Új időpont, Új emlékeztető, és bekapcsolt várólistánál Várólista, egy sorban. Az emlékeztető csak belső szolgáltatói teendő, foglalással párhuzamos lehet, napi/heti/havi ismétléssel. A böngészőben esedékes jelzés látszik; ez nem külön SMS/push-szolgáltatás.
- **Beállítások → Alapbeállítások → Használt funkciók:** a várólista alapból kikapcsolva. Kikapcsolva a naptári és vendégoldali gomb eltűnik, a korábbi jelentkezések megmaradnak és küldésük szünetel. Bekapcsolva megerősített e-mailes jelentkezés után szabad helyről egyszeri értesítés megy; ez nem automatikus foglalás vagy kizárólagos helyfoglalás.
- **Alapbeállítások → Foglalás:** opcionális fix utolsó nap vagy gördülő hónapszám (1–36). A vendég keresése, várólistás ajánlata és foglalásbeküldése is tiszteletben tartja; az admin kézi felvitelét nem korlátozza.
- **Integrációk → Online fizetés:** saját Stripe, SimplePay vagy Barion, külön átutalási adatok és külön OTP SZÉP-kártyás SimplePay-kapcsolat.
- **Szolgáltatások → Szerkesztés → Előrefizetés:** általános szabály követése, nincs kötelező összeg, teljes összeg vagy fix/százalékos előleg. A funkció alapból kikapcsolva; a Főadmin központi kapcsolatai között külön letiltható.
- **Nyilvános adatlap → Elérhetőség és kereshetőség:** elfogadott fizetési módok. Ezek tájékoztató jelölések, önmagukban nem aktiválnak online elfogadást.
- **Értesítések → SMS-egyenleg:** új feltöltések 3 000 / 5 000 / 10 000 Ft. Kártyás befizetést a központi Stripe aláírt visszajelzése ír jóvá. Átutalásnál kérelem és egyedi SMS-közlemény készül; az egyenleg csak banki ellenőrzés után nő.
- **Főadmin → SMS-egyenlegek:** átutalás kézi jóváírása szolgáltató, összeg és egyedi banki tranzakcióazonosító szerint, kérelemmel vagy kérelem nélkül. Minimum 3 000 Ft; ugyanaz a banki azonosító csak egyszer számolható el. A központi átutalási adatok az üzemeltető adataiból jönnek, nem a szolgáltató saját előlegszámlájából.
- **Előfizetés:** formázott csomag/naptár összegzés; következő megújulás lemondása és visszakapcsolása. A fizetett időszak megmarad. Újraindításkor a szüneteltetett időszakokra nem készül visszamenőleges új díj.
- **Üzenetek:** kapcsolt fiók nélküli vendég meghívható; ha van e-mail-címe, külön jelöléssel az üzenet teljes szövege e-mailben is elküldhető. A belső olvasottság nem állítja, hogy az e-mailt elolvasták.
- **Főadmin → Hibanapló:** összevont, szűrt hibák, legfeljebb 1 000 csoport / 30 nap, azonos hibánál percenként legfeljebb egy rögzítés. Kulcs, üzenettartalom és token nem jelenik meg. Szerveres napi napló 7 napos megőrzéssel.

## Kötelező előrefizetés működése

A vendég az online foglalás előtt szerveroldali árajánlatból látja a kötelező összeget. Több szolgáltatásnál szolgáltatásonként számolunk, a kedvezményes kosárösszeget pontosan elosztva; a befizetett bérlet által fedezett rész nem kérhető be újra. A foglaláskor elmentett minimum későbbi szabályváltoztatástól nem módosul.

A kötelező összeg igazolásáig **függő, előrefizetésre váró** foglalás foglalja az időpontot. Teljesítés után automatikus megerősítés csak az egyébként automatikusan elfogadható szolgáltatásnál történik. Külön szolgáltatói jóváhagyás továbbra is szükséges lehet. Az admin nem tud puszta státuszmódosítással átugrani egy fennálló minimumot. Kézzel felvett szolgáltatói foglalásra ez az online fizetési kapu nem kényszerül rá.

Átutalásnál a szolgáltató a Befizetéseknél a ténylegesen beérkezett pénzt rögzíti. Nincs banki számlafigyelés. Nem fizetett foglalásnak egyelőre nincs automatikus lejárata: szükség esetén le kell mondani. Részösszeghez automatikus előlegszámla nem készül; a saját számlázóban kell kezelni. Előleg nem azonos az automatikusan elvesző foglalóval; a szolgáltató saját lemondási/visszatérítési feltételeket ad meg.

Központi letiltáskor új előrefizetés nem indítható. A következő állapotmentés a még függő fizetési kaput feloldja, a pénzügyi előzmények megőrzésével. A Ráérő havidíjának és SMS-feltöltésének központi Stripe-kapcsolata ettől független.

## Új fizetési kapcsolatok

### Barion

Saját elfogadóhely POSKey kulcs és saját Barion e-mail szükséges. Sandbox és éles rendszer elkülönül. A v2 Payment/Start indítás után a visszajelzés hatására a szerver a **v4 paymentstate** végponton, `x-pos-key` fejlécben hitelesítve ellenőrzi a PaymentId, PaymentRequestId, HUF összeg és Succeeded állapot egyezését. Böngészős visszatérés vagy a callback beküldött státusza nem ír jóvá. Többszöri jelzés idempotens.

Dokumentáció: https://docs.barion.com/Payment-Start-v2 és https://docs.barion.com/Payment-PaymentState-v4

### OTP SZÉP SimplePay

Külön, SZÉP-elfogadásra engedélyezett kereskedő, SECRET_KEY és IPN cím szükséges. A normál bankkártyás szolgáltató közben Stripe vagy Barion is lehet. A SimplePay API v2 a SZÉP-kereskedőn is **CARD** módszert használ, nem kitalált SZEP módszert. A SZÉP orderRef legfeljebb 32 alfanumerikus karakter: itt 32 hexadecimális karakter. Külön kulccsal ellenőrzött IPN és külön aláírt tranzakciólekérdezés igazolhat befizetést. Más kibocsátók SZÉP-kártyái nincsenek integrálva.

Elsődleges dokumentum: SimplePay 2.x Development Documentation 2025-11-05, 10. fejezet (SimplePay által készített dokumentum elérhető másolata: https://kenderter.eu/?attachment_id=162183&kt_pdfemb_secure=1). Belépési pont: https://simplepay.hu/fejlesztoknek/

Mindkét új kapcsolat valódi élesítése előtt saját kereskedői jóváhagyás és sandbox-próba szükséges. A fejlesztői tesztek HTTP-válaszokat szimulálnak, nem valós banki terhelések.

## Frissítés

A `2026_11_15_000018` és `2026_11_16_000019` adatmegőrző migrációk új táblákat és SMS-átutalási auditmezőket adnak hozzá. A régebben indított 2 000 Ft-os Stripe-feltöltés hiteles sikeres visszajelzése továbbra is elszámolható; a 3 000 Ft-os minimum az új fizetési kérelmekre vonatkozik.

Használd a FRISSITES.md lépéseit; a fájlcsere után:

```sh
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

A meglévő percenkénti ütemező szükséges az értesítésekhez és a várólistához. A közvetlen banki SMS-feltöltés jóváírása szándékosan kézi, nem ütemező feladata. Böngészőben Ctrl+F5.

## November 16. kiegészítés

Az emlékeztetők a napi és heti naptár idővonalán is láthatók, eltérő, szaggatott kerettel. Párhuzamos foglalás mellett külön vizuális sávot kapnak; a nyitvatartás előtti teendőhöz a látható időskála kibővül. Kattintással szerkeszthetők vagy elvégzettnek jelölhetők. A havi nézetben a megfelelő napon látszanak; a szabad időpontokat nem csökkentik.

Az Integrációk → Online fizetés → Előrefizetés beállításai résznél külön kiválasztható az előrefizetéshez felajánlott bankkártya, átutalás és OTP SZÉP-kártya. A helyszíni fizetési módok tájékoztató listájától független. A ki nem választott módot a szerver közvetlen fizetésindítási kérelemnél is elutasítja; megfelelően beállított, kijelölt fizetési mód nélkül az előrefizetés nem kapcsolható be. A korábbi beállításoknál az eddigi, konfigurált módok maradnak elérhetők.

A Használt funkciók opciókártyáin a hosszú várólista-leírás több sorban, levágás nélkül jelenik meg mobilon is.
