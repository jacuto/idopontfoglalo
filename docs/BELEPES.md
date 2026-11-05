# Google- és Facebook-belépés beállítása

A vendég valóban a megszokott Google- vagy Facebook-gombra kattinthat, és a külső szolgáltató saját oldalán lép be. Ehhez a foglaló tulajdonosának egyszer létre kell hoznia egy OAuth-alkalmazást.

**Nem a Gmail-/Facebook-fiók jelszavát kell megadni.** Két alkalmazásadat kell: egy azonosító és egy titkos kulcs. A Gmail-fiók Google-fiók, nincs külön „Gmail-belépési API”.

## Google

1. Nyisd meg: <https://console.cloud.google.com/>. Válassz vagy hozz létre egy projektet, például „Ráérő foglalás”.
2. Nyisd meg a **Google Auth Platform** részt. Első használatkor a **Get started / Kezdés** beállításon haladj végig.
3. A **Branding / Márkaépítés** résznél add meg az alkalmazás nevét, kapcsolattartási emailjét és a saját weboldalad szükséges adatait. A vendégek ezt az alkalmazásnevet látják belépéskor.
4. Az **Audience / Közönség** résznél külső vendégekhez általában **External / Külső** kell. Tesztállapotban vedd fel a kipróbáláshoz használt Google-fiókot a tesztfelhasználók közé.
5. **Clients / Ügyfelek → Create client / Ügyfél létrehozása → Web application / Webalkalmazás**.
6. Az engedélyezett JavaScript-eredet:

   ```text
   https://foglalo.digitmarketing.hu
   ```

7. Az engedélyezett átirányítási URI, pontosan így, záró perjel nélkül:

   ```text
   https://foglalo.digitmarketing.hu/auth/google/callback
   ```

8. A létrehozás után megjelenik a **Client ID** és a **Client Secret**. Meglévő kliensnél az Ügyfelek listájából megnyitva találod őket; a Google a titkos érték megjelenítését vagy letöltését az aktuális konzol szabályai szerint engedi.
9. A Ráérő szolgáltatói **Beállítások → Szolgáltatói kapcsolatok → Google / Facebook** részében másold őket a **Google Client ID** és **Google Client Secret** mezőbe, majd ments.
10. Próbáld ki a vendégfiók oldalán. Más vendégek számára a Google-konzolban a közzétételi/közönségbeállításokat is rendbe kell tenni. A konzol által kért esetleges domainellenőrzést vagy alkalmazásellenőrzést a saját Google-fiókodban végezd el.

## Facebook / Meta

1. Nyisd meg: <https://developers.facebook.com/apps/>.
2. **Create App / Alkalmazás létrehozása**. A Use cases oldalon a **„Authenticate and request data from users with Facebook Login”** sort jelöld be, majd válaszd a **Next** gombot. A Meta felületén az elnevezés az alkalmazás típusától függhet.
3. Az alkalmazás **Settings → Basic / Beállítások → Alapadatok** részén találod az **App ID / Alkalmazásazonosító** értékét.
4. Ugyanitt az **App Secret / Alkalmazás titkos kulcsa** mellett a **Show / Megjelenítés** gombbal kérheted le a titkos kulcsot. A Meta ehhez kérheti a saját fiókod helyi hitelesítését; ezt a Meta oldalán végezd el.
5. Az alkalmazás domainje a saját domained legyen. A webes oldal címe:

   ```text
   https://foglalo.digitmarketing.hu/
   ```

6. **Facebook Login → Settings / Beállítások**: legyen engedélyezve a webes OAuth-belépés. A **Valid OAuth Redirect URIs / Érvényes OAuth-átirányítási URI-k** mezőbe:

   ```text
   https://foglalo.digitmarketing.hu/auth/facebook/callback
   ```

7. A Ráérőben másold az azonosítót a **Facebook App ID**, a titkos kulcsot a **Facebook App Secret** mezőbe, majd ments.
8. Fejlesztői módban csak az alkalmazáshoz felvett fejlesztői/tesztelői fiókok működnek. Vendégekhez éles mód és a Meta által kért esetleges felülvizsgálat, adatkezelési oldal vagy üzleti ellenőrzés is szükséges. A Meta által megkövetelt nyilvános adatkezelési/törlési tájékoztatót a saját tényleges tájékoztatód címével töltsd ki.

## Mit fog látni a vendég?

