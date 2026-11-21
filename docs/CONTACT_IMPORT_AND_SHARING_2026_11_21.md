# Vendégimport és foglalóoldal-megosztás – 2026. november 21.

## Felület

A szolgáltatói felső értesítések alatt két külön, bezárható javaslat található. A telefonos névjegyek átvétele csak legfeljebb 650 px-es nézetben jelenik meg; a Google-/Outlook-névjegyek átvétele számítógépen is hasznos. Az × és a Később megcsinálom gomb 7 napra, a szolgáltatóhoz mentve rejti el az adott javaslatot. Sikeres import az adott forrás javaslatát megszünteti. Szerkesztő munkatárs nem importálhat.

A Vendégek → Importálás menüpont mindig elérhető, bezárt javaslat után is. A korábbi Ráérő CSV-import külön, változatlan lehetőségként megmaradt.

Telefonon a Contact Picker API-t támogató, biztonságos környezetű böngészőben a rendszer saját névjegyválasztója indul. Ez nem ad korlátlan címjegyzék-hozzáférést: a felhasználó választja ki az átadható névjegyeket. A telefon mind kijelölése lehetőségét a weboldal nem tudja kikényszeríteni. Nem támogatott böngészőben, így jellemzően iPhone-on, VCF-fájl választható. Valódi telefonos támogatást az adott készüléken is ellenőrizni kell; az automatizált tesztben a natív választó eredményét helyettesítettük.

Az átadott névjegyek alapból kijelöltek. 20-as lapozás, név/telefon/e-mail keresés, kijelölés törlése, szűrt találatok kijelölése és adatok javítása segít. Csoportválasztáskor csak az adott csoport névjegyei lesznek kijelölve. A keresés önmagában nem törli a többi oldalon lévő kijelölést; a számláló a teljes kijelölést mutatja.

Fájlok: VCF, CSV, TSV és XLSX, legfeljebb 5 MB / 2000 névjegy. Táblázatnál oszlopok rendelhetők teljes névhez vagy vezetéknév+keresztnévhez, telefonhoz, e-mailhez és csoporthoz. Magyar, Google- és Outlook-fejléceket felismerünk. XLSX: első munkalap, közös és inline szövegek; képleteket nem futtatunk, makrót nem olvasunk. Régi XLS-fájlt előbb XLSX/CSV-ként kell menteni. Több telefon/e-mail esetén az első, VCF-nél lehetőség szerint a mobil/elsődleges kerül át; az előnézetben módosítható.

## Biztonságos egyezés

Normalizált telefonszám vagy kisbetűsített e-mail alapján kizárólag a saját szolgáltató aktív vendéglistájában keresünk. Új vendéghez név és legalább egy érvényes elérhetőség szükséges. E-mail nem kötelező. A hiányzó mezők kiegészíthetők, meglévő név/telefon/e-mail nem íródik felül. Eltérő kapcsolati érték vagy több egyező vendég külön ellenőrzést igényel, nem importálható automatikusan. Telefonos becenév esetén a meglévő név marad meg. A forráson belüli ismétléseket jelezzük.

Az egyezések ellenőrzése még nem ment. A végső kijelölés és Importálás gomb ment csak. A szerver zárolt szolgáltatói állapotban újra ellenőrzi az egyezést; időközben megváltozott tételek kimaradnak, amit az eredmény számszerűen jelez. Ismételt kérés nem hoz létre másolatot. Sem vendégfiók-azonosító, sem emlékeztető-beállítás, sem hírlevél-hozzájárulás nem vehető át az importból. Más szolgáltató vagy globális regisztrált vendégfiók adatai nem jelennek meg. Az import nem küld SMS-t, e-mailt, meghívót vagy hírlevelet.

A szerveres előnézet és az ideiglenes felhős névjegyek titkosított, szolgáltatóhoz és munkamenethez kötött külön gyorsítótárban, 20 percig érhetők el. A meglévő schedule:run percenkénti cron mellett az új raero:contact-import-cleanup parancs ötpercenként törli a lejárt állományokat. Kézzel is futtatható. Cache-törlés után új előnézet szükséges. A felhős OAuth-hozzáférési token csak a lekérés alatt él a memóriában; nem mentünk tartós kapcsolatot vagy refresh tokent.

