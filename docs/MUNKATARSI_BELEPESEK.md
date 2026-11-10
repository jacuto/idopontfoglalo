# Munkatársi belépések

Több munkatársas vállalkozásnál a **Munkatársak → Szerkesztés → Belépési adatok és jogosultság** fülön adható külön belépés. Nincs külön munkatársi belépéslista. Az **Alapadatok** fülön a név, szakterület, elérhetőségek, bemutatkozás és profilkép módosítható. A tulajdonos meglévő belépése továbbra is működik.

- **Szerkesztő:** csak saját foglalások, azokhoz tartozó vendégek elérhetőségei, saját időpontok felvétele, módosítása és lemondása. Nincs hozzáférése a többi munkatárs vendégeihez, vállalkozási beállításokhoz vagy pénzügyekhez.
- **Admin:** a vállalkozás teljes kezelése, beleértve a beállításokat és a munkatársi jogosultságokat. Nem főadmin.

A tulajdonos/admin megadja az e-mail-címet és a legalább 12 karakteres induló jelszót. A rendszer nem küld automatikusan jelszót e-mailben. A munkatárs a **Szolgáltatói belépés** oldalon léphet be; saját jelszavát módosíthatja, és az elfelejtett jelszó funkciót is használhatja. Utóbbihoz működő központi e-mail-küldés és az ütemező szükséges. Több vállalkozáshoz érvényes belépésnél munkaterület-választás jelenik meg.

Jogosultság-, jelszó- vagy fiókmódosításkor a korábbi munkatársi munkamenetek érvénytelenednek. Archivált munkatárs nem léphet be. Egyéni vállalkozásra váltáskor a szerkesztői munkatársi belépés szünetel; az admin továbbra is kezelheti a vállalkozást.

A szolgáltatói belépéshez saját vendégprofil is tartozik. A **Saját vendégfiókom** linken foglalhat más szolgáltatóhoz is, majd visszatérhet a munkaterületre. Más létező vendégfiókot nem kapcsolunk hozzá pusztán azonos e-mail-cím alapján.

## Frissítés

A `2026_11_09_000013_add_provider_members` migráció új táblát és új, opcionális jelszó-visszaállítási hivatkozást ad hozzá. Nem töröl meglévő adatokat. Fájlfeltöltés után `php artisan raero:upgrade`, majd `php artisan raero:check` szükséges.

## Elérhetőségek és próbálkozási korlát

A formázott magyar telefonszámokat mentéskor egységesítjük: például `+36 (30) 123-4567` → `06301234567`. A javíthatatlan számoknál hiba jelenik meg. A megjelenítés továbbra is tagolt lehet. A próbálkozási korlát elérésekor az üzenet a tényleges hátralévő másodperceket mutatja; a válasz `Retry-After` fejlécet is ad.

Új munkatárs felvételekor ugyanabban az ablakban a **Belépési adatok és jogosultság** fülön a **Belépés engedélyezve** jelölővel megadható a jogosultság és a jelszó. A belépés opcionális: nélküle is megjelenhet a munkatárs a foglalóoldalon. Ilyenkor az e-mail-cím kötelező. A munkatárs és a belépés egyetlen tranzakcióban készül el: hibás jelszó vagy betelt naptárkeret esetén egyik sem jön létre. Meglévő munkatársnál ugyanez a fül kezeli a fiókot. Üres jelszómezővel a meglévő jelszó megmarad; a belépés a pipa kivételével kikapcsolható. A belépési e-mail külön kezelhető az alapadatoknál megadott kapcsolattartási e-mailtől. Egyszerű profilfrissítés nem érvényteleníti a munkatárs munkamenetét.
