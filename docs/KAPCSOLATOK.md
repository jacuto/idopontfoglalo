# Külső kapcsolatok

## Két külön tulajdonos

**Vendég → szolgáltató:** a szolgáltató Beállítások / Kapcsolatok részében saját SMTP-, SMS-, Stripe-, Billingo- és Számlázz.hu-adatokat adhat meg. Az eredeti vállalkozás korábbi `.env`-adatai és `storage/app/private/connections.enc` fájlja tovább működnek. Új vállalkozásoknak nincs hozzáférése ezekhez; saját fájljuk `connections.enc.provider-ID`.

**Szolgáltató → üzemeltető:** `/foadmin` → Saját Billingo, Stripe és SMTP. A központi kulcsok a `storage/app/private/platform-connections.enc` külön titkosított fájlba kerülnek. A titkos mezőket a böngésző nem kapja vissza; a „Beállítva” jelzés nem jelent sikeres fizetést vagy küldést. Üres mező mentéskor megőrzi a korábbi kulcsot. Az összes titkosított fájlhoz az eredeti APP_KEY szükséges.

Külső fiók és valódi API-kulcs nélkül a kapcsolat nem aktiválható. A tesztek nem hoztak létre éles fizetést, számlát vagy kiküldést.

## Stripe – az üzemeltető előfizetése

A főadmin saját Stripe-fiókjában készíts havi, 1 havonta ismétlődő, **4 000 Ft fizetendő összegű, HUF** árat. Az alkalmazás a Stripe-árobjektumot szerveroldalon ellenőrzi. A próbaidő helyi, két naptári hónap; a szolgáltató felvételekor nincs Checkout és nincs kártyakérés. A Stripe Checkout elindítása már a fizetős előfizetés vállalása; a helyi próbaidőt nem másoljuk át automatikusan Stripe-próbára.

Webhook:

```text
https://foglalo.digitmarketing.hu/api/platform-stripe/webhook
```

Események:

- `checkout.session.completed`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.paid`
- `invoice.payment_failed`

A Stripe-aláírást és annak időbélyegét ellenőrizzük. A böngésző `success` paramétere nem igazol fizetést. A szolgáltató azonosítója szerver által küldött Stripe-metaadat; a havi befizetés az ismert előfizetéshez tartozó, aláírt `invoice.paid` eseményből kerül a központi naplóba. HUF 4 000 Ft-tól eltérő összeg nem lesz automatikusan jóváírva. A Stripe API-ban ez `amount_paid=400000`.

Az ismételt eseményazonosítót és a Stripe-számlaazonosítót deduplikáljuk. Korábban érkező számlaeseménynél, amikor még nem ismert az előfizetés, 409 választ adunk a Stripe-nak az újrapróbáláshoz. Régebbi fizetési hiba nem írhatja felül az újabb állapotot. Éles beállításkor a Stripe webhooknaplójában külön ellenőrizd az eseményeket és az ismételt kézbesítést.

Opcionális szerverbeállítás a felület helyett:

```dotenv
PLATFORM_STRIPE_SECRET_KEY=
PLATFORM_STRIPE_PRICE_ID=
PLATFORM_STRIPE_WEBHOOK_SECRET=
```

## Átutalás és üzemeltetői Billingo

A szolgáltató az Előfizetésnél látja az összeget, a konfigurálható üzemeltetői bankadatokat és az egyedi `RAERO-ID` közleményt. A főadmin csak a tényleges banki beérkezés után rögzítsen befizetést, a banki tranzakció **egyedi azonosítójával**. Ismételt rögzítés nem növeli újra a rendezett időszakot. Egy rögzítés egy 4 000 Ft-os hónap.

Az üzemeltető Billingo-fiókjában a beszélgetésben megadott saját kibocsátói név, cím és adószám legyen helyesen beállítva, alanyi adómentességgel. Ezek a személyes és banki adatok nincsenek a nyilvános forráskódban. Először a főadmin / Üzemeltető számlázási és banki adatai részben rögzítsd őket; addig nincs megjeleníthető átutalási számlaszám. A helyi név-/címbeállítás szerkesztése nem módosítja automatikusan a Billingo-fiók kibocsátói adatait.

A főadmin / Előfizetési számlák gombnál a rendezett befizetésből indítható számla. A szolgáltató előbb töltse ki a saját számlázási nevét és címét. A tétel bruttó fizetendő összege 4 000 Ft, áfakulcsa **AAM**. Az ismételt számlázás ugyanazt a számlát adja vissza. Bizonytalan külső eredménynél `unknown` állapot keletkezik; nincs automatikus új számla. A főadmin a Billingóban végzett ellenőrzés után külön egyeztető űrlapon rögzítheti a tényleges eredményt. A kiállított számla száma a szolgáltató saját előfizetésénél is látszik; a PDF jogosultságvizsgált szerveroldali letöltéssel kérhető.

```dotenv
PLATFORM_BILLINGO_API_KEY=
PLATFORM_BILLINGO_BLOCK_ID=
```

## Központi SMTP és hírlevél

A külön központi SMTP-kapcsolat az üzemeltető közvetlen üzeneteinek értesítéseihez és a hírlevelekhez kell. Nem használjuk hozzá valamelyik szalon saját SMTP-jét.

```dotenv
PLATFORM_MAIL_HOST=
PLATFORM_MAIL_PORT=587
PLATFORM_MAIL_USERNAME=
PLATFORM_MAIL_PASSWORD=
PLATFORM_MAIL_FROM_ADDRESS=
PLATFORM_MAIL_FROM_NAME=Ráérő
```

Használj engedélyezett feladócímű SMTP-szolgáltatást; a szolgáltató előírásai szerint állítsd be az SPF-, DKIM- és DMARC-rekordokat. A levélküldőnél ellenőrizd a feladócím hitelesítését és a küldési korlátokat. Port 587: STARTTLS; port 465: implicit TLS. A tanúsítvány-ellenőrzést nem kapcsoljuk ki.

A főadmin hírlevelet külön szolgáltatói vagy vendégcélcsoportnak készíthet. Van piszkozat, szöveges előnézet, tesztküldés és időzített küldés. Import vagy foglalás nem hoz létre feliratkozást. A hozzájárulást a bejelentkezett felhasználó külön jelöli. Vendégnél ellenőrzött email szükséges; a Facebooktól kapott, nem ellenőrzött email önmagában nem elég a feliratkozáshoz. A leiratkozási link aláírt, az adott email minden hírlevélcélcsoportjára hat. A munkafolyamat a tényleges küldés előtt újra ellenőrzi a hozzájárulást.

Az `accepted` azt jelenti, hogy **az SMTP-szerver elfogadta**, nem azt, hogy a címzett postaládájába biztosan megérkezett. SMTP-n nincs automatikus bounce-/megnyitáskövetés. Bizonytalan küldés `unknown`, automatikus ismétlés nélkül. A központi SMTP-szolgáltató naplójában ellenőrizhető. HTML-sablonszerkesztő és szolgáltatóspecifikus bounce-webhook nincs ebben a kiadásban.

## A szolgáltatók saját kapcsolatai

A korábbi szolgáltatói Stripe-fizetés, Billingo/Számlázz.hu számlázás, SMTP és Twilio SMS megmaradt. A kuponos foglalás fizetése a lefagyasztott kedvezményes összegre épül; a bérletből fedezett foglalás nem kér új bankkártyás fizetést. A szolgáltató saját Stripe-webhookja:

```text
https://foglalo.digitmarketing.hu/api/stripe/webhook?provider=ID
```

Az eredeti vállalkozásnál a korábbi `/api/stripe/webhook` cím is működik. Az új vállalkozások saját Stripe-fiókjában a saját ID-jukat tartalmazó URL-t állítsák be. Események: `checkout.session.completed`, `charge.refunded`. A szolgáltatói és a központi webhook aláírókulcsai különbözők.

A fizetési visszaigazoló oldal és a vendégnek küldött foglaláskezelő link megtartja a szolgáltató azonosítóját. A belső üzenet tranzakcióban mentődik, az emailértesítő külön küldési sorba kerül; a küldés hibája nem törli az üzenetet.

## Google és Facebook

A szolgáltató saját Kapcsolatok felületén adhatja meg az OAuth-adatokat. Az eredeti vállalkozás korábbi `.env`-adatai is működnek. Callbackek:

```text
https://foglalo.digitmarketing.hu/auth/google/callback
https://foglalo.digitmarketing.hu/auth/facebook/callback
```

Az indítás URL-jében `provider=ID` választ vállalkozást; a callback a munkamenetben, state-védelemmel megőrzött vállalkozást használja. A vendégszerepkörből nem lesz szolgáltatói vagy főadminjog. A szolgáltatói Google-belépés ellenőrzött emailes engedélylistához, a Facebook-belépés konkrét Facebook-azonosítóhoz kötött, vállalkozásonként külön. A főadminhoz külön helyi jelszó szükséges.

A Google/Facebook-fejlesztői fiók, az engedélyezett domain, a callbackek és az esetleges szolgáltatói alkalmazás-jóváhagyás a fióktulajdonos feladata. Ne add át a kulcsokat beszélgetésben.

## Cron

Egyetlen percenkénti `php artisan schedule:run` szükséges. Ez indítja:

```sh
php artisan raero:deliver --limit=50
php artisan raero:newsletters --limit=30
```

Kézi futtatásuk már tényleges külső küldést végezhet, ha a kapcsolatok aktívak. Éles adatokkal ne használd őket puszta kapcsolatpróbának. A hírlevél felületének Tesztküldés gombját saját tesztcímre használd. A központi cron az összes szolgáltató értesítőit a megfelelő vállalkozás külön kulcsaival dolgozza fel. A korábbi kézi küldés gomb megmaradt, de kizárólag a bejelentkezett szolgáltató saját sorát dolgozza fel; központi üzemeltetői üzenetet vagy más szolgáltató értesítőjét nem indíthatja.

A külső API-hostokat és SMTP-portokat a tárhely kimenő hálózatának is engednie kell. A konfigurációs értékek létezése nem igazolja a hálózati hozzáférést vagy a külső fiók jogosultságait.

## Beágyazás és saját URL

Modern, iframe nélküli beágyazás:

```html
<script src="https://foglalo.digitmarketing.hu/embed.js" defer></script>
<raero-booking endpoint="https://foglalo.digitmarketing.hu" provider="2"></raero-booking>
```

A szolgáltató Beállítások / Beágyazás részében külön engedélyezze a befogadó weboldal pontos HTTPS-originjét. Más vállalkozás originlistája nem ad jogosultságot. A beágyazás nem viszi át a szolgáltatói munkamenetet vagy a harmadik féltől származó sütiket. A vendégoldali kupon-/bérletválasztó a teljes saját foglalóoldalon működik; a beágyazott komponens ennek az alapfoglalási részét tartalmazza.

Saját foglalóoldalak már külön `provider=ID` URL-en vannak. Automatikus aldomain-, DNS-, egyéni domain- és SSL-provizionálás **nincs**; ezekhez külön tárhely-/DNS-integráció szükséges. A digitmarketing.hu főoldalát a fejlesztés nem módosította.