Beállított kapcsolatnál **Google / Gmail-fiókkal belépés / regisztráció** és/vagy **Facebook-fiókkal belépés / regisztráció** gombot. A gomb a külső szolgáltatóhoz visz; első alkalommal ott kérhetnek hozzájárulást. Ez nem a foglalóoldali mérési hozzájárulás: a mérés ebben a kiadásban ki van kapcsolva.

Hiányzó kapcsolatnál a megfelelő gomb látható, de **„még nincs beállítva”** jelzéssel nem kattintható. A foglalás belépés nélkül továbbra is működik. Nem jelenítünk meg látszólag működő közösségi gombot beállítatlan kapcsolathoz.

A titkos kulcs a szolgáltató saját, titkosított kapcsolati fájljába kerül. A mentési űrlap nem olvassa vissza a titkos értéket, a nyilvános GitHub-forrás pedig nem tartalmazza. Az alkalmazásadatok megadása miatt önmagában nem kell megváltoztatnod a személyes Google- vagy Facebook-jelszavadat.

## Beállítás a meglévő .env fájlban

cPanel → Fájlkezelő → Beállítások → Rejtett fájlok megjelenítése. Nyisd meg a **`/home/digitmar/idopontfoglalo-php/.env`** fájlt. Az alábbi változókat módosítsd, ha már léteznek; ne hozz létre belőlük második sort. A helyőrzők helyére a saját Google Client ID / Client secret és Facebook App ID / App secret értékeid kerülnek. A titkos értékek csak a tárhelyen legyenek, a GitHubon ne.

```dotenv
APP_URL=https://foglalo.digitmarketing.hu
GOOGLE_CLIENT_ID=SAJAT_GOOGLE_CLIENT_ID
GOOGLE_CLIENT_SECRET=SAJAT_GOOGLE_CLIENT_SECRET
FACEBOOK_CLIENT_ID=SAJAT_FACEBOOK_APP_ID
FACEBOOK_CLIENT_SECRET=SAJAT_FACEBOOK_APP_SECRET
FACEBOOK_GRAPH_VERSION=v23.0
```

Nem kell külön redirect-változót felvenni: a rendszer az APP_URL alapján képezi a visszahívási címeket. A meglévő APP_KEY, adatbázis-, email- és egyéb beállításokat hagyd meg. Ne másold rá a teljes `.env.example` fájlt.

Google → Clients → webalkalmazás:

- Authorized JavaScript origins: `https://foglalo.digitmarketing.hu`
- Authorized redirect URIs: `https://foglalo.digitmarketing.hu/auth/google/callback`

Meta → Facebook Login → Valid OAuth Redirect URIs:

- `https://foglalo.digitmarketing.hu/auth/facebook/callback`

