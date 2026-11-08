# Főadminmenü és vendégoldali navigáció – 2026. november 8.

## Főadmin

A `/foadmin` külön üzemeltetői belépése megmaradt. Belépés után bal oldali menü jelenik meg, a szolgáltatói munkatérhez hasonló elrendezéssel. Mobilon a felső menügomb nyitja és zárja az oldalsávot.

- Szolgáltatók: keresés névre, azonosítóra és állapotra; találatszámláló; új szolgáltató felvétele; naptárkeret módosítása.
- Befizetések: átutalás jóváírása és korábbi előfizetési befizetések.
- Üzemeltető adatai: saját számlázási és banki adatok.
- Szolgáltatói belépési jogosultságok, automatikus HTML-levelek, központi kapcsolatok, levelezés, előfizetési számlák és hírlevelek: a korábbi funkciók a bal oldali menüből nyithatók meg.

A keresés a főadmin által már olvasható szolgáltatólistát szűri; a jogosultságok nem változtak. A kereső kezeli az ékezet nélküli beírást is.

## Vendégek és nyilvános menü

A vendégeknek szóló teljes oldalakon felső navigáció található: **Szolgáltató, Árak, Kapcsolat, Időpontfoglalás, Saját fiókom**. A foglalóoldalon, a vendégfióknál és a titkos foglaláskezelő oldalon is elérhető. Mobilon lenyitható.

| Cím | Tartalom |
|---|---|
| `/` | Belépés nélkül közös kezdőoldal; meglévő szolgáltatói munkamenettel a naptár marad |
| `/szolgaltatok` | Nyilvános szolgáltatókereső, közvetlen foglalási linkekkel |
| `/szolgaltato` | Szolgáltatói vállalkozás kiválasztása és csatlakozási kapcsolat |
| `/szolgaltato?provider=2` | Az adott szolgáltató belépése; meglévő munkamenettel saját munkatér |
| `/arak` | Szolgáltatói alapcsomag és a vendégoldal díjmentessége |
| `/kapcsolat` | Hivatkozás az üzemeltető valódi elérhetőségeire |

Az eddigi `/?provider=…` és `/foglalas?provider=…` címek tovább működnek. A nyilvános önkiszolgáló szolgáltatói regisztráció nem része ennek a navigációs frissítésnek: új vállalkozást továbbra is a főadmin hoz létre; a csatlakozási oldal kapcsolatfelvételt kínál.

A nyilvános szolgáltatólista név, szakma, cím és szolgáltatás szerint kereshető, 24-es oldalakra bontott. Csak aktív, foglalható szolgáltatáskatalógussal rendelkező vállalkozásokat mutat. Vendégek, belépési adatok, értesítési címek, előfizetési vagy bankadatok nem kerülnek ebbe az API-ba. A megjelenített cím kizárólag a szolgáltató nyilvános adatlapjából származik. A találatok legfeljebb egy percig gyorsítótárazódnak.

## Naptár

A naptárból megnyitott új időpontnál a rejtett napot a **Nap módosítása (2026. 11. 08.)** felirat jelzi. A dátumválasztó változtatásakor a felirat is frissül. A napi, heti és havi nézet, a többnapos kijelölés és a nyitvatartási beállítások megmaradtak.

A mai nap fejlécét felső színes jelölés különíti el. A napi és heti időrácsban az aktuális magyarországi időt rózsaszín vonal mutatja; percenként frissül, az idősávok kattintását nem takarja el. Csak a megjelenített időtartományon belül látszik.

## Frissítés

Fájl- és adatbázismentés után töltsd fel a csomagot, a saját `.env`, `APP_KEY` és `storage` megőrzésével. Nincs új környezeti változó vagy új Composer-függőség. A szokásos frissítőparancs továbbra is használható:

```sh
cd /home/digitmar/idopontfoglalo-php && \
composer install --no-dev --prefer-dist --optimize-autoloader && \
php artisan raero:upgrade && \
php artisan raero:check
```

Ezután töltsd újra az oldalt. A fejlesztést helyi tesztkörnyezetben ellenőriztük; a csomag feltöltése külön lépés.
