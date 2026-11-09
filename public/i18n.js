/* UI localization. Business names, authored descriptions, and form values are not translated. */
(()=>{
 let language='hu';try{const saved=localStorage.getItem('raero-language');if(['hu','en','de'].includes(saved))language=saved;}catch{}
 window.raeroLanguage=language;window.raeroLocale=()=>({hu:'hu-HU',en:'en-GB',de:'de-DE'}[window.raeroLanguage]||'hu-HU');
 const dictionary=new Map();
 const entries=`
Csatlakozom|Join as a provider|Als Anbieter starten
Árak|Pricing|Preise
Kapcsolat|Contact|Kontakt
Időpontfoglalás|Book an appointment|Termin buchen
Saját fiókom|My account|Mein Konto
Menü|Menu|Menü
Felső menü|Main navigation|Hauptnavigation
Belépés|Log in|Anmelden
Belépek|Log in|Anmelden
Regisztráció|Sign up|Registrieren
Kilépés|Log out|Abmelden
Vissza|Back|Zurück
Mégse|Cancel|Abbrechen
Mentés|Save|Speichern
Bezárás|Close|Schließen
Keresés|Search|Suchen
Újrapróbálás|Try again|Erneut versuchen
Új időpont|New appointment|Neuer Termin
Naptár|Calendar|Kalender
Vendégek|Guests|Kunden
Szolgáltatások|Services|Leistungen
Munkatársak|Team members|Mitarbeiter
Foglalási események|Bookings|Buchungen
Befizetések|Payments|Zahlungen
Statisztika|Statistics|Statistik
Üzenetek|Messages|Nachrichten
Beállítások|Settings|Einstellungen
Előfizetés|Subscription|Abonnement
Napi|Day|Tag
Heti|Week|Woche
Havi|Month|Monat
Ma|Today|Heute
Nap|Date|Datum
Időpont|Time|Uhrzeit
Vendég neve|Guest name|Kundenname
Telefonszám|Phone number|Telefonnummer
E-mail|Email|E-Mail
E-mail-cím|Email address|E-Mail-Adresse
Jelszó|Password|Passwort
Jelszó még egyszer|Confirm password|Passwort bestätigen
Jelszó · legalább 12 karakter|Password · at least 12 characters|Passwort · mindestens 12 Zeichen
Időpont mentése|Save appointment|Termin speichern
Válassz időpontot…|Choose a time…|Uhrzeit wählen…
Válassz időpontot...|Choose a time…|Uhrzeit wählen…
Nincs szabad időpont ezen a napon|No available times on this day|An diesem Tag sind keine Termine frei
Szabad időpontok keresése…|Finding available times…|Freie Termine werden gesucht…
Szabad időpontok|Available times|Freie Termine
Nap kiválasztása|Choose a date|Datum auswählen
Erre a napra nincs szabad időpont. Válassz másik napot.|No available times on this day. Choose another date.|An diesem Tag sind keine Termine frei. Bitte wähle einen anderen Tag.
Időpont lefoglalása →|Book appointment →|Termin buchen →
Az időpontot elmentettük.|Appointment saved.|Termin gespeichert.
Válassz szabad időpontot.|Choose an available time.|Bitte wähle einen freien Termin.
Érvénytelen e-mail-cím.|Invalid email address.|Ungültige E-Mail-Adresse.
Érvénytelen e-mail-cím. Példa: nev@pelda.hu.|Invalid email address. Example: name@example.com.|Ungültige E-Mail-Adresse. Beispiel: name@beispiel.de.
Érvénytelen telefonszám. Példa: 06301234567.|Invalid phone number. Example: 06301234567.|Ungültige Telefonnummer. Beispiel: 06301234567.
Töltsd ki ezt a mezőt.|Please complete this field.|Bitte fülle dieses Feld aus.
A belépés nem sikerült. Próbáld újra.|Login failed. Please try again.|Anmeldung fehlgeschlagen. Bitte versuche es erneut.
Hibás e-mail-cím vagy jelszó.|Incorrect email address or password.|E-Mail-Adresse oder Passwort ist falsch.
Vendégbelépés szükséges.|Please log in as a guest.|Bitte melde dich als Kunde an.
Az online foglalás nem elérhető. Keresd közvetlenül a szolgáltatót.|Online booking is unavailable. Please contact the provider directly.|Die Onlinebuchung ist nicht verfügbar. Bitte kontaktiere den Anbieter direkt.
A hivatkozás érvénytelen vagy lejárt.|This link is invalid or has expired.|Dieser Link ist ungültig oder abgelaufen.
Előbb erősítsd meg az e-mail-címedet a kapott levélben.|Please confirm your email address using the link in the email first.|Bitte bestätige zuerst deine E-Mail-Adresse über den Link in der E-Mail.
Ezzel az e-mail-címmel már van jelszavas vendégfiók. Lépj be, vagy kérj új jelszót.|An email account already exists. Log in or reset your password.|Für diese E-Mail-Adresse gibt es bereits ein Konto. Melde dich an oder setze dein Passwort zurück.
Örülünk, hogy itt vagy.|Welcome back.|Schön, dass du da bist.
Legyen saját vendégfiókod.|Create your guest account.|Erstelle dein Kundenkonto.
Erősítsd meg az e-mail-címedet.|Confirm your email address.|Bestätige deine E-Mail-Adresse.
Válassz új jelszót.|Choose a new password.|Wähle ein neues Passwort.
Kérj új jelszót.|Reset your password.|Setze dein Passwort zurück.
Küldjük újra a megerősítő levelet.|Resend the verification email.|Bestätigungs-E-Mail erneut senden.
A TE IDŐD, EGY HELYEN|YOUR TIME, IN ONE PLACE|DEINE ZEIT, AN EINEM ORT
Legközelebb|Next time,|Beim nächsten Mal
még egyszerűbb.|even easier.|noch einfacher.
Saját adataid, közelgő időpontjaid és korábbi foglalásaid egy átlátható vendégfiókban.|Your details, upcoming appointments, and booking history in one clear account.|Deine Daten, bevorstehenden Termine und vergangenen Buchungen in einem übersichtlichen Konto.
Neved|Your name|Dein Name
Telefonszám (nem kötelező)|Phone number (optional)|Telefonnummer (optional)
Létrehozom a vendégfiókomat|Create my guest account|Kundenkonto erstellen
E-mail-cím megerősítése|Confirm email address|E-Mail-Adresse bestätigen
Új jelszó mentése|Save new password|Neues Passwort speichern
Jelszó-visszaállító levél kérése|Request password reset email|E-Mail zum Zurücksetzen anfordern
Megerősítő levél újraküldése|Resend verification email|Bestätigungs-E-Mail erneut senden
Elfelejtetted a jelszavad?|Forgot your password?|Passwort vergessen?
Nem jött meg a megerősítő levél?|Didn't receive the verification email?|Keine Bestätigungs-E-Mail erhalten?
vagy közösségi fiókkal|or use a social account|oder mit einem Social-Media-Konto
Vissza a belépéshez|Back to login|Zurück zur Anmeldung
Foglalni vendégfiók nélkül is lehet.|You can also book without an account.|Du kannst auch ohne Konto buchen.
Foglalás belépés nélkül →|Book without logging in →|Ohne Anmeldung buchen →
A közösségi belépés nem sikerült. Próbálj másik belépési módot.|Social login failed. Please try another login method.|Die Anmeldung über Social Media ist fehlgeschlagen. Bitte versuche eine andere Anmeldemethode.
Ha van ilyen vendégfiók, a megfelelő levél küldési sorba került. Nézd meg a beérkező leveleket és a levélszemét mappát is.|If a matching account exists, the email has been queued. Check your inbox and spam folder.|Wenn ein passendes Konto existiert, wurde die E-Mail zum Versand vorgemerkt. Bitte prüfe auch deinen Spam-Ordner.
Egy kis idő.|A little time.|Ein wenig Zeit.
Csak neked.|Just for you.|Nur für dich.
IDŐPONTOK, AMIK RÓLAD SZÓLNAK|APPOINTMENTS THAT PUT YOU FIRST|TERMINE, BEI DENEN ES UM DICH GEHT
Új frizura, feltöltő masszázs vagy egy lendületes edzés? Találd meg a szakembered, és foglalj időt magadra.|A new hairstyle, a relaxing massage, or an energising workout? Find your specialist and make time for yourself.|Eine neue Frisur, eine entspannende Massage oder ein motivierendes Training? Finde deinen Profi und nimm dir Zeit für dich.
✓ Egyszerű választás|✓ Easy to choose|✓ Einfach auswählen
✓ Online foglalás|✓ Book online|✓ Online buchen
✓ Ingyenes vendégfiók|✓ Free guest account|✓ Kostenloses Kundenkonto
A következő szabad pillanatod|Your next free moment|Dein nächster freier Moment
Találj rá néhány kattintással.|Find it in just a few clicks.|Mit wenigen Klicks finden.
Kit vagy mit keresel?|Who or what are you looking for?|Wen oder was suchst du?
Szolgáltató vagy szolgáltatás neve|Provider or service name|Name des Anbieters oder der Leistung
Milyen szolgáltatást?|Which service?|Welche Leistung?
Minden kategória|All categories|Alle Kategorien
Hol?|Where?|Wo?
Település|Town or city|Ort
A közelemben|Near me|In meiner Nähe
Válassz települést, vagy használd a helymeghatározást.|Choose a town or use your location.|Wähle einen Ort oder nutze deinen Standort.
Településadatok:|Location data:|Ortsdaten:
Mihez lenne kedved?|What would you like?|Worauf hast du Lust?
Válassz egy kategóriát.|Choose a category.|Wähle eine Kategorie.
Fodrászat és barber|Hair and barber|Friseur und Barber
Szépség és ápolás|Beauty and care|Beauty und Pflege
Masszázs és wellness|Massage and wellness|Massage und Wellness
Sport és mozgás|Fitness and movement|Sport und Bewegung
Egészség és terápia|Health and therapy|Gesundheit und Therapie
Tanácsadás és oktatás|Consulting and education|Beratung und Bildung
Egyéb szolgáltatás|Other services|Weitere Leistungen
FEDEZD FEL A LEHETŐSÉGEID|EXPLORE YOUR OPTIONS|ENTDECKE DEINE MÖGLICHKEITEN
Találd meg a neked valót.|Find your perfect match.|Finde das Richtige für dich.
Szűrők törlése ×|Clear filters ×|Filter zurücksetzen ×
Most nincs ilyen találat.|No matches right now.|Momentan gibt es keine passenden Treffer.
Próbálj másik kategóriát vagy települést, esetleg töröld a szűrőket.|Try another category or town, or clear the filters.|Versuche eine andere Kategorie oder einen anderen Ort oder setze die Filter zurück.
Időpontot foglalok|Book an appointment|Termin buchen
Előző|Previous|Zurück
Következő|Next|Weiter
← Előző|← Previous|← Zurück
Következő →|Next →|Weiter →
Árak az adatlapon|Prices on the profile|Preise auf der Profilseite
Online időpontfoglalás|Online booking|Online-Terminbuchung
HÁROM EGYSZERŰ LÉPÉS|THREE EASY STEPS|DREI EINFACHE SCHRITTE
A te időd.|Your time.|Deine Zeit.
A te választásod.|Your choice.|Deine Entscheidung.
Találd meg|Find your provider|Finde deinen Anbieter
Keress név, kategória vagy település alapján.|Search by name, category, or town.|Suche nach Name, Kategorie oder Ort.
Válassz időpontot|Choose a time|Wähle einen Termin
Az adatlapon megnézheted a szolgáltatásokat és a szabad időket.|See services and available times on the provider's profile.|Auf der Profilseite findest du Leistungen und freie Termine.
Érkezz és töltődj|Arrive and enjoy|Ankommen und genießen
Saját fiókodban egy helyen látod a foglalásaidat.|See all your bookings in one place in your account.|In deinem Konto findest du alle Buchungen an einem Ort.
A MÁSIK OLDALON DOLGOZOL?|ARE YOU A SERVICE PROVIDER?|BIST DU SELBST ANBIETER?
A vendégeid itt keresnek.|Your clients are looking here.|Deine Kunden suchen hier.
Legyél te a következő találat.|Be their next discovery.|Sei ihr nächster Treffer.
Saját foglalóoldal, átlátható naptár és kevesebb adminisztráció.|Your own booking page, a clear calendar, and less admin.|Deine eigene Buchungsseite, ein übersichtlicher Kalender und weniger Verwaltung.
Csatlakozom szolgáltatóként|Join as a provider|Als Anbieter registrieren
A böngésződ nem támogatja a helymeghatározást. Add meg kézzel a települést.|Your browser doesn't support location lookup. Enter the town manually.|Dein Browser unterstützt keine Standortbestimmung. Bitte gib den Ort manuell ein.
Helyzeted meghatározása…|Finding your location…|Dein Standort wird ermittelt…
A helymeghatározás túl pontatlan. Add meg kézzel a települést.|The location is too imprecise. Enter the town manually.|Der Standort ist zu ungenau. Bitte gib den Ort manuell ein.
Nem találtunk közeli magyar települést. Válassz települést kézzel.|We couldn't find a nearby Hungarian town. Choose a town manually.|Es wurde kein nahe gelegener ungarischer Ort gefunden. Bitte wähle einen Ort manuell.
A helyhozzáférés nincs engedélyezve. Add meg kézzel a települést.|Location permission is not enabled. Enter the town manually.|Der Standortzugriff ist nicht erlaubt. Bitte gib den Ort manuell ein.
A helyzeted most nem kérdezhető le. Add meg kézzel a települést.|Your location is unavailable right now. Enter the town manually.|Dein Standort ist gerade nicht verfügbar. Bitte gib den Ort manuell ein.
A településadatok nem tölthetők be. Add meg kézzel a települést.|Location data could not be loaded. Enter the town manually.|Die Ortsdaten konnten nicht geladen werden. Bitte gib den Ort manuell ein.
Belépés / Saját fiók|Log in / My account|Anmelden / Mein Konto
Saját fiókom →|My account →|Mein Konto →
Belépés Google-, Facebook- vagy Apple-fiókkal →|Log in with Google, Facebook or Apple →|Mit Google, Facebook oder Apple anmelden →
Szolgáltatásaink|Our services|Unsere Leistungen
Válassz szolgáltatást és szabad időpontot. A többit mi egyszerűvé tesszük.|Choose a service and an available time. We make the rest easy.|Wähle eine Leistung und einen freien Termin. Den Rest machen wir einfach.
Megnézem az időpontokat|View available times|Freie Termine ansehen
Válassz időpontot|Choose an appointment|Wähle einen Termin
Itt találsz minket|Find us here|Hier findest du uns
Útvonal és térkép →|Directions and map →|Anfahrt und Karte →
Elérhetőség és nyitvatartás|Contact and opening hours|Kontakt und Öffnungszeiten
Weboldal ↗|Website ↗|Webseite ↗
Munkatársaink|Our team|Unser Team
Aktuális ajánlatok|Current offers|Aktuelle Angebote
Bérletek|Passes|Mehrfachkarten
Vásárláshoz keresd a szolgáltatót.|Contact the provider to purchase.|Bitte kontaktiere den Anbieter zum Kauf.
Foglalási és lemondási feltételek|Booking and cancellation terms|Buchungs- und Stornierungsbedingungen
Az online foglalás még nem érhető el|Online booking is not available yet|Die Onlinebuchung ist noch nicht verfügbar
A szolgáltató még nem adott meg foglalható szolgáltatást. Keresd a megadott elérhetőségein.|This provider has not added bookable services yet. Use the contact details provided.|Dieser Anbieter hat noch keine buchbaren Leistungen hinzugefügt. Bitte nutze die angegebenen Kontaktdaten.
Szolgáltató értékelése|Review the provider|Anbieter bewerten
Vendég értékelése|Review the guest|Kunden bewerten
Pontszám|Rating|Bewertung
Szöveges értékelés|Written review|Schriftliche Bewertung
Értékelés mentése|Save review|Bewertung speichern
Értékelés mentve.|Review saved.|Bewertung gespeichert.
Az értékelés a szolgáltató nyilvános oldalán jelenik meg.|The review appears on the provider's public page.|Die Bewertung erscheint auf der öffentlichen Seite des Anbieters.
Milyen volt a látogatásod?|How was your visit?|Wie war dein Besuch?
Az értékelésed nyilvánosan megjelenik a szolgáltató oldalán.|Your review will appear publicly on the provider's page.|Deine Bewertung wird öffentlich auf der Seite des Anbieters angezeigt.
Tapasztalatod|Your experience|Deine Erfahrung
Értékelés elküldése|Submit review|Bewertung absenden
Köszönjük az értékelésedet!|Thank you for your review!|Vielen Dank für deine Bewertung!
Saját vendégfiókom|My guest account|Mein Kundenkonto
Nevem|My name|Mein Name
Kapcsolattartási email|Contact email|Kontakt-E-Mail
Profil mentése|Save profile|Profil speichern
Profil mentve.|Profile saved.|Profil gespeichert.
Szolgáltatóim|My providers|Meine Anbieter
Számlázási adataim|My billing details|Meine Rechnungsdaten
A szolgáltató a hozzád kapcsolt foglalás számlázásakor használhatja ezeket. Az elérhetőségeidtől külön mentjük.|The provider can use these when invoicing your linked booking. We store them separately from your contact details.|Der Anbieter kann diese Daten für Rechnungen zu verknüpften Buchungen verwenden. Sie werden getrennt von deinen Kontaktdaten gespeichert.
Számlázási név|Billing name|Rechnungsname
Számlázási e-mail|Billing email|Rechnungs-E-Mail
Országkód|Country code|Ländercode
Irányítószám|Postal code|Postleitzahl
Utca, házszám|Street and house number|Straße und Hausnummer
Adószám (ha van)|Tax number (if applicable)|Steuernummer (falls vorhanden)
Adatok törlése|Delete details|Daten löschen
Számlázási adatok mentése|Save billing details|Rechnungsdaten speichern
Számlázási adatok mentve.|Billing details saved.|Rechnungsdaten gespeichert.
Számlázási adatok törölve.|Billing details deleted.|Rechnungsdaten gelöscht.
Közelgő foglalások|Upcoming bookings|Bevorstehende Buchungen
Korábbi és lezárt foglalások|Past and completed bookings|Vergangene und abgeschlossene Buchungen
Még nincs közelgő foglalásod. Új időpontot a választott szolgáltató foglalóoldalán foglalhatsz.|You have no upcoming bookings. Book a new appointment on your chosen provider's page.|Du hast keine bevorstehenden Buchungen. Neue Termine kannst du auf der Seite deines Anbieters buchen.
Még nincs korábbi foglalásod.|You have no past bookings yet.|Du hast noch keine vergangenen Buchungen.
Foglalás részletei / módosítása →|Booking details / change →|Buchungsdetails / ändern →
Megerősítve|Confirmed|Bestätigt
Jóváhagyásra vár|Awaiting approval|Wartet auf Bestätigung
Lemondva|Cancelled|Storniert
Elutasítva|Rejected|Abgelehnt
Megjelent|Attended|Erschienen
Nem jelent meg|No-show|Nicht erschienen
Nincs jelölve|Not marked|Nicht markiert
Elmaradt óra|Cancelled class|Ausgefallener Kurs
Profilkép cseréje|Change profile photo|Profilbild ändern
Kép cseréje|Change photo|Bild ändern
Profilkép mentve.|Profile photo saved.|Profilbild gespeichert.
Google Naptár|Google Calendar|Google Kalender
Google Naptár összekapcsolása|Connect Google Calendar|Google Kalender verbinden
Most kihagyom|Not now|Jetzt nicht
Legyenek a foglalásaid a Google Naptáradban is?|Would you like your bookings in Google Calendar too?|Möchtest du deine Buchungen auch im Google Kalender sehen?
Csak te kapcsolhatod össze a saját fiókodat. Később a Saját fiókomban is bekapcsolható.|Only you can connect your account. You can also do this later in My account.|Nur du kannst dein Konto verbinden. Das ist auch später unter Mein Konto möglich.
Elérhetőségeim megjegyzése sikeres foglalás után|Remember my contact details after a successful booking|Kontaktdaten nach erfolgreicher Buchung merken
Bejelentkezve:|Logged in:|Angemeldet:
A fiókodban megadott adatokat kitöltöttük.|We filled in the details from your account.|Wir haben deine Kontodaten vorausgefüllt.
A Google és a Facebook általában nem ad át telefonszámot. Ha most megadod, legközelebb ezt is kitöltjük. Más személynek történő foglalásnál vedd ki a pipát.|Google and Facebook usually don't share phone numbers. Enter yours now to prefill it next time. Uncheck this when booking for someone else.|Google und Facebook geben normalerweise keine Telefonnummer weiter. Wenn du sie jetzt angibst, wird sie beim nächsten Mal vorausgefüllt. Entferne das Häkchen bei Buchungen für andere Personen.
Foglalási kérésed megérkezett!|We've received your booking request!|Deine Buchungsanfrage ist eingegangen!
Az időpontod megerősítve!|Your appointment is confirmed!|Dein Termin ist bestätigt!
A szalon jóváhagyására vár.|Waiting for the provider's approval.|Wartet auf die Bestätigung des Anbieters.
Várunk szeretettel!|We look forward to seeing you!|Wir freuen uns auf dich!
Elérhetőségeidet megjegyeztük a következő foglaláshoz.|We saved your contact details for your next booking.|Deine Kontaktdaten wurden für die nächste Buchung gespeichert.
Mentsd el a módosítási hivatkozásod. A szalon beállításai alapján e-mailes értesítőt is kaphatsz:|Save your booking management link. You may also receive an email depending on the provider's settings:|Speichere deinen Link zur Buchungsverwaltung. Je nach Einstellung des Anbieters erhältst du auch eine E-Mail:
Foglalásom kezelése →|Manage my booking →|Meine Buchung verwalten →
Foglalás kezelése|Manage booking|Buchung verwalten
Lemondás|Cancel booking|Buchung stornieren
Lemondom az időpontot|Cancel my appointment|Meinen Termin stornieren
Új időpont mentése|Save new time|Neue Uhrzeit speichern
Módosítás|Change|Ändern
Részletek|Details|Details
Főadmin|Platform admin|Plattform-Admin
Szolgáltatók|Providers|Anbieter
Üzemeltető adatai|Operator details|Betreiberdaten
Üzemeltetői munkatér|Operator workspace|Betreiberbereich
ÜZEMELTETŐI MUNKATÉR|OPERATOR WORKSPACE|BETREIBERBEREICH
Szolgáltatói belépési jogosultságok|Provider login permissions|Anbieter-Anmeldeberechtigungen
Automatikus levelek (HTML)|Automatic emails (HTML)|Automatische E-Mails (HTML)
Központi kapcsolatok|Platform integrations|Zentrale Integrationen
Közvetlen levelezés|Direct messages|Direktnachrichten
Előfizetési számlák|Subscription invoices|Abonnement-Rechnungen
Hírlevélküldés|Newsletters|Newsletter
Szolgáltató keresése|Find a provider|Anbieter suchen
Név, azonosító vagy állapot…|Name, ID, or status…|Name, Kennung oder Status…
Nincs találat. Próbálj más keresést.|No matches. Try another search.|Keine Treffer. Bitte versuche eine andere Suche.
Próbaidő|Trial|Testphase
Aktív|Active|Aktiv
Fizetésre vár|Awaiting payment|Zahlung ausstehend
Megszűnt|Ended|Beendet
Naptárkeret módosítása|Change calendar limit|Kalenderlimit ändern
Új szolgáltató felvétele|Add a provider|Anbieter hinzufügen
Vállalkozás neve|Business name|Name des Unternehmens
Kezdőjelszó (legalább 12 karakter)|Initial password (at least 12 characters)|Startpasswort (mindestens 12 Zeichen)
Szolgáltató létrehozása|Create provider|Anbieter erstellen
Befizetés jóváírása|Credit payment|Zahlung gutschreiben
Banki tranzakció azonosítója|Bank transaction reference|Bank-Transaktionskennung
Befizetés dátuma|Payment date|Zahlungsdatum
Előfizetési befizetések|Subscription payments|Abonnement-Zahlungen
Alapvető beállítások|Basic settings|Grundeinstellungen
Kuponok és bérletek|Coupons and passes|Gutscheine und Mehrfachkarten
Nyilvános adatlap|Public profile|Öffentliches Profil
Integrációk|Integrations|Integrationen
Vállalkozás és foglalás|Business and booking|Unternehmen und Buchung
Szakterület|Profession|Fachgebiet
Működés|Business mode|Betriebsart
Egyéni vállalkozás|Solo business|Einzelunternehmen
Több munkatársas vállalkozás|Multi-person business|Unternehmen mit mehreren Mitarbeitern
Elsődleges szakember (egyéni működésnél)|Primary specialist (solo mode)|Hauptfachkraft (Einzelbetrieb)
Elsődleges szakember|Primary specialist|Hauptfachkraft
Nyitás|Opening time|Öffnung
Zárás|Closing time|Schließung
Beállítások mentése|Save settings|Einstellungen speichern
A munkádhoz szükséges funkciók|Tools for your work|Funktionen für deine Arbeit
Párhuzamos foglalás és csoportos órák|Parallel bookings and group classes|Parallele Buchungen und Gruppenkurse
Szolgáltatási szakaszok: munka, hatóidő, munka|Service phases: work, waiting time, work|Leistungsphasen: Arbeit, Einwirkzeit, Arbeit
Kuponok|Coupons|Gutscheine
Megnevezés|Name|Bezeichnung
Szolgáltatás leírása|Service description|Leistungsbeschreibung
Időtartam (perc)|Duration (minutes)|Dauer (Minuten)
Ár (Ft)|Price (HUF)|Preis (HUF)
Automatikus megerősítés|Automatic confirmation|Automatische Bestätigung
Ki végzi?|Who provides it?|Wer führt sie aus?
Szolgáltatás szerkesztése|Edit service|Leistung bearbeiten
Új szolgáltatás|New service|Neue Leistung
Szerkesztés|Edit|Bearbeiten
Szerkeszt|Edit|Bearbeiten
Új vendég|New guest|Neuer Kunde
Név|Name|Name
Új munkatárs|New team member|Neuer Mitarbeiter
Új kupon|New coupon|Neuer Gutschein
Új bérlet|New pass|Neue Mehrfachkarte
Állapot|Status|Status
Állapot módosítása|Change status|Status ändern
Új állapot|New status|Neuer Status
Állapot mentése|Save status|Status speichern
Foglalás frissítve|Booking updated|Buchung aktualisiert
Hívás|Call|Anrufen
Összeg|Amount|Betrag
Készpénz|Cash|Bar
Bankkártya|Card|Karte
Átutalás|Bank transfer|Überweisung
Befizetés rögzítése|Record payment|Zahlung erfassen
Fizetési mód|Payment method|Zahlungsart
Megjegyzés|Note|Notiz
Mai időpontok|Today's appointments|Heutige Termine
Mai befizetések|Today's payments|Heutige Zahlungen
Nyitvatartás és szünetek|Opening hours and breaks|Öffnungszeiten und Pausen
Nyitvatartás|Opening hours|Öffnungszeiten
Ebédszünet|Lunch break|Mittagspause
Szünet / szabadság|Break / time off|Pause / Urlaub
Szabadnap / szünet|Time off / break|Freier Tag / Pause
Kire vonatkozik?|Who does it apply to?|Für wen gilt das?
Minden munkatárs|All team members|Alle Mitarbeiter
Egész nap|All day|Ganztägig
Kezdés|Start|Beginn
Befejezés|End|Ende
Tól|From|Von
Ig|To|Bis
Tágas idősávok|Spacious time slots|Größere Zeitfelder
Képernyőhöz igazítás|Fit to screen|An Bildschirm anpassen
Csoportos órák|Group classes|Gruppenkurse
Új csoportos alkalom|New group session|Neuer Gruppentermin
Férőhely|Capacity|Plätze
Minimumlétszám|Minimum participants|Mindestteilnehmerzahl
Legfeljebb hány vendég?|Maximum number of guests?|Maximale Kundenzahl?
Foglalás típusa|Booking type|Buchungsart
Egyéni – egyszerre egy vendég|Individual – one guest at a time|Einzeln – ein Kunde zur gleichen Zeit
Párhuzamos – ugyanaz a szolgáltatás több vendégnek|Parallel – same service for several guests|Parallel – gleiche Leistung für mehrere Kunden
Csoportos óra – meghirdetett alkalmakra jelentkezés|Group class – book published sessions|Gruppenkurs – veröffentlichte Termine buchen
Ismétlődő időpont|Recurring appointment|Wiederkehrender Termin
Ismétlődés|Recurrence|Wiederholung
Hetente|Every week|Wöchentlich
Kéthetente|Every two weeks|Alle zwei Wochen
Havonta|Every month|Monatlich
Naponta|Every day|Täglich
Összes alkalom, az elsővel együtt|Total appointments, including the first|Anzahl der Termine einschließlich des ersten
Minden alkalmat ellenőrzünk. Ütközés esetén egyik időpontot sem mentjük el.|Every appointment is checked. If any conflict occurs, none are saved.|Jeder Termin wird geprüft. Bei einem Konflikt wird keiner gespeichert.
Ismétlődő sorozat|Recurring series|Terminserie
A sorozat időpontjai|Appointments in this series|Termine dieser Serie
A sorozat jövőbeli időpontjainak lemondása|Cancel future appointments in the series|Zukünftige Termine der Serie stornieren
Lemondod a sorozat összes jövőbeli, nyitott időpontját?|Cancel all future open appointments in this series?|Alle zukünftigen offenen Termine dieser Serie stornieren?
Sorozat lemondva.|Series cancelled.|Terminserie storniert.
Feketelista|Blacklist|Sperrliste
Online foglalás letiltása|Block online booking|Onlinebuchung sperren
Tiltás feloldása|Remove block|Sperre aufheben
Belső megjegyzés (nem kötelező)|Internal note (optional)|Interne Notiz (optional)
Online foglalás tiltásának feloldása|Unblock online booking|Onlinebuchung entsperren
Vendég hozzáadása a feketelistához|Add guest to blacklist|Kunden auf die Sperrliste setzen
A tiltás csak a saját vállalkozásod online foglalására vonatkozik. A meglévő időpontok megmaradnak, és te továbbra is felvehetsz neki időpontot.|The block only affects your own business's online bookings. Existing appointments remain, and you can still book for this guest.|Die Sperre betrifft nur Onlinebuchungen bei deinem Unternehmen. Bestehende Termine bleiben erhalten und du kannst weiterhin Termine für diesen Kunden anlegen.
Csak a saját vállalkozásod online foglalásait érinti.|Only affects online bookings at your business.|Betrifft nur Onlinebuchungen bei deinem Unternehmen.
Nincs letiltott vendég.|No blocked guests.|Keine gesperrten Kunden.
Online foglalás letiltva|Online booking blocked|Onlinebuchung gesperrt
Értékelés kérése e-mailben|Request a review by email|Bewertung per E-Mail anfordern
Az értékeléskérés küldési sorba került.|The review request has been queued.|Die Bewertungsanfrage wurde zum Versand vorgemerkt.
Ehhez a látogatáshoz már kértünk értékelést.|A review has already been requested for this visit.|Für diesen Besuch wurde bereits eine Bewertung angefordert.
Ügyfélvélemények kérése|Request client reviews|Kundenbewertungen anfordern
Automatikus értékeléskérés e-mailben|Automatic review requests by email|Automatische Bewertungsanfragen per E-Mail
Hány órával a látogatás után?|How many hours after the visit?|Wie viele Stunden nach dem Besuch?
Csak a bekapcsolás után befejeződött, Megjelent állapotú látogatásokhoz. Egy foglaláshoz egy értékeléskérést küldünk. A főadmin e-mail-sablonja használható.|Only for attended visits completed after enabling this feature. One review request per booking. The platform admin's email template is used.|Nur für bestätigte Besuche, die nach der Aktivierung beendet wurden. Eine Bewertungsanfrage pro Buchung. Die E-Mail-Vorlage des Plattform-Admins wird verwendet.
RÁÉRŐ SZOLGÁLTATÓKNAK|RÁÉRŐ FOR PROVIDERS|RÁÉRŐ FÜR ANBIETER
A szakmád a tiéd.|Your expertise is yours.|Dein Handwerk gehört dir.
A szervezésben segítünk.|We help with the organising.|Wir helfen bei der Organisation.
Kevesebb üzenetváltás, átláthatóbb napok. Naptár, vendégek és online foglalás egy egyszerű munkatérben.|Fewer messages, clearer days. Calendar, clients, and online booking in one simple workspace.|Weniger Nachrichten, mehr Überblick. Kalender, Kunden und Onlinebuchungen in einem einfachen Arbeitsbereich.
Kipróbálom 2 hónapig díjmentesen|Try free for 2 months|2 Monate kostenlos testen
Már csatlakoztam · Belépés|Already joined · Log in|Bereits registriert · Anmelden
Bankkártya nélkül indulhatsz. A próbaidő után az alapcsomag 4 000 Ft / hó.|Start without a bank card. After the trial, the Basic plan costs HUF 4,000 per month.|Starte ohne Bankkarte. Nach der Testphase kostet der Basistarif 4.000 HUF pro Monat.
Fodrászoknak és szépségszalonoknak|For hairdressers and beauty salons|Für Friseure und Schönheitssalons
Edzőknek és mozgásstúdióknak|For trainers and fitness studios|Für Trainer und Bewegungsstudios
Masszőröknek és más szakembereknek|For massage therapists and other specialists|Für Masseure und andere Fachkräfte
EGY RENDSZER. A TE VÁLLALKOZÁSODRA SZABVA.|ONE SYSTEM. BUILT AROUND YOUR BUSINESS.|EIN SYSTEM. AUF DEIN UNTERNEHMEN ABGESTIMMT.
Amire nap mint nap szükséged van.|What you need, every day.|Was du jeden Tag brauchst.
A speciális funkciókat csak akkor kapcsold be, ha használod őket.|Enable specialised tools only when you need them.|Aktiviere Spezialfunktionen nur dann, wenn du sie brauchst.
A napod, egy pillantásra|Your day at a glance|Dein Tag auf einen Blick
Saját online foglalóoldal|Your own online booking page|Deine eigene Online-Buchungsseite
Vendégek, akikre emlékszel|Clients you'll remember|Kunden, an die du dich erinnerst
Emlékeztetők és üzenetek|Reminders and messages|Erinnerungen und Nachrichten
Befizetések és számlázás|Payments and invoicing|Zahlungen und Rechnungsstellung
Több szakember, közös rendszer|Several specialists, one system|Mehrere Fachkräfte, ein System
A saját ritmusodban|At your own pace|In deinem eigenen Rhythmus
Napi, heti és havi naptár, gyors időpontfelvitel, egyedi nyitvatartás és szünetek.|Daily, weekly, and monthly calendars, quick booking entry, custom opening hours and breaks.|Tages-, Wochen- und Monatskalender, schnelle Terminerfassung, individuelle Öffnungszeiten und Pausen.
A vendégeid megismerhetik a szolgáltatásaidat, és kiválaszthatják a nekik megfelelő időt.|Clients can discover your services and choose a time that suits them.|Deine Kunden können deine Leistungen kennenlernen und einen passenden Termin wählen.
Elérhetőségek, előzmények és saját vendégfiókok. Az adatokat nem kell minden foglalásnál újra megadni.|Contact details, history, and personal guest accounts. No need to re-enter details for every booking.|Kontaktdaten, Historie und eigene Kundenkonten. Daten müssen nicht bei jeder Buchung erneut eingegeben werden.
E-mail- és SMS-értesítések a beállított kapcsolatokkal, belső levelezés és kezelhető lemondások.|Email and SMS notifications through configured integrations, internal messages, and manageable cancellations.|E-Mail- und SMS-Benachrichtigungen über konfigurierte Integrationen, interne Nachrichten und verwaltbare Stornierungen.
Befizetések rögzítése, számlázókapcsolatok, bevételi statisztikák és Excel-export.|Payment records, invoicing integrations, revenue statistics, and Excel export.|Zahlungserfassung, Rechnungsintegrationen, Umsatzstatistiken und Excel-Export.
Kedvezmények, alkalmas bérletek és felhasználási feltételek egy helyen.|Discounts, multi-visit passes, and usage terms in one place.|Rabatte, Mehrfachkarten und Nutzungsbedingungen an einem Ort.
Külön munkatársi naptárak, csoportos alkalmak és párhuzamos szolgáltatások igény szerint.|Separate staff calendars, group sessions, and parallel services when needed.|Separate Mitarbeiterkalender, Gruppentermine und parallele Leistungen bei Bedarf.
Google Naptár-kapcsolat, letölthető naptár és nyomtatható QR-kód a foglalóoldaladhoz.|Google Calendar connection, calendar downloads, and a printable QR code for your booking page.|Google-Kalender-Verbindung, Kalenderdownload und druckbarer QR-Code für deine Buchungsseite.
TÖBB FIGYELEM A VENDÉGEIDNEK|MORE ATTENTION FOR YOUR CLIENTS|MEHR AUFMERKSAMKEIT FÜR DEINE KUNDEN
Ne az adminisztráció töltse ki a napodat.|Don't let admin fill your day.|Lass nicht die Verwaltung deinen Tag bestimmen.
Az egyéni vállalkozónak egyszerű felület kell. Egy csapatnak közös átlátás. A Ráérőben a működésedhez választhatod ki az eszközöket.|Solo businesses need simplicity. Teams need a shared overview. With Ráérő, choose the tools that fit the way you work.|Einzelunternehmen brauchen Einfachheit, Teams einen gemeinsamen Überblick. Mit Ráérő wählst du die Werkzeuge passend zu deiner Arbeitsweise.
Gyors időpontfelvitel ismétlődő adatbeírás nélkül|Quick appointment entry without retyping details|Schnelle Terminerfassung ohne wiederholte Dateneingabe
Saját arculat, képek és szolgáltatások a foglalóoldalon|Your branding, images, and services on your booking page|Eigenes Erscheinungsbild, Bilder und Leistungen auf der Buchungsseite
Vendégfiók és önálló foglaláskezelés|Guest accounts and self-service booking management|Kundenkonten und eigenständige Buchungsverwaltung
Megnézem a csatlakozást →|See how to join →|Mehr zur Registrierung →
INDULJUNK EL EGYÜTT|LET'S GET STARTED|LASS UNS GEMEINSAM STARTEN
Legyen egyszerűbb|Make your next|Mach deinen nächsten
 a következő munkanapod.|working day easier.|Arbeitstag einfacher.
a következő munkanapod.|working day easier.|Arbeitstag einfacher.
Hozd létre a saját szolgáltatói fiókodat. A naptáradhoz elsőként a szolgáltatásaidat és a nyitvatartásodat állíthatod be.|Create your provider account. Start by setting up your services and opening hours.|Erstelle dein Anbieterkonto. Lege zuerst deine Leistungen und Öffnungszeiten fest.
az első 2 hónapban|for the first 2 months|in den ersten 2 Monaten
Utána 4 000 Ft / hó, alanyi adómentes végösszeg. A naptárkeret bővítéséről és a külső kapcsolatok díjairól az üzemeltetővel egyeztethetsz.|Then HUF 4,000 per month, VAT-exempt final price. Contact the operator about extra calendars and external integration fees.|Danach 4.000 HUF pro Monat als umsatzsteuerbefreiter Endpreis. Zusätzliche Kalender und externe Integrationskosten kannst du mit dem Betreiber abstimmen.
Előbb kérdeznék →|I'd like to ask first →|Ich habe zuerst eine Frage →
Csatlakozom a Ráérőhöz|Join Ráérő|Bei Ráérő registrieren
Fő kategória|Main category|Hauptkategorie
Válassz kategóriát…|Choose a category…|Kategorie wählen…
Elindítom a próbaidőt|Start my free trial|Kostenlose Testphase starten
Van már fiókod?|Already have an account?|Hast du bereits ein Konto?
Szolgáltatói belépés|Provider login|Anbieter-Anmeldung
Üdv újra. Folytassuk a napod.|Welcome back. Let's continue your day.|Willkommen zurück. Machen wir weiter.
Keresd meg a vállalkozásod, és lépj be a saját munkaterületedre.|Find your business and log in to your workspace.|Finde dein Unternehmen und melde dich in deinem Arbeitsbereich an.
Belépés e-mail-címmel|Log in with email|Mit E-Mail anmelden
Belépés a vállalkozás nevével vagy közösségi fiókkal|Log in by business name or social account|Mit Unternehmensname oder Social-Media-Konto anmelden
Vállalkozás keresése|Find your business|Unternehmen suchen
Vállalkozás neve…|Business name…|Unternehmensname…
Még nincs saját fiókod?|Don't have an account yet?|Hast du noch kein Konto?
Csatlakozom szolgáltatóként →|Join as a provider →|Als Anbieter registrieren →
Belépés →|Log in →|Anmelden →
HELY A NAPTÁRADBAN. REND A NAPODBAN.|SPACE IN YOUR CALENDAR. ORDER IN YOUR DAY.|PLATZ IM KALENDER. ORDNUNG IM ALLTAG.
Egy jó rendszer.|One good system.|Ein gutes System.
Átlátható feltételekkel.|Clear terms.|Klare Bedingungen.
Indulj egyedül, vagy szervezz csapatot. A vendégeidnek a fiók és az időpontfoglalás ingyenes.|Start solo or build a team. Accounts and booking are free for your guests.|Starte allein oder organisiere ein Team. Konto und Terminbuchung sind für deine Kunden kostenlos.
2 hónap díjmentes próba · Bankkártya nélkül|2 months free · No bank card required|2 Monate kostenlos · Ohne Bankkarte
EGYÉNI SZAKEMBEREKNEK|FOR SOLO SPECIALISTS|FÜR EINZELNE FACHKRÄFTE
Alap|Basic|Basis
/ hónap|/ month|/ Monat
Alanyi adómentes végösszeg.|VAT-exempt final price.|Umsatzsteuerbefreiter Endpreis.
1 naptár az alapcsomagban|1 calendar included|1 Kalender inklusive
Saját foglalóoldal és QR-kód|Your own booking page and QR code|Eigene Buchungsseite und QR-Code
Vendégkezelés és feketelista|Client management and blacklist|Kundenverwaltung und Sperrliste
Ismétlődő időpontok|Recurring appointments|Wiederkehrende Termine
Google Naptár összekapcsolás|Google Calendar connection|Google Kalender verbinden
Befizetések, statisztikák és Excel-export|Payments, statistics, and Excel export|Zahlungen, Statistiken und Excel-Export
Magyar, angol és német felület|Hungarian, English, and German interface|Oberfläche auf Ungarisch, Englisch und Deutsch
Kipróbálom díjmentesen|Try it free|Kostenlos testen
TÖBB SZAKEMBERNEK|FOR SEVERAL SPECIALISTS|FÜR MEHRERE FACHKRÄFTE
Csapat|Team|Team
Egyedi ajánlat|Custom quote|Individuelles Angebot
A csapatod méretéhez és naptárigényéhez igazítva.|Tailored to your team's size and calendar needs.|Abgestimmt auf die Größe deines Teams und euren Kalenderbedarf.
Az Alap csomag funkciói|All Basic plan features|Alle Funktionen des Basistarifs
Több munkatárs külön naptára|Separate calendars for multiple staff members|Separate Kalender für mehrere Mitarbeiter
Közös szolgáltatói munkatér|Shared provider workspace|Gemeinsamer Anbieter-Arbeitsbereich
Munkatársonkénti időbeosztás|Individual staff schedules|Individuelle Zeitpläne pro Mitarbeiter
Párhuzamos foglalások|Parallel bookings|Parallele Buchungen
Csoportos órák és létszámkezelés|Group classes and capacity management|Gruppenkurse und Teilnehmerverwaltung
Ajánlatot kérek|Request a quote|Angebot anfordern
EGYEDI IGÉNYEKRE|FOR CUSTOM REQUIREMENTS|FÜR INDIVIDUELLE ANFORDERUNGEN
Egyedi|Custom|Individuell
Beszéljük meg|Let's discuss it|Lass uns darüber sprechen
Ha a szokásos kereteken túl gondolkodsz.|For needs beyond the usual setup.|Wenn du über den üblichen Rahmen hinausdenkst.
Az igényeid áttekintése|Review your requirements|Bedarfsanalyse
Nagyobb naptárkeret egyeztetése|Discuss a larger calendar allowance|Größeres Kalenderkontingent abstimmen
Bevezetési segítség egyeztetése|Discuss onboarding support|Unterstützung beim Einstieg abstimmen
Integrációs igények felmérése|Assess integration needs|Integrationsbedarf ermitteln
Egyedi fejlesztések külön megállapodással|Custom development by separate agreement|Individuelle Entwicklung nach gesonderter Vereinbarung
Felveszem a kapcsolatot|Get in touch|Kontakt aufnehmen
AMI KÜLÖN DÍJAS LEHET|POSSIBLE EXTRA COSTS|MÖGLICHE ZUSATZKOSTEN
Nincsenek elrejtve az apró részletek.|The small details aren't hidden.|Die kleinen Details werden nicht versteckt.
Az alábbi tételek nem részei a 4 000 Ft-os havidíjnak.|The following items are not included in the HUF 4,000 monthly fee.|Die folgenden Posten sind nicht im monatlichen Preis von 4.000 HUF enthalten.
SMS-küldés|SMS messaging|SMS-Versand
A beállított SMS-szolgáltató használati díja. Nem tartalmaz ingyenes havi SMS-keretet.|Usage fees charged by your SMS provider. No free monthly SMS allowance is included.|Nutzungsgebühren deines SMS-Anbieters. Kein kostenloses monatliches SMS-Kontingent enthalten.
Bankkártyás fizetés|Card payments|Kartenzahlung
A külső fizetési szolgáltató tranzakciós díjai.|Transaction fees charged by the payment provider.|Transaktionsgebühren des Zahlungsdienstleisters.
Külső számlázó|External invoicing service|Externer Rechnungsdienst
A választott számlázószolgáltatás saját előfizetési és használati díja.|Subscription and usage fees charged by your chosen invoicing service.|Eigene Abonnement- und Nutzungsgebühren des gewählten Rechnungsdienstes.
További naptárak és egyedi munkák|Extra calendars and custom work|Zusätzliche Kalender und individuelle Arbeiten
Külön ajánlat és megállapodás alapján.|Subject to a separate quote and agreement.|Nach gesondertem Angebot und Vereinbarung.
Előbb kipróbálnád?|Want to try it first?|Möchtest du es zuerst ausprobieren?
Hozd létre a saját munkaterületed, és állítsd be a szolgáltatásaidat.|Create your workspace and set up your services.|Erstelle deinen Arbeitsbereich und richte deine Leistungen ein.
EMBEREK VAGYUNK A RENDSZER MÖGÖTT|REAL PEOPLE BEHIND THE SYSTEM|MENSCHEN HINTER DEM SYSTEM
Kezdjük egy|Let's start with|Beginnen wir mit
jó beszélgetéssel.|a good conversation.|einem guten Gespräch.
Most csatlakoznál, csapatban dolgozol, vagy már a Ráérőt használod? Mutatjuk, hol érsz el minket.|Thinking of joining, working in a team, or already using Ráérő? Here's how to reach us.|Möchtest du starten, arbeitest du im Team oder nutzt du Ráérő bereits? So erreichst du uns.
Digit Marketing · Elérhetőségek|Digit Marketing · Contact details|Digit Marketing · Kontaktdaten
Még ismerkedünk|Just getting to know us|Wir lernen uns gerade kennen
Nézd meg a funkciókat, vagy indítsd el a saját próbaidődet.|Explore the features or start your free trial.|Entdecke die Funktionen oder starte deine kostenlose Testphase.
Már használom a rendszert|I already use the system|Ich nutze das System bereits
Saját fiókoddal közvetlenül is írhatsz az üzemeltetőnek.|Use your account to message the operator directly.|Über dein Konto kannst du dem Betreiber direkt schreiben.
Üzenetet írok|Send a message|Nachricht schreiben
Csapatban dolgozunk|We work as a team|Wir arbeiten im Team
Naptárkeretről, bevezetésről vagy egyedi igényekről egyeztetnél?|Want to discuss calendars, onboarding, or custom requirements?|Möchtest du Kalenderkontingente, den Einstieg oder individuelle Anforderungen besprechen?
EGYSZERŰBB SZERVEZÉS, TÖBB FIGYELEM|LESS ADMIN, MORE ATTENTION|WENIGER VERWALTUNG, MEHR AUFMERKSAMKEIT
A rendszer a munkádhoz igazodjon.|The system should fit your work.|Das System soll sich deiner Arbeit anpassen.
Mondd el, hányan dolgoztok, milyen szolgáltatásokat kínáltok, és mi venné le a legtöbb terhet a válladról.|Tell us how many people work with you, what services you offer, and what would take the most admin off your hands.|Erzähle uns, wie viele Personen ihr seid, welche Leistungen ihr anbietet und was euch am meisten entlasten würde.
Beszéljünk róla|Let's talk|Lass uns sprechen
`;
 for(const line of entries.split('\n')){const p=line.trim().split('|');if(p.length===3)dictionary.set(p[0],{en:p[1],de:p[2]});}
 const extra=`
Nincs találat. A megadott adatokkal új vendéget rögzítünk.|No match. We will create a new guest with these details.|Kein Treffer. Mit diesen Angaben wird ein neuer Kunde angelegt.
-tól| and up| aufwärts
Munkatárs|Team member|Mitarbeiter
Rendezés|Sort by|Sortieren
Név szerint|Name|Name
Ár szerint növekvő|Price: low to high|Preis: aufsteigend
Ár szerint csökkenő|Price: high to low|Preis: absteigend
A jelszavadat az e-mailben kapott hivatkozás megnyitása után állíthatod be.|Set your password after opening the link sent to your email address.|Dein Passwort legst du über den Link fest, den du per E-Mail erhältst.
A kiválasztott nap|Selected date|Ausgewähltes Datum
Foglalásod|Your booking|Deine Buchung
Foglalás részletei|Booking details|Buchungsdetails
Értékelés|Review|Bewertung
4 000 Ft|HUF 4,000|4.000 HUF
0 Ft|HUF 0|0 HUF
A látogatásodhoz már tartozik értékelés.|This visit already has a review.|Für diesen Besuch gibt es bereits eine Bewertung.
Az online foglalás letiltva. A szolgáltató saját időpontot továbbra is rögzíthet.|Online booking is blocked. The provider can still create an appointment.|Die Onlinebuchung ist gesperrt. Der Anbieter kann weiterhin einen Termin anlegen.
A vendéghez nincs e-mail-cím.|No email address is available for this guest.|Für diesen Kunden liegt keine E-Mail-Adresse vor.
Előbb adj meg új, jövőbeli időpontot.|Please select a new future time first.|Bitte wähle zuerst einen neuen zukünftigen Termin.
E-mailes értesítések és emlékeztetők|Email notifications and reminders|E-Mail-Benachrichtigungen und Erinnerungen
Szolgáltató értesítési e-mail-címe|Provider notification email|Benachrichtigungs-E-Mail des Anbieters
Az új vendégfoglaláshoz jóváhagyás szükséges|New guest bookings require approval|Neue Kundenbuchungen müssen bestätigt werden
Vendég értesítése új foglalásról, módosításról és lemondásról|Notify the guest about new bookings, changes and cancellations|Kunden über neue Buchungen, Änderungen und Stornierungen informieren
Szolgáltató értesítése új foglalásról, módosításról és lemondásról|Notify the provider about new bookings, changes and cancellations|Anbieter über neue Buchungen, Änderungen und Stornierungen informieren
Időpont előtti emlékeztető a vendégnek|Send an appointment reminder to the guest|Terminerinnerung an den Kunden senden
Hány órával előtte?|How many hours before?|Wie viele Stunden vorher?
Emlékeztető csatornája|Reminder channel|Erinnerungskanal
E-mail és SMS|Email and SMS|E-Mail und SMS
Számlázási adatok|Billing details|Rechnungsdaten
Gyors szalonfoglalás|Quick staff bookings|Schnelle Terminerfassung
Alapértelmezett szolgáltatás a szalon új időpontjaihoz|Default service for staff-created appointments|Standardleistung für intern angelegte Termine
Első elérhető szolgáltatás|First available service|Erste verfügbare Leistung
Név nélküli mentéskor ne kérjen külön megerősítést|Don't ask for confirmation when saving without a name|Beim Speichern ohne Namen keine Bestätigung anfordern
Csak a szalon saját időpontfelvitelére vonatkozik. A vendég maga választ szolgáltatást.|Only applies to appointments entered by staff. Guests choose their own service.|Gilt nur für intern angelegte Termine. Kunden wählen ihre Leistung selbst.
A pipa kivételével visszakapcsolhatod a figyelmeztetést.|Uncheck this to enable the warning again.|Entferne das Häkchen, um die Warnung wieder einzuschalten.
Csak azt kapcsold be, amit használsz. A korábbi időpontok és kiadott bérletek megmaradnak.|Enable only what you use. Existing appointments and issued passes remain intact.|Aktiviere nur, was du nutzt. Bestehende Termine und ausgestellte Mehrfachkarten bleiben erhalten.
Új vendég meghívása saját vendégfiók létrehozására|Invite new guests to create an account|Neue Kunden zur Kontoerstellung einladen
Ne küldjön meghívót|Do not send invitations|Keine Einladungen senden
Csak e-mailben|Email only|Nur per E-Mail
Csak SMS-ben|SMS only|Nur per SMS
E-mailben; ha nincs e-mail-cím, SMS-ben|Email; SMS if no email address is available|E-Mail; SMS, wenn keine E-Mail-Adresse vorhanden ist
SMS-ben; ha nincs telefonszám, e-mailben|SMS; email if no phone number is available|SMS; E-Mail, wenn keine Telefonnummer vorhanden ist
A foglalóoldalad QR-kódja|QR code for your booking page|QR-Code für deine Buchungsseite
QR-kód letöltése / nyomtatása|Download / print QR code|QR-Code herunterladen / drucken
Nyomtasd ki, vagy tedd ki a szalonban: a vendég közvetlenül nálad foglalhat.|Print it or display it in your business so guests can book directly.|Drucke ihn aus oder hänge ihn aus, damit Kunden direkt buchen können.
Megjelenés a szolgáltatókeresőben|Visibility in provider search|Anzeige in der Anbietersuche
A települést a kereséshez és a helyzet alapján történő ajánláshoz használjuk.|Your town is used for search and location-based suggestions.|Der Ort wird für die Suche und standortbezogene Vorschläge verwendet.
Kategóriák|Categories|Kategorien
Üresen a szakmád és a szolgáltatásaid alapján választunk kategóriát.|If empty, categories are inferred from your profession and services.|Ohne Auswahl werden Kategorien aus deinem Fachgebiet und deinen Leistungen abgeleitet.
Csak az itt megadott adatok nyilvánosak. A számlázási és API-beállításokat külön kezeljük.|Only the details entered here are public. Billing and API settings are kept separate.|Nur die hier eingegebenen Daten sind öffentlich. Rechnungs- und API-Einstellungen werden getrennt verwaltet.
Rövid bemutatkozás|Short introduction|Kurze Vorstellung
Részletes leírás|Detailed description|Ausführliche Beschreibung
Cím|Address|Adresse
Megközelítés|Directions|Anfahrt
Nyilvános telefon|Public phone number|Öffentliche Telefonnummer
Nyilvános email|Public email|Öffentliche E-Mail
Weboldal|Website|Webseite
Térképes hivatkozás|Map link|Kartenlink
Nyitvatartás leírása|Opening hours description|Beschreibung der Öffnungszeiten
Foglalási és lemondási feltételek|Booking and cancellation terms|Buchungs- und Stornierungsbedingungen
Adatlap mentése|Save profile|Profil speichern
Nyilvános oldal megnyitása|Open public page|Öffentliche Seite öffnen
Üzletfotók képcímei (soronként egy)|Business photo URLs (one per line)|Bildadressen des Unternehmens (eine pro Zeile)
Saját, HTTPS-en elérhető JPG, PNG vagy WebP képcímeket adj meg.|Use your own JPG, PNG or WebP images available over HTTPS.|Verwende eigene JPG-, PNG- oder WebP-Bilder, die über HTTPS erreichbar sind.
Profilkép / logó feltöltése|Upload profile image / logo|Profilbild / Logo hochladen
Borítókép feltöltése|Upload cover image|Titelbild hochladen
Üzletfotó hozzáadása|Add business photo|Unternehmensfoto hinzufügen
Képek|Images|Bilder
Kép feltöltése|Upload image|Bild hochladen
Letöltés|Download|Herunterladen
Exportálás|Export|Exportieren
Excel letöltése|Download Excel|Excel herunterladen
Naptár letöltése|Download calendar|Kalender herunterladen
Összes|All|Alle
Ma|Today|Heute
Elmúlt 1 hét|Last 7 days|Letzte 7 Tage
Mai időpontok|Today's appointments|Heutige Termine
Minden időpont|All appointments|Alle Termine
Rögzítésre váró befizetések|Payments to record|Zu erfassende Zahlungen
Rögzítésre vár|Awaiting recording|Erfassung ausstehend
Beérkezés rögzítése|Record receipt|Zahlungseingang erfassen
Alapértelmezett paraméterek|Default settings|Standardeinstellungen
Befizetés alapértelmezései|Payment defaults|Zahlungsstandards
Alapértelmezett fizetési mód|Default payment method|Standardzahlungsart
Alapértelmezett megjegyzés|Default note|Standardnotiz
Alapértelmezések mentése|Save defaults|Standards speichern
Nincs rögzítésre váró befizetés ebben az időszakban.|No payments to record for this period.|Für diesen Zeitraum sind keine Zahlungen zu erfassen.
Válassz szolgáltatást…|Choose a service…|Leistung wählen…
Másik szolgáltatás hozzáadása…|Add another service…|Weitere Leistung hinzufügen…
Minden szolgáltatás|All services|Alle Leistungen
Nem használok bérletet|I won't use a pass|Ich verwende keine Mehrfachkarte
Kuponkód|Coupon code|Gutscheincode
Bérlet|Pass|Mehrfachkarte
Érvényesítés|Apply|Anwenden
Válassz szolgáltatást|Choose a service|Leistung wählen
Hozzáadás|Add|Hinzufügen
Új üzenet|New message|Neue Nachricht
Üzenet|Message|Nachricht
Üzenet küldése|Send message|Nachricht senden
Üzenet az üzemeltetőnek|Message the operator|Nachricht an den Betreiber
Foglalási oldalam|My booking page|Meine Buchungsseite
Kezdő dátum|Start date|Startdatum
Záró dátum|End date|Enddatum
Kezdő idő|Start time|Beginn
Záró idő|End time|Ende
Szakaszok: munka, hatóidő, munka (opcionális)|Phases: work, waiting time, work (optional)|Phasen: Arbeit, Einwirkzeit, Arbeit (optional)
`;
 for(const line of extra.split('\n')){const p=line.trim().split('|');if(p.length===3)dictionary.set(p[0],{en:p[1],de:p[2]});}
 const operational=`
Munkaterület /|Workspace /|Arbeitsbereich /
Tesztpéldány · mesterséges mintaadatok, nem valódi befizetések.|Test instance · synthetic sample data, not real payments.|Testversion · künstliche Beispieldaten, keine echten Zahlungen.
A NAPOD, ÁTLÁTHATÓAN|YOUR DAY AT A GLANCE|DEIN TAG AUF EINEN BLICK
Kevesebb szervezés. Több idő a vendégeidre.|Less organising. More time for your clients.|Weniger Organisation. Mehr Zeit für deine Kunden.
Összesítők és naptárvezérlők elrejtése|Hide summaries and calendar controls|Übersichten und Kalendersteuerung ausblenden
Összesítők és naptárvezérlők megnyitása|Show summaries and calendar controls|Übersichten und Kalendersteuerung einblenden
Kijelölés törlése|Clear selection|Auswahl löschen
Mutass egy idősávra: a napot és az időt együtt kiemeljük.|Point to a time slot to highlight its day and time.|Zeige auf ein Zeitfeld, um Tag und Uhrzeit gemeinsam hervorzuheben.
Megerősített|Confirmed|Bestätigt
Kattints az üres idősávra a gyors felvitelhez.|Click an empty time slot to add an appointment quickly.|Klicke auf ein leeres Zeitfeld, um schnell einen Termin anzulegen.
RÁÉRŐ MUNKATÉR|RÁÉRŐ WORKSPACE|RÁÉRŐ ARBEITSBEREICH
Importálás|Import|Importieren
Foglalások és változások|Bookings and changes|Buchungen und Änderungen
Frissítés|Refresh|Aktualisieren
Vendég|Guest|Kunde
Szolgáltatás|Service|Leistung
Név nélküli vendég|Unnamed guest|Kunde ohne Namen
A ténylegesen beérkezett összegek nyilvántartása.|Records of money actually received.|Aufzeichnung der tatsächlich eingegangenen Beträge.
Az elkezdődött, elfogadott időpontok fennmaradó összege automatikusan megjelenik. Csak a beérkezés rögzítése után számít bevételnek.|Outstanding amounts for started, confirmed appointments appear automatically. They count as revenue only after receipt is recorded.|Offene Beträge begonnener, bestätigter Termine erscheinen automatisch. Als Umsatz gelten sie erst nach Erfassung des Zahlungseingangs.
Vendég / időpont|Guest / appointment|Kunde / Termin
Befizetés|Payment|Zahlung
Fizetés|Payment|Zahlung
Számla|Invoice|Rechnung
Kézzel rögzítve|Recorded manually|Manuell erfasst
Számla készítése|Create invoice|Rechnung erstellen
Kézi rögzítéskor a beérkezést te igazolod. A Stripe-befizetést csak ellenőrzött webhook írja jóvá. A számlázás külön művelet.|For manual entries, you confirm receipt. Stripe payments are credited only by verified webhooks. Invoicing is a separate action.|Bei manuellen Einträgen bestätigst du den Eingang. Stripe-Zahlungen werden nur durch geprüfte Webhooks gutgeschrieben. Die Rechnungsstellung erfolgt separat.
Kezdőnap|Start date|Startdatum
Zárónap|End date|Enddatum
Szűrés|Filter|Filtern
Ez a hónap|This month|Dieser Monat
Ez az év|This year|Dieses Jahr
Előző hónap|Previous month|Letzter Monat
Elmúlt 30 nap|Last 30 days|Letzte 30 Tage
Tényleges befizetés|Actual payments|Tatsächliche Zahlungen
A befizetés dátuma alapján, visszatérítésekkel csökkentve|Based on payment date, less refunds|Nach Zahlungsdatum, abzüglich Erstattungen
Befizetések száma|Number of payments|Anzahl der Zahlungen
Tervezett szolgáltatásérték|Planned service value|Geplanter Leistungswert
A foglalás dátuma alapján|Based on appointment date|Nach Termindatum
Bevétel szolgáltatásonként|Revenue by service|Umsatz nach Leistung
A több szolgáltatásos foglalások befizetését az árak arányában osztjuk fel.|Payments for bookings with multiple services are allocated in proportion to their prices.|Zahlungen für Buchungen mit mehreren Leistungen werden anteilig nach Preisen aufgeteilt.
Beérkezett összeg|Amount received|Eingegangener Betrag
Látogatások a kiválasztott időszakban|Visits in the selected period|Besuche im ausgewählten Zeitraum
Csak a saját vállalkozás foglalásai. A megjelenéshez kézi igazolás szükséges.|Only your own business's bookings. Attendance requires manual confirmation.|Nur Buchungen deines Unternehmens. Die Anwesenheit muss manuell bestätigt werden.
Összes foglalás|All bookings|Alle Buchungen
Bevételek alakulása naponta|Daily revenue trend|Tägliche Umsatzentwicklung
Napi összegek táblázata|Daily totals table|Tabelle der Tagessummen
Bevétel|Revenue|Umsatz
Belső üzenetek|Internal messages|Interne Nachrichten
Az üzenet mentés után akkor is megmarad, ha az emailértesítőt nem sikerül kézbesíteni.|The message remains saved even if the email notification cannot be delivered.|Die Nachricht bleibt gespeichert, auch wenn die E-Mail-Benachrichtigung nicht zugestellt werden kann.
Beszélgetés vendéggel|Conversation with a guest|Gespräch mit einem Kunden
Válassz vendéget|Choose a guest|Kunden auswählen
Bejelentkezve foglaló vendéggel indíthatsz beszélgetést. Az importált vendégből nem készül automatikusan fiók.|You can message guests who booked while logged in. Imported guests do not automatically receive an account.|Du kannst Kunden schreiben, die angemeldet gebucht haben. Importierte Kunden erhalten nicht automatisch ein Konto.
Itt állíthatod be a vállalkozást, a szokásos nyitvatartást és az értesítéseket. Kivételes napot, szabadságot vagy ebédszünetet a Naptárban adj meg.|Set up your business, regular opening hours, and notifications here. Add exceptional days, time off, or lunch breaks in the Calendar.|Hier stellst du Unternehmen, reguläre Öffnungszeiten und Benachrichtigungen ein. Besondere Tage, Urlaub oder Mittagspausen legst du im Kalender an.
Szépségszalon|Beauty salon|Schönheitssalon
Masszázs|Massage|Massage
Fodrászat|Hairdressing|Friseur
Műköröm|Nail services|Nageldesign
Pedikűr|Pedicure|Pediküre
Szempilla|Eyelashes|Wimpern
Személyi edzés|Personal training|Personal Training
Jóga és pilates|Yoga and Pilates|Yoga und Pilates
Tanácsadás|Consulting|Beratung
Egyéb|Other|Sonstiges
Egyéni módban a munkatársmenü és a naptár munkatársszűrője eltűnik. A vendégek az elsődleges szakemberhez foglalnak; a korábbi adatok megmaradnak.|In solo mode, the staff menu and calendar staff filter are hidden. Guests book the primary specialist; historical data is preserved.|Im Einzelbetrieb werden Mitarbeitermenü und Mitarbeiterfilter ausgeblendet. Kunden buchen bei der Hauptfachkraft; bisherige Daten bleiben erhalten.
Küldéshez állítsd be az SMTP- vagy Twilio-kapcsolatot és a percenként futó cronfeladatot. A hiányzó kapcsolat miatt várakozó levelek a küldési sorban látszanak.|Configure SMTP or Twilio and the minute-by-minute scheduled task. Messages waiting for missing connections appear in the delivery queue.|Richte SMTP oder Twilio und den minütlichen Cronjob ein. Nachrichten, die auf eine fehlende Verbindung warten, erscheinen in der Versandwarteschlange.
Vendégeid számlázója|Invoicing service for your clients|Rechnungsdienst für deine Kunden
Áfakulcs a vendégszámlákon|VAT rate on client invoices|Umsatzsteuersatz auf Kundenrechnungen
A saját vállalkozásod számlázási adatai az előfizetéshez tartoznak. A vendég számlázási adatait számlakiállításkor külön kell megadni. A számla a Befizetések menüből indítható.|Your business billing details are used for the subscription. Guest billing details are supplied separately when issuing an invoice. Start invoicing from Payments.|Die Rechnungsdaten deines Unternehmens gelten für das Abonnement. Kundenrechnungsdaten werden bei der Rechnungserstellung separat angegeben. Rechnungen startest du unter Zahlungen.
Beágyazás saját weboldalba|Embed on your website|In eigene Webseite einbetten
Engedélyezett weboldalak (soronként egy eredet)|Allowed websites (one origin per line)|Erlaubte Webseiten (eine Herkunft pro Zeile)
Beilleszthető kód|Embed code|Einbettungscode
Önálló webkomponens, iframe nélkül, elkülönített stílusokkal. A befogadó oldal pontos https://domain.hu címét engedélyezni kell. A foglalás adatkezelési tájékoztatóját a befogadó oldalon is tedd elérhetővé.|A standalone web component without an iframe, with isolated styles. Allow the hosting site's exact HTTPS origin. Make the booking privacy notice available on that site too.|Eigenständige Webkomponente ohne iframe mit getrennten Stilen. Erlaube die genaue HTTPS-Herkunft der einbettenden Seite und stelle dort auch die Datenschutzhinweise zur Buchung bereit.
Időpontkezdés és meg nem jelenés|Appointment starts and no-shows|Terminbeginn und Nichterscheinen
Milyen gyakran kezdődhet új időpont?|How often can a new appointment start?|In welchen Abständen können neue Termine beginnen?
Egész óránként|On the hour|Zur vollen Stunde
Az óránkénti kezdés egész órához igazodik. Más lépésköz a napi nyitás percétől indul.|Hourly starts align with full hours. Other intervals start from the day's opening time.|Stündliche Termine beginnen zur vollen Stunde. Andere Intervalle richten sich nach der Öffnungsminute des Tages.
Alapértelmezett meg nem jelenési díj (Ft)|Default no-show fee (HUF)|Standardgebühr bei Nichterscheinen (HUF)
Nem kötelező: 0 Ft-tal kikapcsolható. Jelöléskor külön is megadható; nem terhelünk automatikusan bankkártyát.|Optional: set to HUF 0 to disable. You can also set it when recording a no-show. No card is charged automatically.|Optional: Mit 0 HUF deaktivieren. Die Gebühr kann auch beim Erfassen festgelegt werden. Es wird keine Karte automatisch belastet.
Csak az általad újonnan felvitt, még nem regisztrált vendégnek. A meghívó 30 napig érvényes, és a belépés után kapcsolja össze a foglalásait a saját fiókjával.|Only for newly added guests who are not yet registered. Invitations are valid for 30 days and link bookings after login.|Nur für neu angelegte, noch nicht registrierte Kunden. Die Einladung gilt 30 Tage und verknüpft Buchungen erst nach der Anmeldung.
Szolgáltatói kapcsolatok|Provider integrations|Anbieter-Integrationen
A titkos kulcsok titkosítva tárolódnak. Az üresen hagyott mezők megtartják a korábbi értéket.|Secret keys are stored encrypted. Empty fields keep the previous value.|Geheime Schlüssel werden verschlüsselt gespeichert. Leere Felder behalten den bisherigen Wert.
Számlázók|Invoicing services|Rechnungsdienste
Billingo számlatömb azonosító|Billingo invoice block ID|Billingo-Rechnungsblock-ID
Számlázz.hu Számla Agent-kulcs|Számlázz.hu Invoice Agent key|Számlázz.hu-Rechnungs-Agent-Schlüssel
Stripe — vendégek kártyás fizetése|Stripe — guest card payments|Stripe — Kartenzahlungen von Kunden
Webhook aláírási titok|Webhook signing secret|Webhook-Signaturgeheimnis
A közös levelezést, SMS-t és közösségi belépést a főadmin állítja be. Stripe webhook:|Shared email, SMS, and social login are configured by the platform admin. Stripe webhook:|Gemeinsame E-Mail-, SMS- und Social-Login-Verbindungen richtet der Plattform-Admin ein. Stripe-Webhook:
Kapcsolatok mentése|Save integrations|Integrationen speichern
Értesítési küldési sor|Notification delivery queue|Benachrichtigungs-Warteschlange
Esedékes küldések most|Send due messages now|Fällige Nachrichten jetzt senden
Alapcsomag · 4 000 Ft / hó|Basic plan · HUF 4,000 / month|Basistarif · 4.000 HUF / Monat
Fizetendő végösszeg, alanyi adómentesen.|Final amount payable, VAT exempt.|Zu zahlender Endbetrag, umsatzsteuerbefreit.
Fizetés bankkártyával / Stripe|Pay by card / Stripe|Mit Karte / Stripe bezahlen
A próbaidő két hónapja 0 Ft-os; erről nem állítunk ki számlát. A fizetős havi díjak határideje az időszak kezdetétől 21 nap.|The two-month trial is free and is not invoiced. Paid monthly charges are due 21 days after the period begins.|Die zweimonatige Testphase ist kostenlos und wird nicht in Rechnung gestellt. Monatliche Gebühren sind 21 Tage nach Beginn des Zeitraums fällig.
Az üzemeltető még nem adta meg a bankadatokat. Írj neki a fenti gombbal.|The operator has not entered bank details yet. Use the message button above.|Der Betreiber hat noch keine Bankdaten angegeben. Schreibe ihm über die Schaltfläche oben.
Közlemény:|Payment reference:|Verwendungszweck:
A banki beérkezést a főadmin hagyja jóvá. Egy átutalás csak egyszer írható jóvá.|Bank receipts are approved by the platform admin. Each transfer can only be credited once.|Bankeingänge werden vom Plattform-Admin bestätigt. Jede Überweisung kann nur einmal gutgeschrieben werden.
Havi díjtételek|Monthly charges|Monatliche Gebühren
Időszak|Period|Zeitraum
Fizetési határidő|Payment deadline|Zahlungsfrist
Próbaidő · díjmentes|Trial · free|Testphase · kostenlos
Díjmentes · nincs számla|Free · no invoice|Kostenlos · keine Rechnung
Tranzakcióim|My transactions|Meine Transaktionen
Még nincs tényleges befizetés. A próbaidő díjmentes tételeit fent látod.|No actual payments yet. Free trial periods are shown above.|Noch keine tatsächlichen Zahlungen. Die kostenlosen Testzeiträume siehst du oben.
Számláim|My invoices|Meine Rechnungen
A próbaidőről nincs számla. Fizetős befizetés után az üzemeltető állíthat ki számlát.|There is no invoice for the trial. The operator can issue an invoice after a paid transaction.|Für die Testphase gibt es keine Rechnung. Nach einer Zahlung kann der Betreiber eine Rechnung ausstellen.
Kedvezmények és bérletek létrehozása, kezelése.|Create and manage discounts and passes.|Rabatte und Mehrfachkarten erstellen und verwalten.
Bérlet hozzárendelése vendéghez|Assign a pass to a guest|Mehrfachkarte einem Kunden zuweisen
Inaktív · Csak kóddal|Inactive · Code only|Inaktiv · Nur mit Code
Még nincs bérlet. Az Új bérlet gombbal hozhatod létre az elsőt.|No passes yet. Use New pass to create the first one.|Noch keine Mehrfachkarte. Mit Neue Mehrfachkarte kannst du die erste erstellen.
A meglévő foglalások árai és a kiadott bérletek feltételei szerkesztéskor nem változnak.|Editing does not change existing booking prices or terms of issued passes.|Die Bearbeitung ändert weder Preise bestehender Buchungen noch Bedingungen ausgestellter Mehrfachkarten.
Ezeket az adatokat látják a vendégek a foglalóoldalon.|Guests see these details on your booking page.|Diese Daten sehen Kunden auf deiner Buchungsseite.
Törlés|Delete|Löschen
Logó képcíme|Logo image URL|Bildadresse des Logos
Borítókép címe|Cover image URL|Bildadresse des Titelbildes
Külső kapcsolatok: levelezés, közösségi belépés, fizetés és számlázás. Az üres titkos mező megőrzi a korábbi kulcsot.|External connections: email, social login, payments, and invoicing. Empty secret fields keep existing keys.|Externe Verbindungen: E-Mail, Social Login, Zahlung und Rechnungsstellung. Leere Geheimnisfelder behalten vorhandene Schlüssel.
Saját naptárak kapcsolatai|Your calendar connections|Eigene Kalenderverbindungen
Munkatársanként külön Google-fiók és naptár csatlakoztatható. A központi belépési és SMS-kulcsokat a főadmin kezeli.|Each staff member can connect a separate Google account and calendar. Central login and SMS keys are managed by the platform admin.|Jeder Mitarbeiter kann ein eigenes Google-Konto und einen Kalender verbinden. Zentrale Anmelde- und SMS-Schlüssel verwaltet der Plattform-Admin.
Esedékes|Due|Fällig
Nincs küldési feladat.|No delivery tasks.|Keine Versandaufgaben.
A foglalásokat a kapcsolt naptárba küldjük. A naptár külső elfoglaltságait az új foglalásoknál figyelembe vesszük. Google-ben módosított Ráérő-időpontot a Ráérő felületén egyeztess.|Bookings are sent to the connected calendar. External busy periods are considered for new bookings. Reconcile Ráérő appointments changed in Google within Ráérő.|Buchungen werden an den verbundenen Kalender gesendet. Externe Belegungen werden bei neuen Buchungen berücksichtigt. Gleiche in Google geänderte Ráérő-Termine in Ráérő ab.
A központi Google-kapcsolatot a főadminnak még be kell állítania.|The platform admin still needs to configure the central Google connection.|Der Plattform-Admin muss die zentrale Google-Verbindung noch einrichten.
Három betűt vagy számjegyet írj be. Új vendégnél töltsd ki az adatokat.|Enter three letters or digits. Fill in the details for a new guest.|Gib drei Buchstaben oder Ziffern ein. Fülle bei einem neuen Kunden die Daten aus.
Példa: 95 perces hajfestés = 30 perc munka, 35 perc hatóidő, 30 perc munka. A középső 35 percben másik vendég fogadható, ha a munkája belefér. Így add meg: 30,35,30.|Example: 95-minute hair colouring = 30 minutes work, 35 minutes waiting, 30 minutes work. Another guest can be served during the middle 35 minutes if their work fits. Enter: 30,35,30.|Beispiel: 95 Minuten Haarfärben = 30 Minuten Arbeit, 35 Minuten Einwirkzeit, 30 Minuten Arbeit. In den mittleren 35 Minuten kann ein anderer Kunde bedient werden, wenn dessen Arbeit hineinpasst. Eingabe: 30,35,30.
Egyszerre egy aktív vendég. Ha van hatóidő, a szakaszok megadásával arra az időre másik munka is foglalható.|One active guest at a time. Define a waiting phase to allow another service during that time.|Ein aktiver Kunde zur gleichen Zeit. Mit einer Einwirkphase kann in dieser Zeit eine andere Leistung gebucht werden.
Érvényesség kezdete|Valid from|Gültig ab
Érvényesség vége|Valid until|Gültig bis
Kedvezmény típusa|Discount type|Rabattart
Százalék|Percentage|Prozent
Kedvezmény értéke|Discount amount|Rabatthöhe
Összes felhasználás (üres: korlátlan)|Total uses (empty: unlimited)|Gesamte Nutzungen (leer: unbegrenzt)
Vendégenkénti korlát|Limit per guest|Limit pro Kunde
Használható szolgáltatások|Eligible services|Gültige Leistungen
Szolgáltatások kiválasztása|Choose services|Leistungen auswählen
Ha egyet sem jelölsz ki, mindegyik szolgáltatásra használható.|If none are selected, it applies to all services.|Ohne Auswahl gilt es für alle Leistungen.
A kupon működése|Coupon settings|Gutscheineinstellungen
A kupon felhasználható|Coupon can be used|Gutschein kann verwendet werden
Ezzel kapcsolhatod ki vagy be a kupont, törlés nélkül.|Enable or disable the coupon without deleting it.|Aktiviere oder deaktiviere den Gutschein, ohne ihn zu löschen.
Megjelenjen a foglalóoldalon|Show on the booking page|Auf der Buchungsseite anzeigen
Kikapcsolva csak az tudja használni, aki ismeri a kuponkódot.|When disabled, only those who know the code can use it.|Wenn deaktiviert, kann ihn nur nutzen, wer den Code kennt.
Csak új vendégeknek|New guests only|Nur für Neukunden
Csak annak jár, akinek még nem volt igazolt látogatása nálad.|Only for guests without a previously confirmed visit.|Nur für Kunden ohne bisher bestätigten Besuch bei dir.
Ingyenes első alkalomhoz válassz 100% kedvezményt és az első alkalom feltételt.|For a free first visit, choose a 100% discount and the first-visit condition.|Für einen kostenlosen Erstbesuch wähle 100 % Rabatt und die Erstbesuch-Bedingung.
Leírás|Description|Beschreibung
Alkalmak száma|Number of visits|Anzahl der Besuche
Meddig használható?|How long is it valid?|Wie lange ist es gültig?
Mikortól számoljuk a napokat?|When does the validity period start?|Ab wann zählt die Gültigkeitsdauer?
A kiadástól / megadott kezdőnaptól|From issue / specified start date|Ab Ausstellung / angegebenem Startdatum
Az első bérletes időponttól|From the first booked visit using the pass|Ab dem ersten mit der Karte gebuchten Termin
Érvényesség napokban|Validity in days|Gültigkeit in Tagen
Konkrét kezdő vagy záró dátum (nem kötelező)|Specific start or end date (optional)|Festes Start- oder Enddatum (optional)
Legkorábban ettől|Earliest valid date|Frühestens ab
Legkésőbb eddig|Latest valid date|Spätestens bis
A megadott dátumokon kívül nem használható. Záró dátum esetén az a végső határ.|Not valid outside these dates. An end date is the final limit.|Außerhalb dieser Daten nicht gültig. Ein Enddatum ist die endgültige Grenze.
Felhasználási feltételek|Terms of use|Nutzungsbedingungen
A bérlet kiadható új vendégnek|Pass can be assigned to new guests|Karte kann neuen Kunden zugewiesen werden
Foglaláskor lefoglalunk egy alkalmat. Lemondáskor visszaadjuk; megjelenés vagy meg nem jelenés jelölésekor elfogy. Az első időponttól számolt érvényesség a foglalt látogatás napjához igazodik, nem a foglalás készítésének napjához.|A visit is reserved when booking and returned on cancellation. Attendance or no-show consumes it. First-use validity starts on the booked visit date, not the date the booking was created.|Bei der Buchung wird ein Besuch reserviert und bei Stornierung zurückgegeben. Erscheinen oder Nichterscheinen verbraucht ihn. Die Gültigkeit ab Erstnutzung beginnt am gebuchten Besuchstag, nicht am Tag der Buchungserstellung.
ONLINE IDŐPONTFOGLALÁS|ONLINE APPOINTMENT BOOKING|ONLINE-TERMINBUCHUNG
Időt magadra.|Time for yourself.|Zeit für dich.
Egyszerű választás. Több szabadidő.|Easy choices. More free time.|Einfach wählen. Mehr Freizeit.
Még nincs meghirdetett alkalom.|No sessions have been published yet.|Es wurden noch keine Termine veröffentlicht.
Válassz szolgáltatást és időpontot. Regisztráció nem szükséges.|Choose a service and a time. No registration required.|Wähle eine Leistung und einen Termin. Keine Registrierung erforderlich.
Először válassz szolgáltatást.|Choose a service first.|Wähle zuerst eine Leistung.
Főadminbelépés|Platform admin login|Plattform-Admin-Anmeldung
Külön üzemeltetői jelszó szükséges.|A separate operator password is required.|Ein separates Betreiberpasswort ist erforderlich.
Nem adtad meg a vendég nevét.|You haven't entered the guest's name.|Du hast den Kundennamen nicht angegeben.
Elmented „Név nélküli vendég” néven?|Save as an unnamed guest?|Als Kunde ohne Namen speichern?
Később ne kérdezze meg|Don't ask again|Nicht erneut fragen
Megadom a nevet|Enter a name|Namen eingeben
Mentés név nélkül|Save without a name|Ohne Namen speichern
`;
 for(const line of operational.split('\n')){const p=line.trim().split('|');if(p.length===3)dictionary.set(p[0],{en:p[1],de:p[2]});}
 const followup=`Találj szakembert.|Find a professional.|Finde die passende Fachkraft.
Foglalj időpontot.|Book an appointment.|Buche einen Termin.
Fodrász, masszőr, edző vagy más szakember — keress a közeledben, nézd meg a szolgáltatásokat, és válassz szabad időpontot.|Hairdresser, massage therapist, trainer or another professional — search nearby, browse services and choose an available appointment.|Friseur, Masseur, Trainer oder eine andere Fachkraft — suche in deiner Nähe, vergleiche Leistungen und wähle einen freien Termin.
Szolgáltatások kategóriánként|Services by category|Leistungen nach Kategorie
Foglalható szolgáltatók|Providers accepting bookings|Anbieter mit Onlinebuchung
Saját vállalkozásod van?|Running your own business?|Du hast ein eigenes Unternehmen?
Legyen saját foglalóoldalad.|Get your own booking page.|Erstelle deine eigene Buchungsseite.
Időpontok, vendégek,|Appointments, clients,|Termine, Kunden,
egy munkaterületen.|one workspace.|ein Arbeitsbereich.
Csomagok és árak|Plans and pricing|Pakete und Preise
Külön díjas tételek|Additional charges|Zusätzliche Kosten
Kezdőnap|Start date|Startdatum
Végdátum (nem kötelező)|End date (optional)|Enddatum (optional)
Korlátlan|Unlimited|Unbegrenzt
0 vagy üres darabszám és üres végdátum: folyamatos ismétlés. A rendszer egy évre előre készíti el az alkalmakat, majd naponta bővíti a sorozatot.|Zero or an empty count with no end date means ongoing repetition. Appointments are created one year ahead and the series is extended daily.|Null oder eine leere Anzahl ohne Enddatum bedeutet eine fortlaufende Wiederholung. Termine werden ein Jahr im Voraus erstellt und täglich ergänzt.
Felhívom|Call|Anrufen
Elfelejtett jelszó|Forgot password|Passwort vergessen
Új jelszó|New password|Neues Passwort
Jelszó megváltoztatása|Change password|Passwort ändern
Jelenlegi jelszó|Current password|Aktuelles Passwort
Új jelszó még egyszer|Repeat new password|Neues Passwort wiederholen
Visszaállító levél kérése|Send reset email|E-Mail zum Zurücksetzen senden
Jelszó mentése|Save password|Passwort speichern
Jelszó mentve.|Password saved.|Passwort gespeichert.
Belépés és biztonság|Login and security|Anmeldung und Sicherheit
Jelszó beállítása e-mailben|Set a password by email|Passwort per E-Mail festlegen
A közösségi fiók összekapcsolása után azzal is beléphetsz ebbe a vállalkozásba.|Link a social account to use it to sign in to this business.|Verknüpfe ein Social-Media-Konto, um dich damit bei diesem Unternehmen anzumelden.
A közösségi belépés központi beállítása még nem készült el.|Social sign-in has not been configured by the platform yet.|Die Plattform hat die Anmeldung über soziale Netzwerke noch nicht eingerichtet.
Közösségi fiók ellenőrizve. Add meg a vállalkozás adatait a csatlakozáshoz.|Social account verified. Enter your business details to join.|Social-Media-Konto bestätigt. Gib deine Unternehmensdaten ein, um dich zu registrieren.
Ha van ilyen szolgáltatói fiók, a visszaállító levél küldési sorba került.|If a matching provider account exists, a reset email has been queued.|Falls ein passendes Anbieterkonto existiert, wurde eine E-Mail zum Zurücksetzen eingeplant.
Sorozat leállítása|Stop series|Serie beenden
A jelenlegi jelszó hibás.|The current password is incorrect.|Das aktuelle Passwort ist falsch.
Túl sok kérés. Próbáld újra később.|Too many requests. Try again later.|Zu viele Anfragen. Versuche es später erneut.
`;
 for(const line of followup.split('\n')){const p=line.trim().split('|');if(p.length===3)dictionary.set(p[0],{en:p[1],de:p[2]});}
 const showcase=`A napod kézben.|Your day, under control.|Dein Tag im Griff.
A vendégeid jó helyen.|Your clients, in good hands.|Deine Kunden in guten Händen.
Hangulatkép|Illustrative scene|Stimmungsbild
Felfedezem a szolgáltatókat|Explore providers|Anbieter entdecken
Szakember a közeledben|Professionals nearby|Fachkräfte in deiner Nähe
Valóban szabad időpontok|Available appointments|Verfügbare Termine
Foglalás néhány kattintással|Book in a few clicks|Mit wenigen Klicks buchen
Ráérő · Naptár|Ráérő · Calendar|Ráérő · Kalender
A Ráérő naptára · mintaadatok|The Ráérő calendar · sample data|Der Ráérő-Kalender · Beispieldaten
MINDEN, AMI AZ INDULÁSHOZ KELL|EVERYTHING YOU NEED TO GET STARTED|ALLES FÜR DEINEN START
Saját foglaláskezelés|Manage your bookings|Eigene Buchungen verwalten`;
 for(const line of showcase.split('\n')){const p=line.trim().split('|');if(p.length===3)dictionary.set(p[0],{en:p[1],de:p[2]});}
 const referenceStyle=`Szolgáltatóknak|For providers|Für Anbieter
A következő időpontod|Your next appointment|Dein nächster Termin
itt kezdődik.|starts here.|beginnt hier.
Keress szolgáltatót,|Find a provider,|Finde einen Anbieter,
válassz időpontot.|choose an appointment.|wähle einen Termin.
Keress név, szolgáltatás vagy település szerint, és foglalj közvetlenül a kiválasztott szakemberhez.|Search by name, service or town, and book directly with your chosen professional.|Suche nach Name, Leistung oder Ort und buche direkt bei der gewählten Fachkraft.
Belépés nélkül is használható|No sign-in required|Ohne Anmeldung nutzbar
✓ Keresés regisztráció nélkül|✓ Search without registering|✓ Ohne Registrierung suchen
✓ Naprakész szabad időpontok|✓ Up-to-date availability|✓ Aktuelle freie Termine
✓ Közvetlen foglalás a szolgáltatóhoz|✓ Book directly with the provider|✓ Direkt beim Anbieter buchen
Szolgáltatóként csatlakoznál?|Want to join as a provider?|Möchtest du als Anbieter starten?
Saját foglalóoldal, naptár és vendégkezelés. Próbáld ki két hónapig díjmentesen.|Your own booking page, calendar and client management. Try it free for two months.|Eigene Buchungsseite, Kalender und Kundenverwaltung. Zwei Monate kostenlos testen.
Kevesebb egyeztetés.|Less back and forth.|Weniger Abstimmung.
Átláthatóbb munkanap.|A clearer working day.|Ein übersichtlicher Arbeitstag.
A vendégeid online foglalnak. Te egy helyen kezeled az időpontokat, az elérhetőségeket és a befizetéseket.|Your clients book online. Manage appointments, contact details and payments in one place.|Deine Kunden buchen online. Verwalte Termine, Kontaktdaten und Zahlungen an einem Ort.
További funkciók megtekintése|Show more features|Weitere Funktionen anzeigen
Kevesebb funkció megjelenítése|Show fewer features|Weniger Funktionen anzeigen
Egyszerű csomagok.|Simple plans.|Einfache Pakete.
Átlátható feltételek.|Clear terms.|Klare Konditionen.
Egyedül dolgozol vagy csapatban? Válaszd a vállalkozásodhoz illő megoldást.|Working alone or with a team? Choose the right plan for your business.|Alleine oder im Team? Wähle die passende Lösung für dein Unternehmen.
Összes funkció|All features|Alle Funktionen
EGYSZERŰ INDULÁS|A SIMPLE START|EINFACH STARTEN
Mi kerülhet külön pénzbe?|What may cost extra?|Was kann zusätzlich kosten?
A foglaláshoz nem szükséges regisztráció.|No registration is needed to book.|Für die Buchung ist keine Registrierung nötig.
Jelképes naptárillusztráció|Symbolic calendar illustration|Symbolische Kalenderillustration`;
 for(const line of referenceStyle.split('\n')){const p=line.trim().split('|');if(p.length===3)dictionary.set(p[0],{en:p[1],de:p[2]});}
 const accountPolish=`Vállalkozás keresése név alapján|Find a business by name|Unternehmen nach Namen suchen
A választott napon nincs szabad időpont.|There are no available appointments on the selected day.|Am ausgewählten Tag sind keine Termine verfügbar.`;
 for(const line of accountPolish.split('\n')){const p=line.trim().split('|');if(p.length===3)dictionary.set(p[0],{en:p[1],de:p[2]});}
 const patterns=[
  [/^Túl sok próbálkozás\. Próbáld újra (\d+) másodperc múlva\.$/,m=>language==='en'?`Too many attempts. Try again in ${m[1]} seconds.`:`Zu viele Versuche. Bitte in ${m[1]} Sekunden erneut versuchen.`],
  [/^A választott napon nincs szabad időpont\. A mai naphoz legközelebbi szabad napot mutatjuk: (.+)$/,m=>language==='en'?'There are no available appointments on the selected day. Showing the nearest available day from today: '+m[1]:'Am ausgewählten Tag sind keine Termine verfügbar. Der nächstmögliche freie Tag ab heute wird angezeigt: '+m[1]],
  [/^(\d+) funkció$/,m=>m[1]+(language==='en'?' features':' Funktionen')],
  [/^(\d+) szolgáltató(.*)$/,m=>language==='en'?`${m[1]} providers${m[2]}`:`${m[1]} Anbieter${m[2]}`],
  [/^(\d+) találat$/,m=>language==='en'?`${m[1]} results`:`${m[1]} Treffer`],
  [/^([＋+↓↑☎↗⌃⌄]\s+)(.+)$/,m=>m[1]+translate(m[2])],
  [/^Google Naptár · (.+)$/,m=>(language==='en'?'Google Calendar':'Google Kalender')+' · '+m[1]],
  [/^Próbaidő vége: (.+)$/,m=>(language==='en'?'Trial ends: ':'Testphase endet: ')+m[1]],
  [/^Főadmin \/(.*)$/,m=>(language==='en'?'Platform admin /':'Plattform-Admin /')+m[1]],
  [/^(.+? · )(\d+) perc(.*)$/,m=>m[1]+m[2]+(language==='en'?' min':' Min.')+m[3]],
  [/^(\d+) percenként$/,m=>language==='en'?`Every ${m[1]} minutes`:`Alle ${m[1]} Minuten`],
  [/^(\d+) p munka · (\d+) p hatóidő · (\d+) p munka$/,m=>language==='en'?`${m[1]} min work · ${m[2]} min waiting · ${m[3]} min work`:`${m[1]} Min. Arbeit · ${m[2]} Min. Einwirkzeit · ${m[3]} Min. Arbeit`],
  [/^(\d+) perc(.*)$/,m=>(language==='en'?`${m[1]} min`:`${m[1]} Min.`)+m[2].replace(/ Ft/g,' HUF')],
  [/^(\d+) PERC$/,m=>language==='en'?`${m[1]} MIN`:`${m[1]} MIN.`],
  [/^(\d+) p(.*)$/,m=>(language==='en'?`${m[1]} min`:`${m[1]} Min.`)+m[2].replace(/ Ft/g,' HUF')],
  [/^(\d+) értékelés$/,m=>language==='en'?`${m[1]} reviews`:`${m[1]} Bewertungen`],
  [/^A sorozat (.+) alkalmát nem lehet menteni: (.+)$/,m=>language==='en'?`Cannot save the series appointment at ${m[1]}: ${translate(m[2])}`:`Der Serientermin am ${m[1]} kann nicht gespeichert werden: ${translate(m[2])}`],
  [/^(\d+) alkalom$/,m=>language==='en'?`${m[1]} appointments`:`${m[1]} Termine`],
  [/^(\d+) ismétlődő időpontot elmentettünk\.$/,m=>language==='en'?`${m[1]} recurring appointments saved.`:`${m[1]} wiederkehrende Termine gespeichert.`],
  [/^Nap módosítása(.*)$/,m=>(language==='en'?'Change date':'Datum ändern')+m[1]],
  [/^Felismert település: (.+)\. Szükség esetén módosíthatod\.$/,m=>language==='en'?`Detected town: ${m[1]}. You can change it if needed.`:`Erkannter Ort: ${m[1]}. Du kannst ihn bei Bedarf ändern.`],
  [/^(Google|Facebook|Apple)-fiókkal belépés \/ regisztráció$/,m=>language==='en'?`Log in / sign up with ${m[1]}`:`Mit ${m[1]} anmelden / registrieren`],
  [/^(Google|Facebook|Apple)-fiókkal belépés$/,m=>language==='en'?`Log in with ${m[1]}`:`Mit ${m[1]} anmelden`],
  [/^(Google|Facebook|Apple) · még nincs beállítva$/,m=>language==='en'?`${m[1]} · not configured yet`:`${m[1]} · noch nicht eingerichtet`],
  [/^Ctrl \+ kattintás vagy húzás: több nap kijelölése\.$/,()=>language==='en'?'Ctrl + click or drag to select multiple days.':'Strg + klicken oder ziehen, um mehrere Tage auszuwählen.'],
  [/^⌘ \+ kattintás vagy húzás: több nap kijelölése\.$/,()=>language==='en'?'⌘ + click or drag to select multiple days.':'⌘ + klicken oder ziehen, um mehrere Tage auszuwählen.']
 ];
 function translate(text){if(language==='hu')return text;const found=dictionary.get(text)||window.raeroFeatureTranslations?.[text];if(found)return found[language];for(const [regex,format]of patterns){const m=text.match(regex);if(m)return format(m);}return text;}
 window.raeroTranslate=translate;
 const originals=new WeakMap(),attributes=new WeakMap();let timer,observer;
 const skip='script,style,textarea,input,svg,[contenteditable],[data-no-i18n],.brand,.visitor-brand,.rich-text,.rich-content,.editor-body,.discovery-provider-name h3,.guest-profile-heading h1,.guest-booking h3,.entity-grid h3,.booking-hero h1,.directory-identity h3,.directory-card h3,.mail-preview';
 function mountSwitchers(){const hosts=[document.querySelector('.visitor-menu nav'),document.querySelector('.shell .top-actions'),document.querySelector('.platform-topbar')];for(const host of hosts){if(!host||host.querySelector('.language-switch'))continue;const label=document.createElement('label');label.className='language-switch';label.dataset.noI18n='';label.innerHTML='<span class="language-switch-label">🌐</span><select aria-label="Language / Nyelv / Sprache"><option value="hu">HU · Magyar</option><option value="en">EN · English</option><option value="de">DE · Deutsch</option></select>';host.append(label);label.querySelector('select').value=language;label.querySelector('select').onchange=e=>{language=e.target.value;window.raeroLanguage=language;try{localStorage.setItem('raero-language',language);}catch{}apply();};}document.querySelectorAll('.language-switch select').forEach(select=>select.value=language);}
 function apply(){observer?.disconnect();document.documentElement.lang=language;mountSwitchers();const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);let node;while(node=walker.nextNode()){
  const parent=node.parentElement;if(!parent||parent.closest(skip))continue;
  if(location.pathname==='/foadmin'&&parent.closest('#app')&&!document.querySelector('.platform-shell')&&!document.querySelector('#platform-login'))continue;
  if(location.pathname==='/fiokom'&&!document.querySelector('.guest-profile,.guest-auth-page')&&parent.closest('#app'))continue;
  const saved=originals.get(node),base=saved&&saved.last===node.data?saved.base:node.data,key=base.trim();if(!key)continue;if(parent.tagName==='OPTION'&&!parent.hasAttribute('value'))parent.value=base;
  const value=base.replace(key,translate(key));if(node.data!==value)node.data=value;originals.set(node,{base,last:value});
 }
 for(const element of document.querySelectorAll('[placeholder],[title],[aria-label]')){if(element.closest('svg,[data-no-i18n]'))continue;const saved=attributes.get(element)||{};for(const name of ['placeholder','title','aria-label']){if(!element.hasAttribute(name))continue;const current=element.getAttribute(name),previous=saved[name],base=previous&&current===previous.last?previous.base:current,value=translate(base);if(value!==current)element.setAttribute(name,value);saved[name]={base,last:value};}attributes.set(element,saved);}
 observer?.observe(document.body,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['placeholder','title','aria-label']});}
 window.raeroApplyLanguage=apply;
 observer=new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(apply,30);});apply();
 document.addEventListener('invalid',event=>{const field=event.target;if(!field.setCustomValidity)return;if(field.validity.customError)field.setCustomValidity(translate(field.validationMessage));else if(field.validity.valueMissing)field.setCustomValidity(translate('Töltsd ki ezt a mezőt.'));},true);
 document.addEventListener('change',event=>{if(event.target.setCustomValidity)event.target.setCustomValidity('');},true);
 document.addEventListener('input',event=>{if(event.target.setCustomValidity)event.target.setCustomValidity('');},true);
})();