Mentés után SSH-ban (PowerShellből SSH-val belépve már a tárhely parancssorában vagy):

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan config:clear
```

Ez csak a konfigurációs gyorsítótárat üríti. Ezután próbáld ki a saját nyilvános foglalóoldalon a belépést. A Beállításokban korábban elmentett kapcsolati értékek elsőbbséget élveznek az `.env`-vel szemben; ha ott már másik alkalmazást állítottál be, ugyanott frissítsd az azonosítót és a kulcsot. A titkosított kapcsolati fájlt ne töröld.

Az `.env`-alapú közösségi kapcsolat a meglévő, első szolgáltatóhoz tartozik. Más szolgáltatók saját kapcsolatukat a Beállításokban adhatják meg. Az `ADMIN_GOOGLE_EMAILS` és `ADMIN_FACEBOOK_IDS` külön szolgáltatói hozzáférési engedélylisták: vendégbelépéshez nem szükségesek, és a Facebook App ID **nem** személyes felhasználói azonosító. A közösségi belépés önmagában nem ad adminjogot.

A Meta által kért személyazonosság- vagy üzleti ellenőrzést az alkalmazás kódja nem helyettesíti. Mintaadatokkal létrehozott fiók helyett a Meta szabályainak megfelelő, valós üzemeltetői fiók szükséges; az ellenőrzést a Meta felületén kell befejezni. Addig a fejlesztői módban engedélyezett tesztelők használhatják a belépést.

## Belépés után a foglalás folytatása

A szolgáltató nyilvános foglalóoldalának belépési hivatkozásáról indított Google- vagy Facebook-belépés után a vendég ugyanennek a szolgáltatónak a foglalóoldalára tér vissza. A közösségi szolgáltató által átadott név és email már a fiókban tárolódik, és bejelentkezés után automatikusan kitöltjük a foglalási űrlapon. A Facebook csak akkor ad emailcímet, ha az elérhető és a vendég engedélyezi. A szokásos Google-/Facebook-belépés telefonszámot nem ad át; ezt a vendég egyszer adhatja meg.

A bejelentkezett vendég foglalásánál az **Elérhetőségeim megjegyzése sikeres foglalás után** jelölőnégyzet szabályozza a saját email/telefonszám mentését. Másnak történő foglaláskor ezt ki lehet kapcsolni. A mentés a vendég saját, CSRF-védett profilvégpontján történik, és nem változtatja meg a Google-/Facebook-belépési azonosítót. A foglalás sikere és az elérhetőségek mentésének sikere külön visszajelzést kap. A saját fiókban továbbra is szerkeszthetők az elérhetőségek.

Belépés idejére ugyanabban a böngészőfülben megőrizzük a kiválasztott szolgáltatást, munkatársat, napot és idősávot, legfeljebb 20 percig. Ezek a böngésző munkamenettárába kerülnek; nevet, telefonszámot vagy emailcímet nem mentünk ide. Visszatéréskor az idősávokat újra lekérjük, a kiválasztás csak akkor áll vissza, ha az adott időpont még elérhető. A végleges foglalást továbbra is a szerver ellenőrzi.

Közvetlenül a `/fiokom` oldalról indított belépés után a saját fiók nyílik meg. Itt az összes saját, fiókazonosítóhoz kapcsolt foglalás szerepel, szolgáltatóval, szolgáltatásokkal, időponttal és állapottal, közelgő és lezárt csoportban. A szolgáltató és a szolgáltatás hivatkozása a megfelelő vállalkozás oldalára vezet. Név vagy email egyezése nem kapcsol hozzá automatikusan idegen foglalást.

A saját adatlapról az általános üzenetküldő és a szolgáltató nélküli Új foglalás hivatkozás kikerült. Az üzenetértesítések külön `/uzenetek?provider=AZONOSITO` oldalra vezetnek, a szolgáltató nevével. Az üzemeltetői üzenetek külön beszélgetésként nyílnak meg. Az értesítési levelek régi, saját fiókra mutató üzenethivatkozásai is átirányítanak erre a külön oldalra.

Ehhez a frissítéshez nem kell új `.env`-változó vagy új OAuth-visszahívási cím. A meglévő adatbázis-frissítéseket továbbra is futtatni kell a FRISSITES.md szerint; a kódfeltöltés után `php artisan optimize:clear` szükséges az új útvonalhoz is.

## Google-/Facebook-profilkép átvétele

A Google `picture` és a Facebook `picture.type(large)` profiladatából átvesszük a képet vendégbelépéskor, ha az elérhető és a vendégnek még nincs elmentett Ráérő-profilképe. A korábbi verziók csak a nevet és az emailt mentették. A már regisztrált vendégnek a frissítés után ki kell lépnie, majd újra belépnie a közösségi fiókjával az átvételhez. A közösségi képet is saját, privát tárhelyre mentjük; a megjelenítés a meglévő jogosultságvédett avatar-végponton történik. Egy későbbi közösségi belépés nem írja felül a már elmentett képet. A saját fiókban továbbra is lehet új képet feltölteni.

Nem kell új kulcs vagy külön OAuth-jogosultság: a meglévő Google `openid email profile`, illetve Facebook `email,public_profile` hozzáférést használjuk. A kép hiánya, letöltési hibája vagy elutasítása nem akadályozza a belépést; ilyenkor alapértelmezett avatar látszik, és saját kép tölthető fel. A Facebook által jelzett alapértelmezett sziluettet nem importáljuk.

A letöltés csak HTTPS-en, a Google és a Meta képkiszolgálóinak engedélyezett domainjeiről történhet, átirányítás követése nélkül. JPEG, PNG vagy WebP fogadható el, legfeljebb 2 MB fájlmérettel és 4096 × 4096 képponttal; a tartalmat is ellenőrizzük. A feltöltési könyvtár és az alkalmazáskulcs megőrzése továbbra is szükséges. Az adatbázis korábbi avatar-migrációjának futtatása nélkül az import kimarad, a belépés működik.

## Apple-belépés

A Google és a Facebook mellett Apple-gomb és felismerhető szolgáltatói ikonok is elérhetők. Az Apple-beállításokhoz külön útmutató készült: [APPLE_BELEPES.md](APPLE_BELEPES.md). A nyilvános foglalóoldal fejlécében jól látható Belépés / Saját fiók hivatkozás van; innen indítva is megmarad a kiválasztott szolgáltató és a foglalási folyamat.
