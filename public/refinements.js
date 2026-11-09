// Daily-work refinements. All handlers register before the first application render.
function offerFields() {
 const now=new Date(), coupons=state.hasCoupons ?? (state.coupons||[]).some(c=>c.active&&new Date(c.startsAt)<=now&&new Date(c.endsAt)>=now);
 const passes=state.hasPasses ?? ((state.passes||[]).some(p=>p.active)||(state.clientPasses||[]).some(p=>p.remaining>0&&(!p.expiresAt||new Date(p.expiresAt)>now)));
 if(!coupons&&!passes)return '';
 return `<details id="booking-offers"><summary>Kedvezmény vagy bérlet</summary>${coupons?'<label>Kuponkód</label><input name="coupon"><button type="button" onclick="previewPrice()">Ár ellenőrzése</button>':''}${passes?'<label>Bérlet</label><select name="passId" onchange="previewPrice()"><option value="">Nem használok bérletet</option></select><p class="hint">Saját bérlethez lépj be, vagy szolgáltatóként válassz vendéget. Lemondáskor a lefoglalt alkalom visszajár.</p>':''}<p id="price-preview" aria-live="polite"></p></details>`;
}
function serviceSelectionChanged(){if(isPublic&&$('#booking-date')){if(!$('#booking-date').value)$('#booking-date').value=datekey(new Date());++slotRequest;}updateStaff();}
function bookingDaySelected(){const date=$('#booking-date');if(date)date.dataset.userChosen='true';loadSlots();}
const dialog=$('#modal');
dialog.addEventListener('click',e=>{const r=dialog.getBoundingClientRect();if(e.target===dialog&&(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom))dialog.close();});
const phoneLink=phone=>phone?`<a class="call-link" href="tel:${esc(phoneHref(phone))}" aria-label="Hívás: ${esc(phoneFormatted(phone))}">☎ ${esc(phoneFormatted(phone))}</a>`:'<span class="muted">Nincs telefonszám</span>';
function phoneAction(phone,label) {return phone?`<a class="call-action" href="tel:${esc(String(phone).replace(/[^+0-9]/g,''))}" aria-label="Hívás: ${esc(phone)}">☎ ${esc(label)}</a>`:'<span class="muted">—</span>';}
function bookingContact(b){return state.clients.find(c=>c.id===b.clientId)||b;}
function bookingGuestLink(b){const c=state.clients.find(c=>c.id===b.clientId);return c?`<a href="#vendeg-${c.id}" onclick="event.preventDefault();clientDetail('${c.id}')">${esc(c.name)}</a>`:`<a href="#foglalas-${b.id}" onclick="event.preventDefault();bookingDetails('${b.id}')">${esc(b.name)}</a>`;}
function pendingBookings(){const list=state.bookings.filter(b=>b.status==='pending').sort((a,b)=>a.start.localeCompare(b.start));showModal(`<h2>Jóváhagyásra váró foglalások (${list.length})</h2>${list.map(b=>`<article class="notice"><h3>${bookingGuestLink(b)}</h3><p>${new Date(b.start).toLocaleString(window.raeroLocale?.()||'hu-HU')} · ${esc(serviceNames(b))}</p><p>${phoneLink(bookingContact(b).phone)}</p><div class="buttons"><button onclick="bookingDetails('${b.id}')">Részletek / áthelyezés</button><button onclick="setStatus('${b.id}','rejected')">Elutasítás</button><button class="primary" onclick="setStatus('${b.id}','confirmed')">Elfogadás</button></div>${new Date(b.start)<new Date()?'<p>Ez az időpont elmúlt. Előbb helyezd át, vagy utasítsd el.</p>':''}</article>`).join('')||'<p>Nincs jóváhagyásra váró foglalás.</p>'}<button onclick="$('#modal').close()">Bezárás</button>`);}
function removeClientForm(id){const c=state.clients.find(c=>c.id===id);if(!c)return;showModal(`<h2>Vendég eltávolítása a saját listából</h2><p>${esc(c.name)}</p><p>A korábbi foglalások és befizetések megmaradnak. A vendég fiókja és más szolgáltatóknál tárolt adatai nem törlődnek.</p><p id="form-error" class="error"></p><div class="buttons"><button onclick="$('#modal').close()">Mégse</button><button class="danger" onclick="removeClient('${id}')">Eltávolítás</button></div>`);}
async function removeClient(id){try{await api('/api/clients/'+id,'DELETE');$('#modal').close();await refresh();toast('A vendéget eltávolítottuk a saját listádból.');}catch(e){$('#form-error').textContent=e.message;}}
function bookingMessage(id){const b=state.bookings.find(b=>b.id===id);if(!b)return;const c=state.clients.find(c=>c.id===b.clientId);composeClientMessage(c||b);}
function clientMessage(id){const c=state.clients.find(c=>c.id===id);if(c)composeClientMessage(c);}
function composeClientMessage(c){
 if(!c.guestId){showModal(`<h2>Üzenet ${esc(c.name)} részére</h2><p>Ehhez a vendéghez még nincs kapcsolt vendégfiók, ezért belső üzenetet nem tud fogadni.</p>${c.email?`<a class="primary mail-link" href="mailto:${esc(c.email)}">Email írása a levelezőprogramban</a>`:'<p>Nincs emailcím megadva. A vendég adatainál rögzíthetsz elérhetőséget.</p>'}<p>${phoneLink(c.phone)}</p><button onclick="$('#modal').close()">Bezárás</button>`);return;}
 showModal(`<h2>Üzenet ${esc(c.name)} részére</h2><form id="quick-message"><label>Üzenet</label><textarea name="text" required maxlength="5000" autofocus></textarea><p id="form-error" class="error"></p><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Üzenet küldése</button></div></form>`);
 $('#quick-message').onsubmit=async e=>{e.preventDefault();try{await api('/api/messages','POST',{guestId:Number(c.guestId),text:new FormData(e.target).get('text')});$('#modal').close();toast('Belső üzenet elmentve. Az emailértesítés külön kerül feldolgozásra.');}catch(err){$('#form-error').textContent=err.message;}};
}
const detailBeforeActions=clientDetail;
clientDetail=async function(id){await detailBeforeActions(id);const c=state.clients.find(c=>c.id===id);if(c&&dialog.open){const div=document.createElement('div');div.className='buttons';div.innerHTML=`${phoneLink(c.phone)}<button onclick="clientMessage('${id}')">Üzenet</button><button class="danger" onclick="removeClientForm('${id}')">Eltávolítás a saját listából</button>`;dialog.append(div);}};
const dailySettings=providerSettings;
let settingsTab = 'basic';
settingsPage=function(){
 const tabs=[['basic','Alapvető beállítások'],['offers','Kuponok és bérletek'],['profile','Nyilvános adatlap'],['integrations','Integrációk']];
 if(settingsTab==='offers')offersPage();else if(settingsTab==='profile')profilePage();else {
  dailySettings();if(settingsTab==='basic')newsletterConsent(document.querySelector('.settings-layout > .card'));const cards=document.querySelectorAll('.settings-layout > .card');cards.forEach((card,i)=>card.hidden=settingsTab==='integrations'?i===0:i!==0);
 }
 const nav=document.createElement('div');nav.className='settings-tabs';nav.setAttribute('role','tablist');nav.setAttribute('aria-label','Beállítások részei');
 nav.innerHTML=tabs.map(([id,label])=>`<button role="tab" aria-selected="${settingsTab===id}" class="${settingsTab===id?'selected':''}" data-settings-tab="${id}">${label}</button>`).join('');
 nav.querySelectorAll('button').forEach(button=>button.onclick=()=>{settingsTab=button.dataset.settingsTab;settingsPage();});$('#content').prepend(nav);
 const hint=document.createElement('p');hint.className='hint settings-help';hint.textContent={basic:'Itt állíthatod be a vállalkozást, a szokásos nyitvatartást és az értesítéseket. Kivételes napot, szabadságot vagy ebédszünetet a Naptárban adj meg.',offers:'Kedvezmények és bérletek létrehozása, kezelése.',profile:'Ezeket az adatokat látják a vendégek a foglalóoldalon.',integrations:'Külső kapcsolatok: levelezés, közösségi belépés, fizetés és számlázás. Az üres titkos mező megőrzi a korábbi kulcsot.'}[settingsTab];nav.after(hint);
};
async function socialButtons(container,mode='guest'){
 try{const configured=await api('/api/auth/providers');const el=$(container);if(!el)return;el.innerHTML=['google','facebook','apple'].map(k=>socialButtonMarkup(k,configured[k],mode)).join('')+(!configured.google&&!configured.facebook&&!configured.apple?'<p class="hint">A szolgáltató még nem kapcsolta be a közösségi belépést. Foglalni továbbra is lehet regisztráció nélkül.</p>':'');}catch(e){if($(container))$(container).textContent='A belépési lehetőségek most nem tölthetők be.';}
}
const accountBeforeContacts=guestPage;
guestPage=async function(){await accountBeforeContacts();if(!$('#avatar-form'))return;try{const g=await api('/api/guest');const section=document.createElement('section');section.className='card item';section.innerHTML=`<h2>Saját elérhetőségeim</h2><p>Az itt megadott elérhetőségeket a hozzád kapcsolt szolgáltatók látják. Ők nem írhatják át őket. A korábbi foglalások eredeti adatai megmaradnak.</p><form id="guest-contact-form"><label>Telefonszám</label><input name="phone" type="tel" maxlength="40" value="${esc(g.phone||'')}"><label>Kapcsolattartási email</label><input name="email" type="email" maxlength="200" value="${esc(g.email||'')}"><p class="hint">Ez a kapcsolattartási cím; a Google-/Facebook-belépési azonosítódat nem módosítja.</p><p id="contact-error" class="error"></p><button class="primary">Elérhetőségek mentése</button></form>`;$('.public').append(section);$('#guest-contact-form').onsubmit=async e=>{e.preventDefault();try{await api('/api/guest/profile','PATCH',Object.fromEntries(new FormData(e.target)));toast('Saját elérhetőségek mentve.');}catch(err){$('#contact-error').textContent=err.message;}};}catch{}};
function reportPreset(preset){const today=new Date();if(preset==='previous'){reportFrom=datekey(new Date(today.getFullYear(),today.getMonth()-1,1));reportTo=datekey(new Date(today.getFullYear(),today.getMonth(),0));}else{const start=new Date(today);start.setDate(start.getDate()-29);reportFrom=datekey(start);reportTo=datekey(today);}reportsPage();}
const reportsBeforeTrend=reportsPage;
reportsPage=function(){reportsBeforeTrend();const toolbar=$('#content .toolbar');if(toolbar){for(const [label,key]of[['Előző hónap','previous'],['Elmúlt 30 nap','30days']]){const b=document.createElement('button');b.textContent=label;b.onclick=()=>reportPreset(key);toolbar.append(b);}}
 const totals=new Map(),from=new Date(reportFrom+'T12:00'),to=new Date(reportTo+'T12:00');for(let d=new Date(from);d<=to;d.setDate(d.getDate()+1))totals.set(datekey(d),0);
 for(const p of state.payments||[]){const key=datekey(new Date(p.paidAt));if(totals.has(key))totals.set(key,totals.get(key)+paymentNet(p));}
 const rows=[...totals],max=Math.max(1,...rows.map(([,n])=>n)),width=Math.max(280,($('#content').clientWidth || 800)-48),height=210;
 const points=rows.map(([d,n],i)=>[35+(width-65)*i/Math.max(1,rows.length-1),height-30-(height-55)*n/max,d,n]);
 const panel=document.createElement('section');panel.className='card item';panel.innerHTML=`<h2>Bevételek alakulása naponta</h2><p>${esc(reportFrom)} – ${esc(reportTo)} · A tényleges befizetés napja alapján, visszatérítésekkel csökkentve. A diagram a nulla bevételű napokat is jelzi; a táblázat csak a nem nulla összegeket mutatja.</p><div class="trend-scroll"><svg class="revenue-trend" role="img" aria-label="Napi bevétel vonaldiagram" viewBox="0 0 ${width} ${height}"><line x1="35" y1="${height-30}" x2="${width-20}" y2="${height-30}" stroke="#b2c8c0"/><polyline fill="none" stroke="#14674f" stroke-width="3" points="${points.map(p=>p[0]+','+p[1]).join(' ')}"/>${points.map(([x,y,d,n],i)=>`<circle cx="${x}" cy="${y}" r="4" fill="#14674f"><title>${d}: ${money(n)}</title></circle>${i===0||i===rows.length-1||i%Math.max(1,Math.ceil(rows.length/8))===0?`<text x="${x}" y="${height-8}" text-anchor="middle" font-size="10">${d.slice(5)}</text>`:''}`).join('')}</svg></div><details><summary>Napi összegek táblázata</summary><div class="table-scroll"><table><thead><tr><th>Nap</th><th>Bevétel</th></tr></thead><tbody>${rows.filter(([,n])=>n!==0).map(([d,n])=>`<tr><td>${d}</td><td>${money(n)}</td></tr>`).join('') || '<tr><td colspan="2">Ebben az időszakban nincs bevétel.</td></tr>'}</tbody></table></div></details>`;$('#content').append(panel);
};
const chargeStatus=s=>({trial:'Próbaidő · díjmentes',pending:'Fizetésre vár',overdue:'Lejárt határidő',paid:'Fizetve',blocked:'Naptár zárolva',trialing:'Próbaidő',active:'Rendezett',past_due:'Fizetésre vár',canceled:'Megszűnt',unpaid:'Rendezetlen',incomplete:'Fizetés befejezésére vár'}[s]||s);
const shownDate=d=>d?new Date(d).toLocaleString(window.raeroLocale?.()||'hu-HU'):'—';
function transactionHTML(p){let details={};try{details=JSON.parse(p.details||'{}');}catch{}return `<details class="transaction"><summary>${money(p.amount)} · ${p.method==='card'?'Bankkártya / Stripe':'Átutalás'} · ${shownDate(p.paid_at)} · Fizetve</summary><dl><dt>Tranzakcióazonosító</dt><dd>${p.id}</dd><dt>Szolgáltatóazonosító</dt><dd>${p.provider_id}</dd><dt>Hivatkozás / banki azonosító</dt><dd>${esc(p.reference)}</dd><dt>Időszak kezdete</dt><dd>${shownDate(p.period_start)}</dd><dt>Időszak vége</dt><dd>${shownDate(p.period_end)}</dd><dt>Rögzítés</dt><dd>${shownDate(p.created_at)}</dd>${Object.entries(details).map(([k,v])=>`<dt>${esc({source:'Forrás',approvedBy:'Jóváhagyó',approvedAt:'Jóváhagyás ideje',chargeId:'Havi díjtétel',stripeInvoice:'Stripe számla',stripeSubscription:'Stripe előfizetés',stripeCustomer:'Stripe ügyfél',paymentIntent:'Stripe fizetési azonosító',eventId:'Stripe esemény',currency:'Pénznem',amountPaid:'Fizetett összeg'}[k]||k)}</dt><dd>${esc(v??'—')}</dd>`).join('')}</dl></details>`;}
billingPage=async function(){try{const d=await api('/api/subscription/info'),s=d.subscription,o=d.operator;state.subscription=s;$('#content').innerHTML=`<div class="entity-grid"><section class="card item"><h2>Alapcsomag · 4 000 Ft / hó</h2><p>Fizetendő végösszeg, alanyi adómentesen.</p><p class="notice">${chargeStatus(s.status||'Nincs előfizetés')}</p>${s.calendarBlocked?'<p class="error">Lejárt a 21 napos fizetési határidő. A belépés és a levelezés működik, a naptár a rendezésig zárolva van.</p>':''}<p>Próbaidő vége: ${shownDate(s.trialEndsAt)}</p><button class="primary" onclick="startSubscription()">Fizetés bankkártyával / Stripe</button><p>A próbaidő két hónapja 0 Ft-os; erről nem állítunk ki számlát. A fizetős havi díjak határideje az időszak kezdetétől 21 nap.</p></section><section class="card item"><h2>Átutalás</h2>${o.account?`<p>${esc(o.name)}<br>${esc(o.bank)}<br>${esc(o.account)}<br>IBAN: ${esc(o.iban)}<br>SWIFT/BIC: ${esc(o.bic)}</p>`:'<p>Az üzemeltető még nem adta meg a bankadatokat. Írj neki a fenti gombbal.</p>'}<p>Közlemény: <strong>RAERO-${d.providerId}</strong></p><p>A banki beérkezést a főadmin hagyja jóvá. Egy átutalás csak egyszer írható jóvá.</p></section></div><section class="card item"><h2>Havi díjtételek</h2>${chargeRows(s.charges||[])}</section><section class="card item"><h2>Tranzakcióim</h2>${d.payments.map(transactionHTML).join('')||'<p>Még nincs tényleges befizetés. A próbaidő díjmentes tételeit fent látod.</p>'}</section><section class="card item"><h2>Számláim</h2>${d.invoices.map(i=>`<p>${esc(i.number||'Még nincs számlaszám')} · ${esc({issued:'Kiállítva',unknown:'Ellenőrzést igényel',issuing:'Folyamatban'}[i.status]||i.status)} ${i.status==='issued'?`<a href="${providerLink('/api/subscription/invoices/'+i.id+'/pdf')}">PDF letöltése</a>`:''}</p>`).join('')||'<p>A próbaidőről nincs számla. Fizetős befizetés után az üzemeltető állíthat ki számlát.</p>'}</section>`;}catch(e){$('#content').textContent=e.message;}};
function chargeRows(entries,provider){return `<div class="table-scroll"><table><thead><tr><th>Időszak</th><th>Összeg</th><th>Állapot</th><th>Fizetési határidő</th><th></th></tr></thead><tbody>${entries.map(e=>`<tr><td>${shownDate(e.from)} – ${shownDate(e.to)}</td><td>${money(e.amount)}</td><td>${chargeStatus(e.status)}</td><td>${e.kind==='trial'?'Díjmentes · nincs számla':shownDate(e.dueAt)}</td><td>${provider&&e.kind==='monthly'&&['pending','overdue'].includes(e.status)?`<button onclick="approveMonthlyCharge(${provider},'${e.id}')">Átutalás beérkezett · Fizetettnek jelölés</button>`:e.paymentId?'Tranzakció #'+e.paymentId:''}</td></tr>`).join('')||'<tr><td colspan="5">A havi díjtételek a próbaidő beállítása után jelennek meg.</td></tr>'}</tbody></table></div>`;}
function approveMonthlyCharge(provider,charge){showModal(`<h2>Átutalás jóváhagyása · 4 000 Ft</h2><p>Csak a bankban ténylegesen megérkezett összeget jelöld fizetettnek. A banki tranzakció egyedi azonosítója megakadályozza a dupla jóváírást.</p><form id="approve-charge"><label>Banki tranzakció azonosítója</label><input name="reference" required maxlength="150"><label>Beérkezés dátuma</label><input name="paidAt" type="date" required value="${datekey(new Date())}"><p id="form-error" class="error"></p><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Befizetés jóváhagyása</button></div></form>`);$('#approve-charge').onsubmit=async e=>{e.preventDefault();try{const result=await api('/api/platform/payments','POST',{...Object.fromEntries(new FormData(e.target)),providerId:provider,chargeId:charge});dialog.close();await platformPage();toast(result.alreadyCredited?'Ezt a banki tranzakciót már korábban jóváírtuk.':'Befizetés rögzítve. A hozzáférés állapota frissült.');}catch(err){$('#form-error').textContent=err.message;}};}
const platformBeforeDues=platformPage;
platformPage=async function(){await platformBeforeDues();if(!$('#provider-create'))return;try{const providers=await api('/api/platform/providers'),payments=await api('/api/platform/payments');const section=document.createElement('section');section.className='card item';section.id='platform-monthly-charges';section.innerHTML=`<h2>Havi díjak és fizetési határidők</h2>${providers.map(p=>`<details><summary>${esc(p.name)} · ${chargeStatus(p.subscription.status||'Nincs előfizetés')}</summary>${chargeRows(p.subscription.charges||[],p.id)}</details>`).join('')}<h2>Tranzakciók részletes adatai</h2>${payments.map(transactionHTML).join('')||'<p>Még nincs tényleges befizetés.</p>'}`;$('.public').append(section);}catch(e){toast(e.message);}};
const calendarBeforeLock=calendarPage;
calendarPage=function(){if(state.subscription?.calendarBlocked){$('#content').innerHTML='<section class="card item"><h2>A naptár zárolva van</h2><p>Lejárt a 21 napos fizetési határidő. Az adatok megmaradtak. A rendezéshez nyisd meg az Előfizetés menüt.</p><button class="primary" onclick="view=\'billing\';render()">Előfizetés és befizetések</button></section>';return;}calendarBeforeLock();};
const formBeforeLock=bookingForm;
bookingForm=function(dt){if(state.subscription?.calendarBlocked){view='billing';render();toast('A naptár használatához előbb rendezd a lejárt havi díjat.');return;}formBeforeLock(dt);};
const renderBeforeSampleBanner=render;
render=function(){renderBeforeSampleBanner();if(!isPublic&&state.sampleData&&$('.intro')){const banner=document.createElement('p');banner.className='notice sample-banner';banner.setAttribute('role','status');banner.textContent='Tesztpéldány · mesterséges mintaadatok, nem valódi befizetések.';$('.intro').before(banner);if(document.querySelector('.timeline'))calendarPage();}};
const publicBeforeGuestDetails = publicPage;
publicPage = function() {
  publicBeforeGuestDetails();
  if (location.hash === '#public-form' || location.hash.startsWith('#szolgaltatas-')) {
    try { const target = document.getElementById(decodeURIComponent(location.hash.slice(1))); if (target) requestAnimationFrame(() => target.scrollIntoView()); } catch {}
  }
  const form = $('#booking-form');
  if (!form) return;
  const login = document.querySelector('#public-form a[href^="/fiokom"]');
  if (login) {
    login.href = '/fiokom?provider=' + encodeURIComponent(providerId()) + '&returnTo=booking';
    login.addEventListener('click', () => rememberBookingDraft(form));
  }
  $('#guest-account-nav')?.addEventListener('click',()=>rememberBookingDraft(form));
  fillOwnBookingDetails(form, login);
};
function rememberBookingDraft(form){const data=new FormData(form);try{sessionStorage.setItem('raero-booking-draft:'+providerId(),JSON.stringify({time:Date.now(),services:data.getAll('services'),staff:data.get('staff'),date:$('#booking-date')?.value,start:data.get('start')}));}catch{}}
async function fillOwnBookingDetails(form, login) {
  let guest;
  try { guest = await api('/api/guest'); } catch { return; }
  if (!form.isConnected) return;
  for (const key of ['name','email','phone']) {
    const field = form.elements.namedItem(key);
    if (field && !field.value) field.value = guest[key] || '';
  }
  const accountNav=$('#guest-account-nav');if(accountNav){accountNav.href=providerLink('/fiokom');accountNav.innerHTML='Saját fiókom →';}
  if (login) { login.textContent = 'Saját fiókom és foglalásaim →'; login.href = providerLink('/fiokom'); }
  const notice = document.createElement('div');
  notice.className = 'notice guest-autofill';
  notice.innerHTML = `<p>Bejelentkezve: <strong>${esc(guest.name)}</strong>. A fiókodban megadott adatokat kitöltöttük.</p><label class="check"><input type="checkbox" name="rememberContact" value="1" checked>Elérhetőségeim megjegyzése sikeres foglalás után <span class="contact-tip" tabindex="0" role="button" onclick="event.preventDefault();event.stopPropagation();this.focus()" aria-label="Tudnivalók az elérhetőségek megjegyzéséről">ⓘ<span class="tip-content">A Google és a Facebook általában nem ad át telefonszámot. Ha most megadod, legközelebb ezt is kitöltjük. Más személynek történő foglalásnál vedd ki a pipát.</span></span></label>`;
  form.querySelector('[name=name]').before(notice);
  const key = 'raero-booking-draft:' + providerId();
  try {
    const draft = JSON.parse(sessionStorage.getItem(key) || 'null');
    sessionStorage.removeItem(key);
    if (draft && Date.now() - draft.time < 20 * 60 * 1000 && Array.isArray(draft.services)) {
      form.querySelectorAll('[name=services]').forEach(field => { field.checked = draft.services.includes(field.value); });
      updateStaff();
      const staff = form.elements.namedItem('staff');
      if ([...staff.options].some(o => o.value === draft.staff)) staff.value = draft.staff;
      if (draft.date && draft.date >= datekey(new Date())) $('#booking-date').value = draft.date;
      await loadSlots();
      const slot = [...form.querySelectorAll('[data-start]')].find(el => el.dataset.start === draft.start);
      if (slot) selectSlot(slot); else restoreSelectedSlot(draft.start);
    }
  } catch {}
}

async function guestMessagesPage() {
  const operator = new URLSearchParams(location.search).has('platformMessages');
  let guest;
  try { guest = await api('/api/guest'); }
  catch { location.replace('/fiokom?provider=' + encodeURIComponent(providerId()) + '&returnTo=' + (operator ? 'operatorMessages' : 'messages')); return; }
  let provider;
  if (!operator) {
    provider = guest.providers.find(p => String(p.id) === String(providerId()));
    if (!provider) { $('#app').innerHTML = '<div class="public card item"><h1>Ez a beszélgetés nem érhető el</h1><p>Csak a saját szolgáltatóiddal beszélgethetsz.</p><a href="/fiokom">Vissza a saját fiókhoz</a></div>'; return; }
  }
  $('#app').innerHTML = `<div class="public"><h1>Üzenetek: ${operator ? 'Ráérő üzemeltetője' : esc(provider.name)}</h1><p><a href="/fiokom">Vissza a saját fiókhoz</a></p>${operator ? '<button onclick="operatorConversation(false)">Beszélgetés megnyitása</button>' : '<div id="conversation"></div>'}</div>`;
  if (operator) await operatorConversation(false); else await openConversation(guest.id, true);
}
