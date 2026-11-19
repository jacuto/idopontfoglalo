# Nyilvános foglalóoldal – dátum és leírások javítása

2026. november 19.

- A várólista ellenőrzése megvárja a szabad időpontok és szükség esetén a következő szabad nap keresését. A végleges megjelenített naphoz tartozó eredményt használja. Nap-, szolgáltatás- vagy munkatársváltáskor a régi eredmény eltűnik; késve beérkező válasz nem írhatja felül az új választást.
- A szerver csak akkor vizsgál további várólista-lehetőséget, ha a kiválasztott napot és megfelelő munkatársat tényleges, aktív foglalás érinti. Másik nap, másik munkatárs, lemondott időpont és még szabad párhuzamos férőhely önmagában nem indokol várólistát. A két próba ugyanazt a külső naptári pillanatképet használja.
- A szolgáltatások Bővebben gombja helyben nyitja le a teljes szöveget; Kevesebb gombbal visszacsukható. Nem nyit felugró ablakot. Lapozáskor is működik.
- A nyitvatartás kísérőszövege: „Az aktuális hét nyitvatartása”. A kiegészítő nyitvatartási leírás a nyitvatartáskártyán belül marad. A részletes bemutatkozás saját Bemutatkozás kártyába kerül, nem marad kereten kívül.

Ellenőrzés: 23 sikeres API-teszt, 177 állítás; böngészőben automatikus napváltás, későn beérkező válasz, pozitív és negatív várólista-eredmény, helyben lenyitás/visszacsukás, nyitvatartás és szövegdoboz, 390 px-es mobilnézet.

Teljes frissítési csomag, a korábbi módosításokkal együtt. A meglévő `.env`, APP_KEY, adatbázis és `storage` megőrzendő. Nincs új függőség vagy adatbázismódosítás.

```sh
cd /home/digitmar/idopontfoglalo-php
php artisan optimize:clear
php artisan raero:upgrade
php artisan raero:check
```

Böngészőben Ctrl+F5. A tesztelés nem módosította az éles tárhely adatait.
