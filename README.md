# Ráérő – Laravel időpontfoglaló

A **jacuto/idopontfoglalo meglévő PHP/Laravel alkalmazásának továbbfejlesztése**, nem külön demó. PHP 8.2+, Laravel 12, MySQL/MariaDB. Nincs Node.js-build vagy Passenger-követelmény.

- [Vendégkereső, helyalapú településválasztás, szolgáltatói regisztráció és egyéni foglalás javítása](docs/KERESO.md)
- [Főadmin oldalsáv, szolgáltatókeresők és vendégoldali felső menü](docs/NAVIGACIO.md)
- [Egyszerűbb űrlapok, szakmai kapcsolók, QR és vendégfiók – november 8.](docs/NOVEMBER8.md)
- [Napi használati finomítások, 21 napos határidő és mintaadatok](docs/FINOMITASOK.md)
- [Google-/Facebook-alkalmazásadatok megkeresése](docs/BELEPES.md)
- [Frissítés Tárhely.eu-n, mentéssel és pontos parancsokkal](FRISSITES.md)
- [Funkcióleltár, megőrzött működés, szabályok és ismert korlátok](docs/ATADAS.md)
- [Külső kapcsolatok, callbackek, webhookok és cron](docs/KAPCSOLATOK.md)
- [Tesztek futtatása külön adatbázison](docs/TESZTELES.md)

## Belépési pontok

| Felület | Cím |
|---|---|
| Közös kezdőoldal / belépett szolgáltató munkatere | `/` |
| Szolgáltatókereső | `/szolgaltatok` |
| Szolgáltatói csatlakozás | `/szolgaltato` |
| Szolgáltatói e-mailes belépés | `/belepes` |
| Eredeti vállalkozás belépése | `/szolgaltato?provider=1` |
| Másik vállalkozás | `/?provider=2` |
| Nyilvános adatlap és foglalás | `/foglalas?provider=2` |
| Vendégfiók és szolgáltatóválasztás | `/fiokom?provider=2` |
| Foglalási titkos hivatkozás | `/modositas?provider=2&token=…` |
| Külön főadmin | `/foadmin` |

Az eredeti szolgáltatói jelszó továbbra is csak az eredeti vállalkozáshoz jogosít. Főadminjogot nem ad. Az új szolgáltatók a főadminfelületen vehetők fel; azonosítójuk a létrehozás után látható.

## Adatmegőrzés

Az eredeti `salon_state` 1-es rekordja megmarad. Az új vállalkozások ugyanebben a táblában külön rekordot kapnak, külön tranzakciós zárolással. A kapcsolati kulcsok vállalkozásonként külön, az alkalmazáskulccsal titkosított fájlba kerülnek. A központi Stripe/Billingo/SMTP-kapcsolat ettől is külön áll.

Frissítéskor **tilos** a saját `.env`, az `APP_KEY` és a `storage` lecserélése; nem használunk `migrate:fresh`, `migrate:refresh`, törlő seedert vagy automatikus visszagörgetést. Az új migrációk táblákat és mezőket adnak hozzá. A meglévő adatállományon a `raero:upgrade` megismételhető.

## Új telepítés fejlesztői gépen

Már működő rendszernél ehelyett a [frissítési útmutatót](FRISSITES.md) kövesd.

```sh
composer install
cp .env.example .env
# A .env-ben kizárólag az új, saját adatbázist állítsd be.
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs storage/app/private bootstrap/cache
php artisan raero:install
php artisan raero:upgrade
php artisan raero:platform-password
php artisan config:clear
```

A jelszavakat a parancsok rejtett helyi bevitellel kérik; ne küldd őket beszélgetésben, ne töltsd fel őket GitHubra. A webkiszolgáló dokumentumgyökere a `public` könyvtár.

A GitHubra feltöltés **nem frissíti** a foglalo.digitmarketing.hu weboldalt. Az éles frissítést a felhasználó végzi a mentés után.

A november 7-i naptárkapcsolat, export, képek, meg nem jelenés és központi levelezés: [beállítás és használat](docs/NOVEMBER7.md).

[Gyors szalonfoglalás, párhuzamos szolgáltatások és csoportos órák](docs/FOGLALASI_MODOK.md).

[Gyorsabb használat, csoportos minimumlétszám és rögzítésre váró befizetések](docs/GYORS_HASZNALAT.md).

A november 8-i vendégfiók-, ismétlődés-, feketelista-, értékelés- és nyelvi bővítés: [útmutató](docs/NOVEMBER8_PLUS.md).
