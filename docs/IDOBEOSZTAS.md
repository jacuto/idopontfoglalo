# Nyitvatartás, ebédszünet és szabadság

A szokásos napi nyitást és zárást a **Beállítások → Alapvető beállítások** fülön add meg. A kivételeket a **Naptár → Nyitvatartás és szünetek** gombbal, vagy a napi/heti naptár napfejlécének fogaskerekével nyithatod meg.

## Eltérő nyitvatartás egy adott napon

1. Válaszd ki a napot.
2. Pipáld be az **Ezen a napon eltérő nyitvatartás** mezőt.
3. Add meg a nyitást és zárást, percre pontosan; a szokásosnál korábbi vagy későbbi időpont is lehet.
4. Ha egész nap zárva vagy, pipáld be az **Egész nap zárva** mezőt is.
5. Kattints a **Napi nyitvatartás mentése** gombra.

A szokásos nyitvatartás visszaállításához vedd ki az eltérő nyitvatartás pipáját, és ments. A napi kivétel az egész vállalkozás nyitvatartását változtatja meg.

## Kieső óra vagy szabadság

A **Kieső idő vagy szabadság** részben add meg az első és utolsó napot, a kezdést és befejezést, valamint az érintett munkatársat. Az egész vállalkozást is választhatod. Többnapos tartománynál az idősáv minden érintett napon ismétlődik. Szabadsághoz pipáld be a **Teljes napok / szabadság** mezőt. A megnevezés csak a szolgáltatói felületen látszik.

A **Kieső idő hozzáadása** gomb külön menti ezt a beállítást. A listában az X-szel szüntethető meg egy korábban rögzített kieső idő. Munkatársra vonatkozó kiesést a naptár megfelelő munkatársszűrőjével nézhetsz meg; a szerver akkor is ellenőrzi, ha a naptárban minden munkatárs látszik.

## Ismétlődő ebédszünet

Kapcsold be az ebédszünetet, add meg a kezdést és befejezést, jelöld ki a hét napjait és az érintett munkatársat vagy az egész vállalkozást. Kattints az **Ebédszünet mentése** gombra. A pipa kikapcsolása és mentés megszünteti az ismétlődő szünetet. Egy további szünet külön kieső idősávként adható meg.

## Mi történik a foglalásokkal?

Az új online és kézi foglalás, valamint az időpont módosítása ugyanazt a szerveroldali ellenőrzést használja. A teljes szolgáltatási időnek bele kell férnie a napi nyitvatartásba, és nem lóghat szünetbe vagy szabadságba. A közvetlen API-kérés sem kerüli meg ezt. A szakaszos fodrászati szolgáltatások egymással való ütközésének korábbi szabályai megmaradnak.

A beállítás nem töröl vagy mond le meglévő foglalást. Mentéskor a rendszer jelzi az új időbeosztással ütköző jövőbeli, aktív foglalásokat, és megnyitható részleteket kínál. Ezek áthelyezését a vendéggel külön kell egyeztetned. A korábbi foglalások importját a mai szünetek nem érvénytelenítik utólag.

A beállítások vállalkozásonként külön tárolódnak, kizárólag a saját szolgáltatói belépéssel módosíthatók. Nem kerülnek ki a nyilvános adatlapra a privát megnevezések. Új `.env`-változó, külső szolgáltatás vagy migráció nem szükséges; a meglévő szalonadatokat egészítjük ki, adatvesztés nélkül.
