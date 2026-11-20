# Sütikezelés és mobilos felület – 2026. november 20.

Minden alkalmazásoldal és jogi/tájékoztató oldal közös sütikezelőt kapott. Első alkalommal egyenrangú elutasítás, részletes beállítás és elfogadás látható. Alapértelmezés szerint csak a szükséges tárolás engedélyezett. A választás ezen a böngészőn, az adott webhelyen 180 napig marad meg (localStorage: raero-cookie-consent, verzió, időpont, kategóriák). Más eszköz, törölt tároló, lejárat vagy módosított hozzájárulási verzió esetén új választás szükséges. Tárolási tiltáskor a választás az aktuális oldal élettartamáig működik.

A bal alsó kisméretű Sütik gomb minden oldalon újranyitja a beállítást. Kategóriák: statisztika, hirdetés, külső tartalmak. A Google-térképek engedély nélkül helyettesítő tájékoztatást kapnak; a külső útvonalhivatkozás továbbra is használható. Az elutasítás nem akadályozza a foglalást és a belépést.

## Google-kapcsolat

A meglévő .env fájlt meg kell tartani. Opcionálisan GOOGLE_ADS_ID=AW-… és GOOGLE_ANALYTICS_ID=G-… állítható be, utána php artisan optimize:clear. Üres vagy érvénytelen azonosítóval nem indul mérés. Nincsenek beégetett azonosítók, konverziós címkék vagy automatikus foglalási konverziók; ezek a későbbi kampánybeállítás részei.

Basic Google Consent Mode v2: mind a négy jelzés denied kezdetben. Google tag hálózati kérés csak a megfelelő kategória engedélyezése után indul. Hirdetési és statisztikai jelzések külön frissülnek. A hozzájárulás visszavonásakor a felismerhető első félhez tartozó Google mérési sütiket töröljük és az oldalt újratöltjük, hogy a már futó mérési kód leálljon. Más domainek sütijeit a böngésző szabályai miatt az oldal nem tudja törölni. A többi nyitott lap a választás változásakor újratöltődik.

Új követőkódot nem szabad ettől függetlenül beilleszteni. A jövőbeli kapcsolatoknak a window.raeroConsent.allowed('marketing'|'analytics'|'external') ellenőrzést és a raero:consentchange eseményt kell használniuk. A jogi tájékoztató sütirészét aktualizáltuk; új adatkezelés, szolgáltató vagy cél esetén a tájékoztatót és a hozzájárulás verzióját is aktualizálni kell. Ez technikai hozzájárulás-kezelés, a teljes adatkezelési megfelelőséget a tényleges üzemeltetés és szerződések alapján kell kialakítani.

## Korábban összegyűjtött javítások

- Várólista gomb valódi kattintáskor page=1 értéket küld; későn beérkező naptári emlékeztetők nem írják felül a várólistát.
- Mobilon a bezárt menü nyitógombja a fejlécben kap helyet, a tartalom nem tart fenn üres bal oldali sávot. A nyíl középre igazított. A tablet ikonos oldalsáv 60 px széles, középre igazított ikonokkal.
- A foglalási események szűrői megfelelően tördelődnek; dátummezők nem lógnak ki.
- Másik munkaterületi oldalra vagy beállítási főfülre váltás felülről indul.
- Minden alkalmazásdialog fixen az aktuális nézet közepén nyílik, belső görgetéssel és viewporthoz korlátozott mérettel.
- Mobilon a vendéglistában név és telefonszám marad, a név nyitja az adatlapot. A foglalási eseményeknél vendég, időpont és állapot marad; a foglalás neve nyitja a részleteket. Az asztali oszlopok változatlanok.
- A nyilvános kuponok és bérletek színesebb kártyát és kiemelt árat kapnak; a cím és kártyák közötti üres hely csökkent.
- A napi/heti/havi naptárnézetet a böngésző megjegyzi. Átméretezés nem írja felül a választást. Heti mobilnézetben az olvasható naposzlopok oldalra görgethetők.

## Ellenőrzés

Böngészőteszt: elutasítás és megőrzés nyilvános/jogi/admin oldalon, kategóriánkénti Google-betöltés, térkép tiltás/engedélyezés, visszavonás; 390 px szűrők és listák, görgetés visszaállítása, vendégadatlap pozíciója, naptárnézet újratöltés után, 850 px oldalsáv. A Google kérések tesztben elfogva, külső mérés és hirdetésküldés nélkül. A várólista valódi gombnyomását és késleltetett emlékeztetőválaszát külön teszt ellenőrzi.
