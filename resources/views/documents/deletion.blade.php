@extends('documents.layout')
@section('title','Felhasználói adatok törlése')
@section('description','Így kérheted a Ráérő-fiókod és a közösségi belépésből származó adataid törlését.')
@section('content')
<p class="document-lead">A vendégfiókod, a közösségi belépéshez kapcsolódó adataid vagy más személyes adataid törlését az üzemeltetőtől kérheted. Ez az útmutató Facebook-, Google-, Apple- és e-mailes regisztrációra is vonatkozik.</p>
@include('documents.operator')
<h2>Hogyan kérheted?</h2><ol><li>Írj a fenti elérhetőségre, lehetőleg a fiókodban használt e-mail-címről. A tárgy legyen: <strong>Ráérő – adattörlési kérelem</strong>.</li><li>Add meg a fiókod e-mail-címét és nevét, valamint azt, hogy a teljes fiókot vagy bizonyos adatokat szeretnél töröltetni. Ha közösségi belépést használsz, elég megnevezni a szolgáltatót.</li><li>A jogosultság ellenőrzéséhez csak a szükséges azonosítást kérjük. <strong>Ne küldj jelszót, API-kulcsot vagy igazolványmásolatot előzetes kérés nélkül.</strong></li></ol>
<h2>Mi történik a kérelemmel?</h2><p>A kérelmet indokolatlan késedelem nélkül, főszabály szerint egy hónapon belül megválaszoljuk. Összetett kérelemnél a GDPR szerinti hosszabbításról és annak okáról az első hónapon belül tájékoztatunk. A teljesítésről vagy az esetleges korlátozás indokáról választ kapsz.</p>
<h2>Mely adatok törölhetők?</h2><p>A szükségtelen profiladatok, profilkép, belépési kapcsolatok és más személyes adatok törölhetők vagy anonimizálhatók. A számlázási, jogi igényhez szükséges vagy jogszabály alapján megőrzendő adatokra eltérő szabályok vonatkoznak. A biztonsági másolatok kezelése a megőrzési szabályok szerint történik; azokból a törölt adatokat rendes működésbe nem állítjuk vissza.</p>
<p>A szolgáltató saját foglalási nyilvántartása külön adatkezelés is lehet. A kérés rád vonatkozó részét szükség esetén a szolgáltatóval egyeztetjük. <strong>A fióktörlés nem jelent automatikus időpontlemondást</strong>: a jövőbeli foglalásaidról külön rendelkezz a kezelőhivatkozással vagy a szolgáltatónál.</p>
<h2>Facebook-kapcsolat eltávolítása</h2><p>A Facebook beállításaiban az <strong>Alkalmazások és webhelyek</strong> résznél eltávolíthatod a Ráérő kapcsolatát. Ez a további hozzáférést szünteti meg; a korábban tárolt Ráérő-adatok törléséhez küldd el a fenti kérelmet. Google- vagy Apple-kapcsolat visszavonásánál is ez a különbség.</p>
<p>Részletes tájékoztatás: <a href="/adatvedelem">Adatkezelési tájékoztató</a>. Panasz: <a href="https://www.naih.hu/">Nemzeti Adatvédelmi és Információszabadság Hatóság</a>, illetve bírósági jogorvoslat.</p>
@endsection
