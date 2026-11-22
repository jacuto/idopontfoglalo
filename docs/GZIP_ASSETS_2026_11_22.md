# JS/CSS hálózati tömörítés – 2026.11.22.

Kiindulópont: `b44de7c7583f334201cf6565aaa401b484b0f5c0` (forrásfa: `9edd87a711d324fb835526781c74c95bb497a181`). Az alkalmazás JavaScriptje, CSS-e, API-ja, adatbázisa és üzleti szabályai változatlanok. Ez a kiadás kizárólag a nyilvános statikus szövegfájlok hálózati kiszolgálását javítja. A képek gyorsítótárazása nem része ennek a kiadásnak.

## Működés

A build az eredeti tartalomhashes JS/CSS mellett előre elkészíti a `.js.gz` és `.css.gz` fájlokat is. A `public/build/.htaccess` a gzipet elfogadó kéréseket a megfelelő előtömörített fájlra irányítja. A böngésző továbbra is ugyanazt az eredeti `.js`/`.css` URL-t használja, a kitömörítést automatikusan elvégzi.

A válasz megfelelő JavaScript/CSS MIME-típust, `Content-Encoding: gzip`, `Vary: Accept-Encoding` és a meglévő tartalomhashes fájlokhoz tartozó immutable gyorsítótár-fejlécet kap. A gzipet tiltó (`gzip;q=0`) vagy nem kérő kliens az eredeti bájtokat kapja. Ha a `.gz` fájl hiányzik, az eredeti fájl megmarad kiszolgálhatónak. A külön rewrite-szabály `no-gzip`/`no-brotli` jelzéssel akadályozza meg az előtömörített fájl újratömörítését.

Az előtömörített kiszolgáláshoz Apache-kompatibilis mod_rewrite és mod_headers szükséges. Nem igényel mod_deflate-ot, PHP-tömörítést vagy Node.js-t az éles tárhelyen. A modulokra vonatkozó feltételek hiányában a normál fájlok kiszolgálása marad. A gyökér `public/.htaccess` opcionálisan mod_deflate-tal tömöríti a különálló JS/CSS-t is, például a süti scriptjét vagy a forrásmód fájljait. A HTML/API és a bináris képek kezelése változatlan.

## Ellenőrzött méretek

| Fájl | Eredeti bájt | Gzip bájt | Csökkenés |
| --- | ---: | ---: | ---: |
| Alkalmazás JavaScript | 859 122 | 260 608 | 69,7% |
| Alkalmazás CSS | 262 380 | 49 548 | 81,1% |
| A kettő együtt | 1 121 502 | 310 156 | 72,3% |

Ez a hálózaton továbbított tartalom mérete, nem az összes alkalmazáskérés összege és nem garantált betöltési idő. A teljes fejlesztési ZIP nagyobb lesz, mert az eredeti fájlokat és az előre tömörített változatokat is tartalmazza. A böngésző egy kéréshez csak a kiválasztott változatot tölti le.

## Telepítés és éles ellenőrzés

A teljes kiadásból a rejtett `.htaccess` fájlokat és a két `.gz` fájlt is fel kell tölteni. A saját `.env`, APP_KEY, adatbázis, storage és vendor megőrzendő. Nincs szükség migrációra, npm installra vagy új cronra.

SSH/Bash alatt:

```bash
curl -sS -H 'Accept-Encoding: gzip' -D - -o /dev/null 'https://foglalo.digitmarketing.hu/build/app.baa287b00a5bd688.js'
curl -sS -H 'Accept-Encoding: gzip' -D - -o /dev/null 'https://foglalo.digitmarketing.hu/build/app.ffa411708b686547.css'
```

Mindkét válaszban `Content-Encoding: gzip` és `Vary: Accept-Encoding` szükséges. A JavaScript válaszmérete hozzávetőleg 261 kB, a CSS-é 50 kB. A webszerver előtömörített kiszolgáláskor általában Content-Length fejlécet is ad. Ha a gzip-fejléc hiányzik, a forrás továbbra is működhet, de az éles szerver még nem alkalmazta a beállítást. Ennek oka lehet kihagyott/rejtett fájl, letiltott override vagy a statikus fájlokat Apache előtt kiszolgáló nginx/CDN; ezt az éles fejlécek alapján kell eldönteni.

Ezután a korábbi PowerShell-script `cold` mérését érdemes újrafuttatni ugyanazon URL-en, ugyanazzal a géppel/beállításokkal; a `warm` mérés külön ellenőrzés. A tömörítés elsősorban az első hálózati betöltést csökkenti, a gyorsítótárból már átvitel nélkül érkező JS-t nem gyorsítja ugyanilyen mértékben.

## Tesztek

- Valódi helyi Apache 2.4: JS/CSS gzip, többféle Accept-Encoding, q=0 és identity válasz, MIME-típus, Vary, immutable gyorsítótár és dupla tömörítés elleni ellenőrzés.
- Külön Apache-példány mod_deflate nélkül: az előtömörített kiszolgálás működik, hiányzó gzipfájlnál normál fájlra esik vissza; ETag-feltételes kérés 304-et ad.
- A gzipfájlok kitömörítése bájtra pontosan az eredeti JS/CSS-t adja. A normál JS/CSS hash és tartalom változatlan.
- Determinisztikus build és `npm run check:assets`; Node build- és gzip-regressziók; FrontendAssetsTest: 3 teszt, 9 assertion.
- Böngészős működésellenőrzés a gzipet ténylegesen kiszolgáló helyi Apache-on.

Éles telepítés vagy éles szerverbeállítás-módosítás nem történt.

## Visszaállítás

A korábbi teljes ZIP: https://github.com/jacuto/idopontfoglalo/archive/b44de7c7583f334201cf6565aaa401b484b0f5c0.zip

Csak a hálózati tömörítés kikapcsolásához a `public/.htaccess` és `public/build/.htaccess` előző változatát kell visszatenni. A plusz `.gz` fájlok szabály nélkül nem lépnek működésbe, az eredeti JS/CSS megmarad. Adatbázis-visszaállítás nem szükséges ehhez a változáshoz.