## Google és Microsoft beállítása

Főadmin → Kapcsolatok: Google-névjegyek importálása és Outlook-névjegyek importálása mezők. A titkos kulcsok a meglévő titkosított központi tárolóba kerülnek; csak a konfiguráltságuk látható. Alternatív .env kulcsok: CONTACTS_GOOGLE_CLIENT_ID / CONTACTS_GOOGLE_CLIENT_SECRET / CONTACTS_MICROSOFT_CLIENT_ID / CONTACTS_MICROSOFT_CLIENT_SECRET. A meglévő .env megőrzendő, majd php artisan optimize:clear. Ha nincs külön Google Contacts kulcspár, a közös Google-belépés kulcspárját használjuk.

Google: engedélyezd a People API-t. A webes OAuth kliens engedélyezett átirányítása: https://SAJAT-DOMAIN/auth/contact-import/google/callback. Kért hatókör kizárólag https://www.googleapis.com/auth/contacts.readonly. Az OAuth hozzájárulási képernyőn a hatókört, tesztfelhasználókat, közzétételt és az esetleg szükséges Google-ellenőrzést is be kell állítani. A közös Google-kliens használatakor az új callbacket a meglévők mellé kell hozzáadni. Mentett névjegyek és címkék jönnek át, nem a Gmail-üzenetek vagy az Other Contacts automatikus címei.

Microsoft: Entra alkalmazásregisztráció Web átirányítással: https://SAJAT-DOMAIN/auth/contact-import/microsoft/callback. Az alkalmazás kívánt ügyfélköre szerint engedélyezd a személyes Microsoft-fiókokat és/vagy munkahelyi fiókokat. Delegált Microsoft Graph Contacts.Read engedély és érvényes klienssecret szükséges. Munkahelyi környezet szervezeti adminengedélyt is kérhet. Névjegymappák, almappák és kategóriák támogatottak; levelezési mappákat és üzeneteket nem olvasunk. 2000 névjegynél nagyobb fióknál kisebb exportált csoporttal kell importálni; nincs csendes csonkolás.

Mindkét kapcsolat OAuth-state ellenőrzéssel és PKCE-vel működik. A külső fiók engedélyezése nem importál automatikusan: visszatérés után csoportot és személyeket lehet választani, majd az egyezéseket jóváhagyni. Éles Google-/Microsoft-fiókkal való végső próbához a fenti külső konfiguráció szükséges; a fejlesztési ellenőrzésekben a külső válaszokat helyettesítettük.

## Megosztás

A felső Foglalási oldalam gomb mellett kis megosztás ikon található. Támogatott eszközön a rendszer megosztója nyílik meg. A szolgáltató választja ki a WhatsAppot, Vibert, Messengert vagy más alkalmazást és a címzettet. Más esetben a teljes szöveg és link másolható. Megosztás megszakítása nem másol automatikusan.

Beállítások → Nyilvános adatlap → Elérhetőség és kereshetőség alatt szerkeszthető, legfeljebb 500 karakteres megosztási szöveg található. Az Adatlap mentése gomb menti. Alapérték: Foglalj hozzám időpontot online! A szolgáltató foglalási linkjét a rendszer adja hozzá; üres szöveg is megengedett. A felület a mentett szöveggel oszt meg. Nincs automatikus WhatsApp/Viber/SMS-küldés vagy üzenetenkénti Ráérő-díj.

## Ellenőrzés

PHP-tesztek: normalizálás, kizárólag üres mezők kitöltése, konfliktus, ismétlés, párhuzamos módosítás, újraküldés, lejárat, szolgáltatói elkülönítés, globális fiók adatainak védelme, nincs értesítés, fájlformátumok, OAuth-state/PKCE/hatókörök, csoportok és mappák. Böngésző: 390 px natív választó helyettesítéssel, tájékoztató bezárása és megőrzése, állandó menüpont, 20-as lapozás, kijelölés/szerkesztés/mentés, Outlook CSV-oszlopok és csoportválasztás, megosztási szöveg mentése, natív megosztás és vágólap.
