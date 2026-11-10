# Alap, Pro, naptárbővítések és automatizmusok

## Díjak és hozzáférés

- **Alap: 4 000 Ft/hó**, egy naptárral, a korábbi alapfunkciókkal.
- **Pro: 6 000 Ft/hó**, egy naptárral. Pro funkció az automatikus foglalási emlékeztető, a több munkatárs és a munkatársi szerkesztő/admin jogosultság, a munkatársanként eltérő beosztás és az automatikus vendégszámlázás.
- **További naptár a Pro csomaghoz: +2 000 Ft/hó/naptár.** Két naptár összesen 8 000 Ft/hó; három naptár 10 000 Ft/hó.
- A próbaidő alatt a Pro funkciók kipróbálhatók. További naptár megrendeléséhez a Pro csomagot kell kiválasztani.
- Az SMS és a külső fizetési/számlázási szolgáltató használati díja külön tétel.

Az **Előfizetés** menüben választható csomag és rendelhető új naptár. A naptár oldalán is van **+ Új naptár** gomb. A díj elfogadása kötelező. A naptár külön munkatársi naptárként jelenik meg; a hozzá tartozó szolgáltatásokat a Szolgáltatásoknál, a belépést a Munkatársaknál lehet beállítani.

Az új díj a következő előfizetési időszaktól esedékes. Nincs időarányos vagy visszamenőleges terhelés, és a korábban kiírt havi tételek összege nem változik. A már előre rendezett átutalásos időszak végéig az eredeti díj marad. A Stripe rendszeres tételeit is frissítjük, azonnali terhelés nélkül. Átutalásnál a főadmin a havi díjtétel tényleges összegét írja jóvá, nem mindig 4 000 Ft-ot.

Megszakadt Stripe-válasz után ugyanaz a folyamat folytatható az Előfizetés menüből. A kérésazonosító és a Stripe idempotenciakulcs megakadályozza, hogy az ismételt kattintás újabb naptárat vagy díjtételt hozzon létre. Folyamatban lévő módosítás mellett újabbat nem indítunk.

Az archiválás nem mondja le a megvásárolt naptárhelyet. A bővítés lemondását az üzemeltetővel kell egyeztetni. Csomagcsökkentés nem használható az első esedékes Pro díj megkerülésére; több aktív naptár vagy bővítés mellett az Alapra váltást szintén egyeztetni kell.

Korábbi, még előfizetésre át nem alakított fiókok hozzáférését nem vesszük el. Frissítéskor a meglévő naptárkeret megmarad. A már próbaidős fiókok próbaideje nem indul újra.

## Munkatársi beosztás

A **Munkatársak → Heti beosztás** gombbal külön nyitás/zárás és szabadnap adható a hét mind a hét napjára. Az egyedi beosztás kikapcsolásakor a közös nyitvatartás érvényes.

A naptár **Nyitvatartás és szünetek** ablakában a napi nyitvatartás is hozzárendelhető egy munkatárshoz. A sorrend: munkatárs konkrét napi kivétele → közös napi kivétel → munkatárs heti rendje → közös alapnyitvatartás. Az ebédszünetek, szabadságok és egyéb foglalások ezután tovább szűkítik az időpontokat. A vendégoldal és a közvetlen API ugyanazokat a szabályokat használja. Meglévő foglalást a beosztás módosítása nem töröl; az ütközések megjelennek mentés után.

## Automatikus emlékeztetők

**Beállítások → E-mailes értesítések és emlékeztetők:** bekapcsolható a vendég emlékeztetése, megadható az előzetes óraszám és az e-mail/SMS csatorna. A funkció Pro vagy aktív próbaidő alatt működik. Módosításkor a jövőbeli foglalások értesítéseit újraütemezzük; lemondott, áthelyezett vagy már elavult értesítést nem küldünk ki. E-mailhez központi SMTP, SMS-hez központi SMS-kapcsolat szükséges.

## Automatikus számlázás

A **Beállítások** számlázási részén a számlázó kiválasztása mellett kapcsolható be. Ötpercenként ellenőrizzük a teljesen rendezett foglalásokat. Előlegről, részfizetésről vagy hiányos számlázási adatokkal nem készül automatikus számla.

A vevőadatok a befizetés mentett számlázási adataiból, ennek hiányában a kapcsolt vendég saját számlázási profiljából jönnek. Számlázókapcsolat és hiánytalan név/cím szükséges. A **Befizetések → Automatikus számlázás – teendők** rész jelzi a hiányokat. Bizonytalan számlázói válasz után nincs automatikus újraküldés: előbb egyeztetni kell a számlázóval. A számlakiállítás foglalásonként zárolt és ismétlés ellen védett.

## Üzemeltetés

A meglévő percenkénti `php artisan schedule:run` cron szükséges az automatikus feladatokhoz. Ha már be van állítva, nem kell új cron. Kézi ellenőrzéshez:

```bash
php artisan raero:deliver
php artisan raero:auto-invoices
```

A központi Stripe ár továbbra is a 4 000 Ft/hó alapár. A Pro és a naptárbővítés rendszeres árát az alkalmazás állítja össze, HUF-ban. Tesztekben minden külső számlázás és Stripe-hívás helyettesített; a fejlesztés nem indít éles terhelést vagy levélküldést.
