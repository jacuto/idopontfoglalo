// All extensions are registered before the first request renders a page.
const operationQuery=new URLSearchParams(location.search);
if(operationQuery.has('calendarSettings')){view='settings';settingsTab='integrations';}
if(operationQuery.has('profilePhotos'))view='profile';
if(operationQuery.has('staffPhotos'))view='staff';
if(operationQuery.has('photoUpdated'))setTimeout(()=>toast('Profilkép átvéve.'),500);
if(operationQuery.has('photoError'))setTimeout(()=>toast('A profilkép nem volt átvehető. Tölts fel saját képet.'),500);
if(operationQuery.has('calendarError'))setTimeout(()=>toast('A Google Naptár összekapcsolása nem sikerült. Próbáld újra.'),500);
if(operationQuery.has('calendarConnected'))setTimeout(()=>toast('Google Naptár kapcsolva. A foglalások szinkronizálása sorba állítva.'),500);
if(location.pathname==='/'&&!operationQuery.has('provider'))portalPage();
else if(['/szolgaltatok','/szolgaltato','/belepes','/arak','/kapcsolat'].includes(location.pathname)) portalPage();
else if(location.pathname==='/uzenetek') guestMessagesPage();
else if(location.pathname==='/foadmin') platformPage();
else if(manageToken) managePage();
else if(location.pathname.replace(/\/+$/,'')==='/fiokom') guestPage();
else refresh().catch(loginPage);
