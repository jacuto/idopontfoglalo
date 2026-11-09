/* User-facing capabilities, grouped by task. Platform-only tools are labelled separately. */
const pricingFeatureGroups=[
 ['Foglalás és vendégfiók',[
  'Saját, nyilvános online foglalóoldal',
  'Szolgáltatókeresés név, kategória és település szerint, helymeghatározással',
  'Foglalás regisztráció és belépés nélkül is',
  'Több szolgáltatás kiválasztása egy foglaláshoz',
  'Szabad időpontok és a legközelebbi szabad nap keresése',
  'Foglalás kezelése és lemondása saját visszaigazoló hivatkozással',
  'E-mailes vendégregisztráció, e-mail-megerősítés és jelszó-visszaállítás',
  'Google-, Facebook- és Apple-belépés vendégeknek és szolgáltatóknak',
  'Vendégprofil, profilkép, elérhetőségek és külön számlázási adatok',
  'Elérhetőségek megjegyzése és automatikus kitöltése',
  'Aktuális és korábbi foglalások több szolgáltatónál, egy vendégfiókban',
  'Magyar, angol és német felület'
 ]],
 ['Naptár és időbeosztás',[
  'Napi, heti és havi naptárnézet, gördülő hét nappal',
  'Gyors időpontfelvitel, meglévő vendég keresése és alapértelmezett szolgáltatás',
  'Ismétlődő időpontok napi, heti, kétheti és havi gyakorisággal',
  'Ismétlődő sorozat kezdőnappal, végdátummal, darabszámmal vagy folyamatosan',
  'Jóváhagyás, elutasítás, lemondás és az időpontok áthelyezése',
  'Megjelent és nem jelent meg állapot, opcionális meg nem jelenési díjjal',
  'Szokásos nyitvatartás és egyedi napi nyitvatartás',
  'Több nap együttes kijelölése a nyitvatartás módosításához',
  'Ebédszünet, teljes napos szabadság és rövidebb időblokkok',
  'Állítható időpontkezdési lépésköz, egész órás kezdéssel is',
  'Naptárletöltés és nyomtatható, letölthető foglalási QR-kód'
 ]],
 ['Csapatok és összetettebb szolgáltatások',[
  'Több munkatárs és külön kezelhető munkatársi naptárak',
  'Külön munkatársi belépések: szerkesztői és adminjogosultság',
  'Szerkesztőként kizárólag saját foglalások kezelése',
  'Szolgáltatói belépéssel saját vendégfoglalások is',
  'Munkatársankénti szolgáltatások és időbeosztás',
  'Egyéni vállalkozói mód az elsődleges szakemberrel',
  'Párhuzamosan végezhető szolgáltatások és kapacitáskezelés',
  'Munka–hatóidő–munka szakaszok megadása',
  'Meghirdetett csoportos alkalmak és férőhelykezelés',
  'Csoportos minimumlétszám és teljesítési határidő',
  'Elmaradó csoportos alkalmak kézi és automatikus lemondása, értesítéssel',
  'A nem használt összetett funkciók kikapcsolása a beállításoknál'
 ]],
 ['Vendégkezelés és kapcsolattartás',[
  'Kereshető vendéglista, foglalási előzmények és látogatási összesítők',
  'E-mail- és telefonszám-ellenőrzés, egységes telefonszámformátum',
  'Azonos elérhetőségekkel létrehozott új vendégmásolatok megelőzése',
  'Feketelista a saját vállalkozás online foglalásaihoz',
  'Hívásgombok és belső üzenetváltás a kapcsolt vendégfiókokkal',
  'Foglalási értesítések és emlékeztetők e-mailben vagy SMS-ben',
  'Választható vendégmeghívó e-mailben vagy SMS-ben',
  'Kézi és automatikus értékeléskérés igazolt látogatás után',
  'Nyilvános vendégvélemények és belső vendégértékelések',
  'Kapcsolatfelvétel az üzemeltetővel a saját fiókból'
 ]],
 ['Kuponok, bérletek és pénzügyek',[
  'Százalékos és összegalapú kuponok, érvényességgel és felhasználási korlátokkal',
  'Kuponok kijelölt szolgáltatásokra, első alkalomra vagy nyilvános ajánlatként',
  'Alkalmas bérletek, felhasználható szolgáltatásokkal',
  'Bérletérvényesség konkrét dátummal vagy az első használattól számítva',
  'Kuponkedvezmény és bérletfelhasználás követése a foglalásnál',
  'Befizetések rögzítése kereshető és időszakra szűrhető foglalásokhoz',
  'Esedékes díjak és állítható alapértelmezett befizetési paraméterek',
  'Napi összegek, bevételi statisztikák és diagramok',
  'Statisztikák letöltése Excelben',
  'Bankkártyás foglalásfizetés Stripe-kapcsolattal',
  'Billingo- és Számlázz.hu-kapcsolat, kézi vagy automatikus számlázással',
  'A vendég mentett számlázási adatainak használata a számlázásnál'
 ]],
 ['Megjelenés, adatátvitel és integrációk',[
  'Bemutatkozás, elérhetőségek, nyitvatartási információ és térképhivatkozás',
  'Szolgáltatói, munkatársi és szolgáltatásképek feltöltése',
  'Formázható leírások, bemutatkozó szövegek és foglalási feltételek',
  'Foglalófelület beágyazása saját weboldalba',
  'Google Naptár-kapcsolat a szolgáltatónak és a munkatársaknak',
  'A vendég saját maga által engedélyezhető Google Naptár-kapcsolata',
  'Google Naptár-események szinkronizálása és foglalt időszakok figyelembevétele',
  'Vendégek, munkatársak és foglalások importálása',
  'Google Analytics, Tag Manager, Google Ads és Meta Pixel beállítása',
  'Mobilon használható felület és összecsukható szolgáltatói oldalsáv'
 ]],
 ['Központi üzemeltetői eszközök',[
  'Külön főadminfelület, kereshető szolgáltatói listával',
  'Szolgáltatói próbaidő, előfizetések és naptárkeretek kezelése',
  'Szolgáltatóknak kiállított előfizetési számlák és befizetések kezelése',
  'Központi Google-, Facebook-, Apple-, SMTP- és SMS-kapcsolatok',
  'Automatikus levelek HTML-sablonjai és sablonelőnézet',
  'Üzemeltetői hírlevélkampányok, feliratkozási és leiratkozási lehetőséggel',
  'Közvetlen üzemeltetői levelezés és saját számlázási, fizetési kapcsolatok'
 ]]
];
function pricingCatalogue(){return `<section class="pricing-catalogue" aria-labelledby="pricing-catalogue-heading"><header><h2 id="pricing-catalogue-heading">A rendszer összes funkciója</h2><p>Témakörönként összegyűjtve. A csapatos funkciókhoz megfelelő naptárkeret, a külső kapcsolatokhoz külön beállítás szükséges.</p><button type="button" class="catalogue-expand-all" onclick="document.querySelectorAll('.pricing-feature-groups>details').forEach(item=>item.open=true)">Összes témakör megnyitása</button></header><div class="pricing-feature-groups">${pricingFeatureGroups.map(([name,items],i)=>`<details ${i===0?'open':''}><summary>${esc(name)} <span>${items.length} funkció</span></summary>${i===6?'<p class="platform-feature-note">Ezeket a rendszer üzemeltetője kezeli a főadminfelületen; nem a szolgáltató saját beállításai.</p>':''}<ul>${items.map(item=>`<li>${esc(item)}</li>`).join('')}</ul></details>`).join('')}</div><p class="pricing-catalogue-note">Az SMS-küldés, a fizetési és számlázási szolgáltatók használata külön díjjal járhat. A részleteket a külön díjas tételeknél találod.</p></section>`;}
const pricingFeatureCopy=`Foglalás és vendégfiók|Booking and guest accounts|Buchung und Kundenkonto
Saját, nyilvános online foglalóoldal|Your own public online booking page|Eigene öffentliche Onlinebuchungsseite
Szolgáltatókeresés név, kategória és település szerint, helymeghatározással|Provider search by name, category and town, with location detection|Anbietersuche nach Name, Kategorie und Ort mit Standortbestimmung
Foglalás regisztráció és belépés nélkül is|Booking without registering or signing in|Buchung auch ohne Registrierung und Anmeldung
Több szolgáltatás kiválasztása egy foglaláshoz|Multiple services in one booking|Mehrere Leistungen in einer Buchung
Szabad időpontok és a legközelebbi szabad nap keresése|Available appointments and the nearest available day|Freie Termine und den nächstmöglichen freien Tag finden
Foglalás kezelése és lemondása saját visszaigazoló hivatkozással|Manage and cancel bookings through a private confirmation link|Buchungen über einen persönlichen Bestätigungslink verwalten und stornieren
E-mailes vendégregisztráció, e-mail-megerősítés és jelszó-visszaállítás|Guest registration by email, email verification and password recovery|Kundenregistrierung per E-Mail, E-Mail-Bestätigung und Passwortwiederherstellung
Google-, Facebook- és Apple-belépés vendégeknek és szolgáltatóknak|Google, Facebook and Apple sign-in for guests and providers|Google-, Facebook- und Apple-Anmeldung für Kunden und Anbieter
Vendégprofil, profilkép, elérhetőségek és külön számlázási adatok|Guest profile, photo, contact information and separate billing details|Kundenprofil, Profilbild, Kontaktdaten und getrennte Rechnungsdaten
Elérhetőségek megjegyzése és automatikus kitöltése|Remember and automatically fill contact details|Kontaktdaten speichern und automatisch ausfüllen
Aktuális és korábbi foglalások több szolgáltatónál, egy vendégfiókban|Current and past bookings across providers in one guest account|Aktuelle und frühere Buchungen bei mehreren Anbietern in einem Kundenkonto
Magyar, angol és német felület|Hungarian, English and German interface|Ungarische, englische und deutsche Oberfläche
Naptár és időbeosztás|Calendar and scheduling|Kalender und Zeitplanung
Napi, heti és havi naptárnézet, gördülő hét nappal|Day, week and month views, with a rolling seven-day week|Tages-, Wochen- und Monatsansicht mit fortlaufender Siebentagewoche
Gyors időpontfelvitel, meglévő vendég keresése és alapértelmezett szolgáltatás|Quick appointment entry, existing guest search and default service|Schnelle Terminerfassung, Suche nach vorhandenen Kunden und Standardleistung
Ismétlődő időpontok napi, heti, kétheti és havi gyakorisággal|Daily, weekly, fortnightly and monthly recurring appointments|Täglich, wöchentlich, zweiwöchentlich und monatlich wiederkehrende Termine
Ismétlődő sorozat kezdőnappal, végdátummal, darabszámmal vagy folyamatosan|Recurring series with start date, end date, occurrence count or ongoing repetition|Terminserien mit Startdatum, Enddatum, Anzahl oder fortlaufender Wiederholung
Jóváhagyás, elutasítás, lemondás és az időpontok áthelyezése|Approve, reject, cancel and reschedule appointments|Termine bestätigen, ablehnen, stornieren und verschieben
Megjelent és nem jelent meg állapot, opcionális meg nem jelenési díjjal|Attendance and no-show status, with an optional no-show fee|Anwesenheits- und Nichterschienen-Status mit optionaler Ausfallgebühr
Szokásos nyitvatartás és egyedi napi nyitvatartás|Regular hours and individual daily opening hours|Reguläre und individuelle tägliche Öffnungszeiten
Több nap együttes kijelölése a nyitvatartás módosításához|Select multiple days to change opening hours|Mehrere Tage gleichzeitig zur Änderung der Öffnungszeiten auswählen
Ebédszünet, teljes napos szabadság és rövidebb időblokkok|Lunch breaks, full days off and shorter blocked periods|Mittagspausen, ganztägige Abwesenheiten und kürzere Zeitsperren
Állítható időpontkezdési lépésköz, egész órás kezdéssel is|Configurable appointment intervals, including starts on the hour|Einstellbare Terminabstände einschließlich Beginn zur vollen Stunde
Naptárletöltés és nyomtatható, letölthető foglalási QR-kód|Calendar download and a printable, downloadable booking QR code|Kalenderdownload und druckbarer, herunterladbarer Buchungs-QR-Code
Csapatok és összetettebb szolgáltatások|Teams and advanced services|Teams und komplexere Leistungen
Több munkatárs és külön kezelhető munkatársi naptárak|Multiple team members with separately managed calendars|Mehrere Mitarbeiter mit getrennt verwaltbaren Kalendern
Munkatársankénti szolgáltatások és időbeosztás|Services and schedules for each team member|Leistungen und Zeitplanung pro Mitarbeiter
Egyéni vállalkozói mód az elsődleges szakemberrel|Solo business mode with a primary professional|Einzelunternehmermodus mit einer Hauptfachkraft
Párhuzamosan végezhető szolgáltatások és kapacitáskezelés|Parallel services and capacity management|Parallele Leistungen und Kapazitätsverwaltung
Munka–hatóidő–munka szakaszok megadása|Define work, processing and finishing stages|Arbeits-, Einwirk- und Abschlussphasen festlegen
Meghirdetett csoportos alkalmak és férőhelykezelés|Published group sessions and capacity tracking|Veröffentlichte Gruppentermine und Platzverwaltung
Csoportos minimumlétszám és teljesítési határidő|Minimum group attendance and its deadline|Mindestteilnehmerzahl und Frist für Gruppen
Elmaradó csoportos alkalmak kézi és automatikus lemondása, értesítéssel|Manual and automatic group session cancellation with notifications|Manuelle und automatische Absage von Gruppenterminen mit Benachrichtigung
A nem használt összetett funkciók kikapcsolása a beállításoknál|Turn off unused advanced features in settings|Nicht verwendete erweiterte Funktionen in den Einstellungen deaktivieren
Vendégkezelés és kapcsolattartás|Guest management and communication|Kundenverwaltung und Kommunikation
Kereshető vendéglista, foglalási előzmények és látogatási összesítők|Searchable guest list, booking history and attendance summaries|Durchsuchbare Kundenliste, Buchungsverlauf und Besuchsübersichten
E-mail- és telefonszám-ellenőrzés, egységes telefonszámformátum|Email and phone validation with consistent phone formatting|E-Mail- und Telefonnummernprüfung mit einheitlichem Telefonnummernformat
Azonos elérhetőségekkel létrehozott új vendégmásolatok megelőzése|Prevent new duplicate guest records with matching contact details|Neue doppelte Kundeneinträge mit gleichen Kontaktdaten verhindern
Feketelista a saját vállalkozás online foglalásaihoz|Blacklist for your own business's online bookings|Sperrliste für Onlinebuchungen des eigenen Unternehmens
Hívásgombok és belső üzenetváltás a kapcsolt vendégfiókokkal|Call buttons and internal messaging with linked guest accounts|Anrufschaltflächen und interne Nachrichten mit verknüpften Kundenkonten
Foglalási értesítések és emlékeztetők e-mailben vagy SMS-ben|Booking notifications and reminders by email or SMS|Buchungsbenachrichtigungen und Erinnerungen per E-Mail oder SMS
Választható vendégmeghívó e-mailben vagy SMS-ben|Optional guest invitations by email or SMS|Optionale Kundeneinladungen per E-Mail oder SMS
Kézi és automatikus értékeléskérés igazolt látogatás után|Manual and automatic review requests after confirmed attendance|Manuelle und automatische Bewertungsanfragen nach bestätigtem Besuch
Nyilvános vendégvélemények és belső vendégértékelések|Public guest reviews and internal guest ratings|Öffentliche Kundenrezensionen und interne Kundenbewertungen
Kapcsolatfelvétel az üzemeltetővel a saját fiókból|Contact the operator from your own account|Den Betreiber aus dem eigenen Konto kontaktieren
Kuponok, bérletek és pénzügyek|Coupons, passes and finances|Gutscheine, Mehrfachkarten und Finanzen
Százalékos és összegalapú kuponok, érvényességgel és felhasználási korlátokkal|Percentage and fixed-amount coupons with validity and usage limits|Prozentuale und feste Rabatte mit Gültigkeit und Nutzungslimits
Kuponok kijelölt szolgáltatásokra, első alkalomra vagy nyilvános ajánlatként|Coupons for selected services, first visits or public offers|Gutscheine für bestimmte Leistungen, Erstbesuche oder öffentliche Angebote
Alkalmas bérletek, felhasználható szolgáltatásokkal|Multi-visit passes with eligible services|Mehrfachkarten mit zulässigen Leistungen
Bérletérvényesség konkrét dátummal vagy az első használattól számítva|Pass validity with fixed dates or from the first use|Kartengültigkeit mit festen Daten oder ab erster Nutzung
Kuponkedvezmény és bérletfelhasználás követése a foglalásnál|Track coupon discounts and pass usage on bookings|Gutscheinrabatte und Kartennutzung bei Buchungen verfolgen
Befizetések rögzítése kereshető és időszakra szűrhető foglalásokhoz|Record payments against searchable bookings filtered by date|Zahlungen zu durchsuchbaren und nach Zeitraum gefilterten Buchungen erfassen
Esedékes díjak és állítható alapértelmezett befizetési paraméterek|Amounts due and configurable default payment parameters|Fällige Beträge und einstellbare Standardzahlungsparameter
Napi összegek, bevételi statisztikák és diagramok|Daily totals, revenue statistics and charts|Tagessummen, Umsatzstatistiken und Diagramme
Statisztikák letöltése Excelben|Download statistics in Excel|Statistiken in Excel herunterladen
Bankkártyás foglalásfizetés Stripe-kapcsolattal|Card payment for bookings through Stripe|Kartenzahlung für Buchungen über Stripe
Billingo- és Számlázz.hu-kapcsolat, kézi vagy automatikus számlázással|Billingo and Számlázz.hu integration with manual or automatic invoicing|Billingo- und Számlázz.hu-Anbindung mit manueller oder automatischer Rechnungsstellung
A vendég mentett számlázási adatainak használata a számlázásnál|Use the guest's saved billing details for invoicing|Gespeicherte Rechnungsdaten des Kunden für die Rechnungsstellung verwenden
Megjelenés, adatátvitel és integrációk|Presentation, data transfer and integrations|Darstellung, Datenübertragung und Integrationen
Bemutatkozás, elérhetőségek, nyitvatartási információ és térképhivatkozás|Introduction, contact details, opening hours and map link|Vorstellung, Kontaktdaten, Öffnungszeiten und Kartenlink
Szolgáltatói, munkatársi és szolgáltatásképek feltöltése|Upload provider, staff and service images|Anbieter-, Mitarbeiter- und Leistungsbilder hochladen
Formázható leírások, bemutatkozó szövegek és foglalási feltételek|Formatted descriptions, introductions and booking terms|Formatierte Beschreibungen, Vorstellungstexte und Buchungsbedingungen
Foglalófelület beágyazása saját weboldalba|Embed the booking interface on your website|Buchungsoberfläche in die eigene Website einbetten
Google Naptár-kapcsolat a szolgáltatónak és a munkatársaknak|Google Calendar connection for providers and staff|Google-Kalender-Verbindung für Anbieter und Mitarbeiter
A vendég saját maga által engedélyezhető Google Naptár-kapcsolata|Google Calendar connection authorized by the guest personally|Vom Kunden selbst freigegebene Google-Kalender-Verbindung
Google Naptár-események szinkronizálása és foglalt időszakok figyelembevétele|Synchronize Google Calendar events and respect busy periods|Google-Kalender-Termine synchronisieren und belegte Zeiträume berücksichtigen
Vendégek, munkatársak és foglalások importálása|Import guests, staff and bookings|Kunden, Mitarbeiter und Buchungen importieren
Google Analytics, Tag Manager, Google Ads és Meta Pixel beállítása|Configure Google Analytics, Tag Manager, Google Ads and Meta Pixel|Google Analytics, Tag Manager, Google Ads und Meta Pixel konfigurieren
Mobilon használható felület és összecsukható szolgáltatói oldalsáv|Mobile-friendly interface and collapsible provider sidebar|Mobil nutzbare Oberfläche und einklappbare Anbieter-Seitenleiste
Központi üzemeltetői eszközök|Central operator tools|Zentrale Betreiberwerkzeuge
Külön főadminfelület, kereshető szolgáltatói listával|Dedicated platform admin interface with a searchable provider list|Eigene Plattformverwaltung mit durchsuchbarer Anbieterliste
Szolgáltatói próbaidő, előfizetések és naptárkeretek kezelése|Manage provider trials, subscriptions and calendar limits|Anbieter-Testzeiträume, Abonnements und Kalenderkontingente verwalten
Szolgáltatóknak kiállított előfizetési számlák és befizetések kezelése|Manage provider subscription invoices and payments|Abonnementrechnungen und Zahlungen der Anbieter verwalten
Központi Google-, Facebook-, Apple-, SMTP- és SMS-kapcsolatok|Central Google, Facebook, Apple, SMTP and SMS connections|Zentrale Google-, Facebook-, Apple-, SMTP- und SMS-Verbindungen
Automatikus levelek HTML-sablonjai és sablonelőnézet|HTML templates and previews for automatic emails|HTML-Vorlagen und Vorschau für automatische E-Mails
Üzemeltetői hírlevélkampányok, feliratkozási és leiratkozási lehetőséggel|Operator newsletter campaigns with subscription and unsubscribe options|Betreiber-Newsletterkampagnen mit An- und Abmeldefunktion
Közvetlen üzemeltetői levelezés és saját számlázási, fizetési kapcsolatok|Direct operator messaging and separate billing and payment integrations|Direkte Betreiberkommunikation und eigene Rechnungs- und Zahlungsanbindungen
A rendszer összes funkciója|All system features|Alle Systemfunktionen
Témakörönként összegyűjtve. A csapatos funkciókhoz megfelelő naptárkeret, a külső kapcsolatokhoz külön beállítás szükséges.|Grouped by topic. Team features require a suitable calendar allowance; external integrations need separate configuration.|Nach Themen geordnet. Teamfunktionen erfordern ein passendes Kalenderkontingent, externe Anbindungen eine gesonderte Einrichtung.
Ezeket a rendszer üzemeltetője kezeli a főadminfelületen; nem a szolgáltató saját beállításai.|These are managed by the platform operator, rather than in the provider's own settings.|Diese Funktionen verwaltet der Plattformbetreiber, nicht der Anbieter in seinen eigenen Einstellungen.
Az SMS-küldés, a fizetési és számlázási szolgáltatók használata külön díjjal járhat. A részleteket a külön díjas tételeknél találod.|SMS, payment and invoicing services may incur additional fees. See the additional charges section for details.|SMS-, Zahlungs- und Rechnungsdienste können zusätzliche Kosten verursachen. Details stehen bei den zusätzlichen Gebühren.
Összes témakör megnyitása|Expand all topics|Alle Themen öffnen`;
window.raeroFeatureTranslations=Object.fromEntries(pricingFeatureCopy.split('\n').map(line=>line.split('|')).filter(p=>p.length===3).map(([hu,en,de])=>[hu,{en,de}]));
