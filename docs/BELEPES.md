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
