# Belépés Apple-fiókkal

A Google és a Facebook mellett az Apple-belépés is elkészült. A működéshez Apple Developer Program-tagság és az Apple felületén beállított webes Sign in with Apple-kapcsolat szükséges. A gomb addig **még nincs beállítva** jelzéssel inaktív. A meglévő Google-/Facebook-kapcsolat ettől nem változik.

## 1. Az Apple Developer felületén

Nyisd meg: https://developer.apple.com/account/resources/identifiers/list

1. A saját Apple Developer-fiókodban hozz létre vagy válassz ki egy **App ID**-t, és engedélyezd rajta a **Sign in with Apple** képességet. Ez lesz a kapcsolathoz tartozó elsődleges App ID.
2. Hozz létre egy **Services ID**-t a webes belépéshez. Ennek az Identifier értéke kerül az **Apple Services ID** mezőbe. Ez nem az Apple-fiókod emailcíme és nem egy numerikus App Store-azonosító.
3. A Services ID **Sign in with Apple → Configure** részében válaszd ki az elsődleges App ID-t. Állítsd be:
   - Domain: `foglalo.digitmarketing.hu`
   - Return URL: `https://foglalo.digitmarketing.hu/auth/apple/callback`
4. A **Keys** részen hozz létre egy Sign in with Apple-kulcsot, és kapcsold a megfelelő App ID-hoz. Jegyezd fel a **Key ID** értékét, és töltsd le a `.p8` fájlt.
5. A **Membership details** részen találod a **Team ID** értékét.

A domain és a visszahívási cím a tényleges telepítéshez tartozzon. Ha az Apple további ellenőrzést kér, azt a saját Apple-fiókodban kell befejezni. Az Apple-fiókod jelszavát nem kell a Ráérőnek megadni.

## 2. Beállítás a Ráérőben

**Beállítások → Integrációk → Google, Facebook és Apple belépés**:

- Apple Services ID
- Apple Team ID
- Apple Key ID
- Apple `.p8` privát kulcs: a letöltött fájl teljes szövege, a BEGIN/END sorokkal együtt

Mentsd el a kapcsolatot. A privát kulcsot a rendszer titkosítva tárolja; a böngészőbe nem küldi vissza. Az üresen hagyott kulcsmező megtartja a korábban elmentett értéket. A `.p8` fájlt és az éles kulcsot ne tedd a nyilvános forráskódba.

A vendégbelépés nem ad szolgáltatói adminjogot. Ha a szolgáltató saját adminbelépéséhez is Apple-fiókot szeretne használni, az **Adminnak engedélyezett Apple felhasználóazonosítók** mezőben külön kell engedélyezni a megfelelő Apple `sub` azonosítót. Ez nem a Services ID és nem az emailcím. Vendégekhez ezt az engedélylistát nem kell kitölteni.

## Beállítás .env-ből, az első szolgáltatónál

A meglévő `.env`-et őrizd meg; csak az alábbi változókat egészítsd ki. A Beállításokban már elmentett értékek elsőbbséget élveznek.

```dotenv
APPLE_CLIENT_ID=SAJAT_SERVICES_ID
APPLE_TEAM_ID=SAJAT_TEAM_ID
APPLE_KEY_ID=SAJAT_KEY_ID
APPLE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\nIDE_A_P8_KULCS_SORAI\n-----END PRIVATE KEY-----"
ADMIN_APPLE_IDS=
```

A privát kulcs minden valódi sorváltását `\n` formában add meg ezen az egy soron; a rendszer ezt visszaalakítja. Egyszerűbb a Ráérő fenti szövegmezőjébe a `.p8` teljes tartalmát bemásolni. Ne változtasd meg az APP_KEY-t. Mentés után:

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan config:clear
```

Az Apple-kapcsolat szolgáltatónként külön van. Más szolgáltatók saját titkosított kapcsolatukat a Beállításokban adhatják meg.

## Üzemeltetés és ellenőrzés

- A frissítés után a `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` parancsot is futtasd a FRISSITES.md szerint: a JWT-ellenőrzéshez új függőség került a zárolt csomaglistába.
- Az Apple visszahívása `POST` és `GET` kérést is használ ugyanazon a címen. A tárhely/WAF ne blokkolja az Apple form POST-ját.
- A `storage/framework/cache/data` legyen írható. Éles használatnál a cache ne legyen `array`; az alapértelmezett `file` megfelelő. A rövid életű visszahívási adatnak két kérés között is meg kell maradnia.
- A tárhelynek HTTPS-en el kell érnie az `appleid.apple.com/auth/token` és `appleid.apple.com/auth/keys` végpontokat. TLS-ellenőrzést ne kapcsold ki.
- Ha az Apple felhasználó elrejti az emailjét, az Apple privát továbbítási címét tároljuk. Az erre küldött értesítésekhez az Apple Developer-fiókban a levelezés tényleges feladóját/domainjét is regisztráld az Apple Private Email Relay szolgáltatásnál, a szükséges SPF/DKIM-beállításokkal.
- Az Apple nem ad át profilképet vagy telefonszámot. Saját kép és telefonszám továbbra is megadható a Ráérőben. A név csak az első engedélyezéskor érkezhet meg; a következő belépések nem törlik a már elmentett nevet/emailt.

Tesztelés: az Apple-válaszokat helyben szimuláltuk. Ellenőriztük az aláírt azonosítót, kibocsátót, alkalmazásazonosítót, lejáratot, nonce-ot, a hibás aláírás/algoritmus elutasítását, a visszahívás ismételt felhasználásának tiltását és azt, hogy vendégbelépés nem ad adminjogot. Külön Chromium-tesztben valódi, másik eredetről érkező form POST-tal ellenőriztük a munkamenet megtartását és a foglaláshoz való visszatérést; maga az Apple-hitelesítés itt is szimulált. Valós Apple Developer-adatokkal végzett éles próba és az Apple-fiók beállítása még a telepítés után szükséges.
