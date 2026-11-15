# Integrációk, SMS-egyenleg és beállítási lapfülek – 2026.11.15.

## Adatmegőrző frissítés

A GitHub-kiadás fájljait másold fel. A meglévő `.env`, `APP_KEY`, `storage/` és adatbázis megőrzendő. Ne futtass adatbázis-visszaállítást vagy `migrate:fresh` parancsot.

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

A 17-es migráció új, külön SMS- és marketingtáblákat hoz létre. Pénzügyi/hozzájárulási előzmények miatt visszafelé törlő migráció nincs. A böngészőben Ctrl+F5 szükséges. A rendszeres feldolgozáshoz a meglévő percenkénti `schedule:run` cron szükséges; ez futtatja az értesítéseket, hírlevélkampányokat és a naptárszinkront is.

## Hol található?

Beállítások, két vízszintes fülsor:

- Alapbeállítások: Vállalkozás, Foglalás, Értesítések, Használt funkciók, Belépés és biztonság.
- Integrációk: Online fizetés, Számlázás, Hírlevelek, Google Naptár.
- A Nyilvános adatlap és a Kuponok és bérletek külön fő fül marad.
- Mobilon a fülsor oldalra görgethető. A nem látható alapbeállítási csoportok értékei a mentésnél megmaradnak.
- A küldési sor az Értesítéseknél lenyitható „Küldési előzmények és hibák” rész.

## SMS

Végső díj induláskor **25 Ft/szegmens**. Főadmin → **SMS-egyenlegek és díjazás**: módosítható. Az új díj a később lefoglalt küldésekre vonatkozik, a korábbi foglalások pillanatfelvételt őriznek. Minden szolgáltató saját egyenleget kap, előfizetési státusztól függetlenül. Kezdőegyenleg nincs a próba indításakor; az első ténylegesen fizetett előfizetés után jár egyszeri 500 Ft ajándék. Meglévő korábbi befizetésekre a frissítés nem ír automatikusan visszamenőleg ajándékot.

Feltöltés 2 000 / 5 000 / 10 000 Ft, egyszeri **központi Stripe** checkout. A jóváírás csak a már beállított központi, hitelesített `checkout.session.completed` webhookból történik, nem a visszatérési URL-ről. A központi webhooknak a `charge.refunded` eseményt is fogadnia kell. A szolgáltató saját Stripe/SimplePay kulcsa nem vesz részt a feltöltésben. Ismételt callback nem ír jóvá kétszer. Részleges visszatérítés kumulatív különbözete levonódik; ha az egyenleget már felhasználták, negatív szabad egyenleg is lehet, ami a további új küldést blokkolja.

A teljes, adott szolgáltatóhoz esedékes új SMS-csoport fedezetét tranzakcióban, a küldés előtt foglaljuk. A worker feldolgozási limitje nem csökkenti az előzetesen ellenőrzött csoportot. Ha nincs elég fedezet, egyetlen új SMS sem indul a csoportból. `waiting_balance` állapot, felületi figyelmeztetés és belső üzemeltetői rendszerüzenet keletkezik. A többi vállalkozás egyenlegét nem használjuk. Feltöltés után a következő futás újra ellenőrzi a tartalmat és az aktualitást.

BulkGate-átvétel után egyszer terhelünk. Bizonytalan hálózati válasz vagy megszakadt küldés esetén a foglalás megmarad, nincs automatikus ismétlés. Küldési előzmények → **SMS eredményének rendezése**: BulkGate-ellenőrzés után átvettként elszámolható vagy biztosan nem küldöttként feloldható. A rendezés önmagában nem küld újra. A feltöltések elkészült számlájának sorszáma és kiállítási dátuma a főadmin SMS-oldalán kézzel rögzíthető; ez a kiadás a feltöltésből nem indít automatikus Billingo-számlát.

## Hírlevelek

Szolgáltatónál Pro jogosultság és saját Brevo/Mailchimp fiók kell. Főadminnál külön Brevo-kapcsolat, kizárólag a központi hírlevélre feliratkozott szolgáltatókhoz. A titkos kulcsok titkosítva tárolódnak, visszaolvasáskor nem kerülnek a böngészőbe. Üres kulcsmező megőrzi a korábbit; szolgáltatóváltás új kulcsot igényel.

A rendszer natív külső kampányt készít, nem tömeges SMTP-levelezést végez. A régi, ütemezett SMTP-kampányok szüneteltetett állapotba kerülnek; a tranzakciós rendszerlevelek változatlanul a központi SMTP-t használják. Külső feladó/domain hitelesítése, küldési keret és szükség esetén külön előfizetés a szolgáltató feladata. Brevo/Mailchimp ingyenes kerete nem korlátlan, változhat.

