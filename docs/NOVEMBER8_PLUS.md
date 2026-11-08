# Vendégfiókok, ismétlődő foglalások és háromnyelvű felület

## Frissítés

A módosítások a meglévő rendszerhez adódnak hozzá. Az `.env`, az APP_KEY, az adatbázis és a `storage` maradjon meg. Nincs új Composer-függőség és nincs szükség Node buildre. A teljes csomagból a `public`, `lang`, `app`, `resources`, `routes` és `database` változásait is fel kell tölteni.

```bash
cd /home/digitmar/idopontfoglalo-php &&
composer install --no-dev --prefer-dist --optimize-autoloader &&
php artisan raero:upgrade &&
php artisan raero:check
```

Utána a böngészőben Ctrl+F5. A meglévő percenkénti `php artisan schedule:run` cron szükséges a levelek és automatikus értékeléskérések feldolgozásához. Ezek nem a weboldal megnyitásakor küldődnek.

## Hagyományos vendégbelépés

A Saját fiókom oldalon e-mail/jelszavas belépés, regisztráció, elfelejtett jelszó és megerősítő levél újrakérése is van, a közösségi belépés mellett. A regisztráció e-mail-címmel indul. A 24 órás megerősítő hivatkozás megnyitása után az e-mail tulajdonosa adja meg a nevét, opcionális telefonszámát és legalább 12 karakteres jelszavát. Megerősítés előtt nem jön létre belépett munkamenet. A jelszó-visszaállító hivatkozás egy óráig és egyszer használható; a jelszóváltás a korábbi jelszavas munkameneteket is érvényteleníti.

A főadmin központi SMTP-beállítása szükséges. A levélsablonok között új elem a vendég e-mail-megerősítése, jelszó-visszaállítása és értékeléskérése. SMTP nélkül a küldés várakozik; a rendszer nem állítja, hogy sikeresen kézbesített. A regisztrációs levél nem jelent automatikus foglaláskapcsolást: meglévő közösségi fiókot vagy korábbi foglalást pusztán egyező e-mail alapján nem vesz át az új fiók.

## Ismétlődő időpontok

Szolgáltatói új időpontnál az **Ismétlődés** bekapcsolásával napi, heti, kétheti vagy havi sorozat készíthető, összesen 2–52 alkalommal. A kezdési idő magyarországi helyi idő marad. A havi sorozat rövid hónapnál a hónap utolsó napjára esik, utána az eredeti naphoz tér vissza. Nem létező óraátállítási időpontot a rendszer visszautasít.

Minden alkalomra ugyanaz a nyitvatartás-, kapacitás-, szünet- és foglalásellenőrzés fut. Ha valamelyik alkalom nem fér el, a teljes mentés visszagörgetődik: nem marad fél sorozat, elfogyasztott bérlet vagy kiküldendő értesítés. A részleteknél a sorozat jövőbeli alkalmai együtt lemondhatók; a múlt és a befizetések megmaradnak. Csoportos szolgáltatásnál továbbra is külön meghirdetett alkalmakat kell létrehozni. Vendég saját maga nem hozhat létre automatikus sorozatot.

## Feketelista

A Vendégek oldalon a vendég részleteinél tiltható vagy újra engedélyezhető az online foglalás; külön lista is elérhető. A megjegyzés belső adat. A tiltás kizárólag az adott szolgáltatóra vonatkozik, a megismert fiókazonosítóval és normalizált elérhetőségekkel ellenőriz. Korábbi időpontot nem töröl, és a szolgáltató kézzel továbbra is rögzíthet foglalást. Ez a megismert adatokra vonatkozó tiltás, nem személyazonosság-ellenőrzés.

## Ügyfélvélemények

Lejárt, megerősített és **megjelent** állapotú foglalás részleteinél egyszer kérhető értékelés e-mailben. Az alapbeállításoknál az automatikus kérés külön bekapcsolható, alapból ki van kapcsolva; az alap késleltetés 24 óra, 0–720 órára állítható. Bekapcsoláskor nem indul tömeges levélküldés a régi foglalásokra. Lemondott és nem jelent meg alkalmakra nem megy értékeléskérés.

A vendég a foglalás saját titkos kezelőhivatkozásán egyszer adhat 1–5 csillagot és szöveges véleményt. A kereső csak ténylegesen beküldött értékelések átlagát és darabszámát mutatja. A levélküldés nem automatikusan nyilvános ajánlás vagy névközzététel.

## Nyelvek és nyilvános oldalak

A fejléc HU / EN / DE választója a felületi szövegeket váltja, alapból magyarul. A választás böngészőnként megmarad. A nevek, bemutatkozások, szolgáltatások saját elnevezései és más felhasználói tartalmak nem kapnak gépi fordítást. A vendég új megerősítő, jelszó-visszaállító és értékeléskérő rendszerleveleinek alapváltozatai angolul és németül is elérhetők; a főadmin által mentett saját HTML-sablon az irányadó.

A szolgáltatókeresőben kategória, település és név szerinti szűrés, engedélykéréses helymeghatározás, rendezés, törölhető szűrők és valódi értékelési összesítés segít. A Kapcsolat és Árak oldalak új megjelenést kaptak.

Az Alap csomag **4 000 Ft/hó**, egy naptárral; a Csapat és Egyedi csomag ajánlatkéréses. A meglévő előfizetés-kezelés díját ez nem módosítja automatikusan, ajánlatkérésből nem lesz automatikus fizetős előfizetés. A többletnaptár, SMS és külső pénzügyi szolgáltatók költségei elkülönítve szerepelnek; nincs kitalált ingyenes SMS-keret vagy átvett versenytársi ár.
