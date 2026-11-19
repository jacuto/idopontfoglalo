# Hírlevélsablonok és vendégemlékeztetők – 2026. november 19.

## Hírlevelek

Öt szerkeszthető, szöveges sablon: szabad időpontok, akció/kupon, új szolgáltatás, fontos tájékoztatás, általános hírlevél. Váltás előtt megerősítés védi a meglévő tartalmat. Opcionális foglalási hivatkozás és elkülönített, sandboxolt előnézet. A tárgy és a mintaszövegek átírandók.

Kiküldéskor a szerver tisztítja a tartalmat, majd 600 px maximális szélességű, táblázatos, inline stílusú keretet és szöveges alternatívát készít. Nincsenek saját szkriptek, beágyazott űrlapok, távoli betűkészletek vagy képként megjelenített levéltartalom. A feladó postai adatai és a szolgáltatóspecifikus leiratkozási hivatkozás megmaradnak. A Brevo/Mailchimp saját kézbesítési és követési működése változatlan. Ezek nem garantálnak spammentes kézbesítést: a feladói domain hitelesítése (SPF/DKIM/DMARC), a feliratkozás és a tartalom is számít.

A címzett-előkészítés és a jóváhagyott küldés állapota helyben frissül; a lekérdezés nem indít küldést. A próbalevél továbbra is külön kérés. A végső küldés kifejezett jóváhagyást kér. Hosszú feldolgozásnál érthető figyelmeztetés jelenik meg. A háttérfeladatot továbbra is futtatni kell a szerveren.

A még el nem indított levelek szerkeszthetők; egy előkészített levél módosítása új előkészítést igényel, a korábbi külső piszkozatot nem küldi ki. Kiküldött levélből másolat készül. A törlés megerősítést kér és helyi elrejtést jelent; az auditadat és a külső kampány megmarad. Időzített, feldolgozás alatt álló vagy bizonytalan állapotú kampány közvetlenül nem törölhető. Időzített kampány előbb visszavonandó. A kiválasztott szolgáltatóhoz tartozó API-súgó és listaazonosító-magyarázat látható.

## Emlékeztetők

Vendégadatlap és szerkesztés: általános beállítás követése / kapjon / ne kapjon. Az egyedi bekapcsolás a beállított `channel` e-mail/SMS/mindkettő csatornát követi; a felületen ez látható. Küldőkapcsolat és SMS-egyenleg hiányában figyelmeztetés jelenik meg; a rendszer nem kerüli meg a küldési vagy előfizetési ellenőrzést.

Az Értesítések oldalon minden vendégemlékeztető központilag szüneteltethető, az egyedi beállítások megőrzésével. Az egyedi beállítású vendégek listázhatók, a beállítások megerősítés után tömegesen az általánosra állíthatók. Csak időpont előtti emlékeztetőre hat; foglalás-visszaigazolást és lemondást nem kapcsol ki. A várakozó küldéseket küldés előtt újra ellenőrzi; a módosítás érvényteleníti és újraütemezi a jövőbeni emlékeztetőket. Már elküldött levelet nem lehet visszahívni.

## További felületi módosítások

Az SMS-díj az aktuális adminisztrátori beállításból, röviden jelenik meg. Kuponbeváltás: „Beváltom”, a mezőhöz igazítva. Kupon/bérlet kártyákon törlés jobb felső sarokban, szerkesztés jobbra az állapottal egy sorban. Érvényesség külön sorban, kuponoknál is; relatív bérletélettartamhoz nem találunk ki fix dátumot.

## Telepítés

A szokásos teljes forrásfrissítés során az éles `.env`, APP_KEY, adatbázis és `storage` maradjon meg. Új migráció: `marketing_campaigns.deleted_at` (nullable).

```bash
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

A tárhely cronfeladatában percenként legyen:

```cron
* * * * * cd /home/digitmar/idopontfoglalo-php && php artisan schedule:run >> /dev/null 2>&1
```

Ha már van ilyen cron, ne duplikáld. Ezzel a hírlevelek, emlékeztetők és más ütemezett feladatok automatikusan futnak. Az éles cronbeállítást ez a forrásfrissítés nem módosítja.