Csak kifejezett hozzájárulással rögzített vendég választható. Foglalás vagy e-mail-egyezés önmagában nem feliratkozás. Vendég saját fiókból, igazolt e-maillel adhat hozzájárulást; szolgáltató az igazolás megadásával rögzítheti. Kampányonként elkülönített lista/szegmens; más vállalkozás címzettjei nem kerülnek bele. A külső leiratkozott/tiltott cím nem iratkozik vissza automatikusan.

Menet: piszkozat → címzettválasztás → előkészítés (cron) → frissítés → próbaküldés → jóváhagyott azonnali/ütemezett küldés. Bizonytalan válasznál ellenőrizendő állapot és nincs automatikus újraküldés. Leiratkozási webhookot állíts be a kapcsolat speciális adatai között megjelenő titkos URL-lel. Ezt az URL-t ne tedd nyilvánossá. A GYIK `/gyik#hirlevelek` részletezi a kattintásokat.

## Fizetés és számlázás

Szolgáltatói Stripe vagy SimplePay választható. Saját kereskedői kulcs, saját szerződés/díjak, saját bevétel; a Ráérő előfizetési Stripe-kapcsolata külön marad. A Stripe-nál nincs kezdeti díj, tranzakciós díj van. SimplePay-nél a saját szerződés feltételei érvényesek.

SimplePay: először sandbox, merchant + SECRET_KEY + saját vállalkozást kijelölő IPN URL. A szerver HMAC SHA384 aláírást ellenőriz, a FINISHED IPN-t külön aláírt lekérdezéssel ellenőrzi összeg/pénznem/tranzakció szerint. Visszatérés nem könyvel befizetést. A fejlesztői tesztek HTTP-helyettesítéssel futnak; valódi SimplePay sandbox-tranzakció és a SimplePay élesítési ellenőrzése a kereskedői hozzáféréssel még elvégzendő. Visszatérítés a SimplePay felületén, majd kézi egyeztetés; automatikus SimplePay refund-szinkron nincs ebben a kiadásban.

Számlázz.hu kizárólag szolgáltatói kapcsolatként: Agent-kulcs, opcionális előtag/bank/számlaszám/e-mailes számlaküldés. A teljes rendezett foglalás számlázható; előlegszámlát a meglévő funkció nem támogat. Külföldi címhez a Számlázz.hu-kapcsolat jelenleg nem használható. Automatikus számlázás külön, Pro kapcsoló. A számlázó saját API/Agent csomagja szükséges. Üzemeltetői Billingo-kapcsolat marad külön.

## Google Naptár

Sikeres összekapcsolás után az érintett kapcsolat első foglaltságfrissítése megtörténik, a visszatérő felület saját kapcsolatra korlátozott szinkront indít. A fennmaradó feladatokat a cron végzi; kézi „Szinkronizálás most” gomb is van. Más kapcsolat függő munkáit ez nem dolgozza fel.

Google Cloud: a Testing → Production váltás nem azonos a Google ellenőrzésével. Naptárengedélyekhez szükséges ellenőrzés hiányában figyelmeztetés/felhasználói korlát maradhat. Testing alatt csak hozzáadott tesztelők és rendszerint hét napos engedély használható. Végleges domainnél APP_URL, pontos Google OAuth callbackek (`/auth/google/callback`, `/auth/google-calendar/callback`), hitelesített domain és jogi URL-ek frissítendők; új ellenőrzés is szükséges lehet. Részletek: `/gyik#google-calendar`.

## Ellenőrzés

A teljes PHPUnit-futás: 252 teszt, 1 779 állítás; négy meglévő, valódi MySQL/MariaDB-t igénylő többfolyamatos teszt SQLite-on kimaradt. A legutóbbi kis hibakezelési és Brevo-lista javítás után a kapcsolódó 17 teszt / 129 állítás ismét sikeres. Valódi külső SMS-, kampány-, számla- vagy fizetési művelet nem indult: HTTP-helyettesítések és elkülönített helyi böngészőfixture ellenőrizte a folyamatokat.

Böngészőben ellenőrizve: vízszintes fő- és alfülek asztali/mobil nézetben, oldalirányú mobilgörgetés vízszintes oldaltúlfutás nélkül, más csoportok adatainak megmaradása mentéskor, Stripe/SimplePay és Számlázz.hu mezők, Pro hírlevélfül, külön főadmin-Brevo, SMS-díj mező, vizuális hírlevélszerkesztő és munkatárskártya sarokba helyezett törlésgombja. JavaScript-hiba nem jelentkezett. Éles szerverfrissítést ez az ellenőrzés nem végzett.
