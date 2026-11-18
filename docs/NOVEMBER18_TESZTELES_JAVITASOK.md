# Tesztelés során jelzett felületi javítások – 2026. november 18.

## Foglalóoldal

- A foglalópanel az oldal részeként görgethető. Nem ragad a képernyőnél magasabb helyre, bérlet és kupon megnyitásakor is elérhető a foglalás gombja.
- Asztali nézetben a szolgáltatások és a munkatársak két oszlopban, egységes magasságú kártyákon látszanak. Mobilon egy oszlopot használunk.
- 15-nél több szolgáltatás esetén 15 elem/oldal, Előző/Következő lapozással. A lapozás nem építi újra a foglalóűrlapot, a kiválasztott szolgáltatások megmaradnak.
- Hosszú munkatárs-/szolgáltatásfelsorolás legfeljebb két sor. Ha minden aktív elem hozzárendelt, „Összes szolgáltatás” / „Minden munkatársnál elérhető”. Máskülönben külön ablakban megnyitható a teljes felsorolás, így a kártya nem nő meg. Ugyanez az admin kártyáin is működik.
- A kupon és bérlet külön-külön jelenik meg a kiválasztott szolgáltatásoknak megfelelően. A publikus ellenőrző végpont nem adja vissza a kuponkódokat. A bérletlista kizárólag a bejelentkezett vendég megfelelő szolgáltatónál felhasználható, maradék alkalommal és érvényes időszakkal rendelkező bérleteit tartalmazza. A végleges árat és jogosultságot továbbra is a szerver ellenőrzi.
- Az Ár ellenőrzése gomb a kuponmező mellett van. Belépve a Belépés/Regisztráció hivatkozás eltűnik.

## Naptár és ablakok

- Összes kijelölése / Kijelölések törlése a naptárválasztóban. Üres kijelöléskor egy naptár sem látszik és külön tájékoztató jelenik meg.
- A foglalásrészletek és befizetés ablakának bezáró × gombja jobb felül van. A befizetés és állapotmódosítás közös gombsorba kerül.
- A befizetési listában az előre kiválasztott foglalás akkor is bekerül a látható találatok közé, ha eredetileg az első húszon kívül lenne. A lista odagörget.
- A mintaadatok korábbi, nem támogatott `notificationRules.provider.reminder` mezőjét az adatbetöltés figyelmen kívül hagyja. Így a már meglévő TESZT fiókok egyéni módra váltásához sem kell új adatfeltöltés. A további értesítési választások és munkatársak megmaradnak.

## Statisztika

- Forintbeosztás a napi vonaldiagram Y tengelyén. Kurzor, érintés vagy billentyűzetes fókusz kiírja a dátumot és összeget.
- Nagyobb kördiagram, keskenyebb görgethető jelmagyarázat. A szeletek és a jelmagyarázat sorai összekapcsoltan kiemelhetők, névvel, összeggel és százalékkal.
- Négy látogatási mutató egy sorban nagy képernyőn; kisebb kijelzőn tördelve. A „Nem jelent meg” szám a tényleges `no_show` állapotot is kezeli. A munkatársszűrő a látogatási mutatókra is vonatkozik.
- Kattintható Foglalások / Beérkezett összeg oszlopfejlécek a munkatársi összesítésben.

## Kuponok, bérletek, fiók

- Térköz a kínálati kártyák között, megerősítést kérő törlés mindkét sablontípusnál. A törlés archiválás: nem módosít korábbi foglalási árakat és nem veszi el a már kiadott bérleteket; új kiadás/felhasználás a törölt sablonnal nem indítható. A már kiadott bérlet saját korábbi feltételeivel továbbra is használható.
- Bérlet kiadásánál gépeléses vendégkereső. A találatot ki kell választani; azonos nevű vendégnél az elérhetőség segít.
- A fiók fő tartalmában: Szolgáltatóim, Közelgő foglalások, Korábbi és lezárt foglalások. A hírlevél-beállítások közös, lejjebb lévő részbe kerülnek, külön hozzájárulásokkal.

## Üzenetek

- Nagy kijelzőn beszélgetéslista balra, beszélgetés jobbra. Kis kijelzőn a kiválasztás odagörget a beszélgetéshez. A keresés és rendezés felül, egy helyen található.
- Olvasatlan jelölés a listában, pontos elolvasási idő a belső üzenetnél. Nyitott beszélgetésnél 20 másodpercenként csak az állapot frissül, a megkezdett levél szövege nem vész el. E-mail megnyitását nem követjük.
- Feladó neve és kör alakú képe/monogramja. Szolgáltatóként a vendégnév a meglévő vendégadatlapra vezet; vendégként a szolgáltató neve a foglalóoldalra, a saját név a saját fiókhoz.
- Megerősítést kérő üzenettörlés a saját nézetből. A másik fél példányát, az elolvasási előzményt és a már elküldött e-mailt nem vonja vissza. Szerveroldali szolgáltató- és vendéghatárok ellenőrzik a műveletet.

## Telepítés és ellenőrzés

Nincs új migráció és nincs új függőség. A teljes csomagot kell frissíteni (két új publikus fájl és szerveroldali végpontok is változtak). A `.env`, APP_KEY, `storage` és az adatbázis megmarad.

```bash
php artisan optimize:clear
php artisan raero:check
```

Utána Ctrl+F5. Nem szükséges új mintaadatot betölteni. Az éles tárhelyre ez a fejlesztés nem futtat automatikus telepítést, értesítést vagy fizetést.

Ellenőrzés: teljes PHP-tesztcsomag, külön jogosultsági/archiválási/kupon-bérlet és módváltási tesztek; valódi helyi tesztfiókokkal asztali és mobilos böngészőpróbák. A listák meglévő böngészőoldali lapozási működése megmarad.
