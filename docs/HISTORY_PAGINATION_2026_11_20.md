# Lapozható előzmények és tömörebb vendégadatlap

## Oldalméretek

| Felület | Oldalanként |
| --- | ---: |
| Vendégadatlap – közelgő időpontok | 4 |
| Vendégadatlap – korábbi időpontok | 4 |
| Vendég saját fiókja – közelgő foglalások | 5 |
| Vendég saját fiókja – korábbi / lezárt foglalások | 5 |
| Kuponok | 10 |
| Bérlettípusok | 10 |
| Vendégadatlap – értékelések | 5 |
| Vendégadatlap – bérletek | 5 |
| Hírlevélelőzmények | 5 |
| Várólista | 10 |
| E-mail/SMS-küldési előzmények | 20 |
| SMS-egyenlegmozgások | 10 |
| SMS-feltöltések | 10 |
| Pontelőzmények | 10 |

Minden lista önállóan lapozható, a számozás legfeljebb 10 oldalszámot mutat egyszerre, előző/következő gombokkal. Egyoldalas és üres listánál nincs felesleges lapozó. A közelgő vendégidőpontok a legközelebbivel, a korábbiak a legutóbbival kezdődnek. Az időpontsorok alacsonyabbak; a négy vendégstatisztika asztalon egy tömör sor, mobilon két tömör sor.

A foglalás adatlapján a Lemondás mellett Link kimásolása gomb található. A vágólapra a szolgáltatóhoz tartozó vendégmódosítási URL kerül, nem küldünk üzenetet. A meglévő szövegmező marad, a böngésző vágólapjának hiányában kijelöléses másolási tartalék működik. Az időpontmező és az Időpont mentése gomb egy sorban van, mobilon is.

## Régebbi adatok elérése

A kampány-, várólista-, küldési és SMS-pénzügyi listák valódi szerveroldali lapozást kapnak. A korábbi 100/200/50 bejegyzésen túli adatok is elérhetők. A küldési előzmények várakozó/hibás szűrése a teljes saját előzményen történik, utána lapozunk; a szűrők darabszámai is a teljes saját listára vonatkoznak.

A pontelőzmények a szolgáltató zároltan kezelt JSON-állapotában vannak; ezeknél a szerver a teljes saját mozgássorból ad vissza 10 rekordot, változatlan teljes egyenleggel. A vendégadatlap, vendégfiók és kupon-/bérletkártyák a már elérhető adataikat a felületen lapozzák; nincs új határ, amely mögött elvesznek a régi sorok.

A lapozott API-k fix oldalméretűek, kliens nem kérhet korlátlan oldalt `perPage` paraméterrel. Hibás/negatív oldal elutasítva; a lista végén túli oldalt az aktuális utolsó oldalra igazítjuk. SQL-listáknál determinisztikus rendezés, szolgáltatóhoz kötött lekérdezések és összesítések vannak. A személyzeti/guest jogosultságok nem bővülnek. SMS-előzmény nem tartalmaz checkout URL-t, titkos pénzügyi részleteket vagy titkosított küldési payloadot.

A régi, `page` nélküli kampány-, várólista-, küldési és pontelőzmény-válaszok kompatibilisek maradnak. Új SMS-végpont: `/api/sms-wallet/history/{ledger|topups}?page=N`. A hírlevélszerkesztő külön `campaignId` lekérdezéssel a 100 legújabbinál régebbi kiválasztott levelet is vissza tudja kérni; visszalépéskor megőrizzük a lista oldalát. A megjelenítést kiegészítő két hírlevél-wrapper is továbbadja az oldalszámot. Az API-kulcs melletti mezősúgó is csak a kiválasztott szolgáltatót mutatja, szolgáltatóváltáskor frissül.

## Telepítés

Nincs új migráció vagy adatátíró lépés. Az éles `.env`, APP_KEY, adatbázis és `storage` megőrzendő.

```bash
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

A meglévő cron változatlanul marad. A frissítés a pontgyűjtést, pénzügyi összegeket és a küldési állapotokat nem módosítja.

## Ellenőrzés

- Új backendtesztek: 5 teszt, 40 ellenőrzés. A 100. kampánynál, 200. várólistásnál, 100. küldésnél, 100. SMS-egyenlegmozgásnál, 50. feltöltésnél és 50. pontmozgásnál régebbi adatok, fix oldalméretek, üres/túlindexelt oldalak, teljes szűrés, egyenlegmegőrzés és jogosultság/szolgáltatóhatár ellenőrizve.
- Teljes backendfutás: 337 teszt, 2477 ellenőrzés, 4 MySQL-specifikus kihagyás; hiba nélkül.
- Chromium helyi mintadatokkal: 4/4 időpont, 5 értékelés/bérlet, 10 kupon/bérlettípus, kampány 21. oldala és visszalépés, várólista 21. oldala, 20 küldési rekord szűréssel, 10 SMS-/pontmozgás, egymástól független lapozók, másolás és időpontmező/mentés elrendezése.
- Vendégfiók két külön 5-ös listája felületi foglalásfixture-rel; 390 px-es vendég-, pénzügyi, vendégadatlap- és foglalásablak-ellenőrzés. A mintadat és teszteszközök nem kerülnek a kiadási ZIP-be. Valódi küldés, foglaláslemondás vagy fizetés nem történt.
