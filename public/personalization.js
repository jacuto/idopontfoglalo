/* Profession-specific tools and compact profiles. Existing booking snapshots stay intact. */
const settingsBeforePersonalization=settingsPage;
settingsPage=function(){
 settingsBeforePersonalization();const form=$('#settings-form');if(settingsTab!=='basic'||!form)return;
 const s=state.settings,box=document.createElement('fieldset');box.className='profession-options';
 box.innerHTML=`<legend>A munkádhoz szükséges funkciók</legend><p class="hint">Csak azt kapcsold be, amit használsz. A korábbi időpontok és kiadott bérletek megmaradnak.</p>${[['advancedBookingEnabled','Párhuzamos foglalás és csoportos órák'],['servicePhasesEnabled','Szolgáltatási szakaszok: munka, hatóidő, munka'],['couponsEnabled','Kuponok'],['passesEnabled','Bérletek']].map(([key,label])=>`<label class="check"><input type="checkbox" name="${key}" ${s[key]?'checked':''}>${label}</label>`).join('')}<label for="guest-invitation-policy">Új vendég meghívása saját vendégfiók létrehozására</label><select name="guestInvitationPolicy" id="guest-invitation-policy">${[['none','Ne küldjön meghívót'],['email','Csak e-mailben'],['sms','Csak SMS-ben'],['email_first','E-mailben; ha nincs e-mail-cím, SMS-ben'],['sms_first','SMS-ben; ha nincs telefonszám, e-mailben']].map(([value,label])=>`<option value="${value}" ${s.guestInvitationPolicy===value?'selected':''}>${label}</option>`).join('')}</select><p class="hint">Csak az általad újonnan felvitt, még nem regisztrált vendégnek. A meghívó 30 napig érvényes, és a belépés után kapcsolja össze a foglalásait a saját fiókjával.</p>`;
 form.querySelector('.buttons').before(box);
 const mode=form.elements.businessMode,primary=form.elements.primaryStaffId.closest('div'),update=()=>primary.hidden=mode.value!=='solo';mode.addEventListener('change',update);update();
 const qr=document.createElement('section');qr.className='notice';qr.innerHTML='<strong>A foglalóoldalad QR-kódja</strong><p>Nyomtasd ki, vagy tedd ki a szalonban: a vendég közvetlenül nálad foglalhat.</p><button type="button" onclick="bookingQrDialog()">QR-kód letöltése / nyomtatása</button>';form.querySelector('.buttons').before(qr);
};
const serviceBeforePersonalization=serviceForm;
serviceForm=function(id){
 serviceBeforePersonalization(id);const form=$('#service-form'),s=state.settings,mode=form.elements.bookingMode,phases=form.elements.phases;
 if(!s.advancedBookingEnabled)mode.closest('fieldset').hidden=true;
 const update=()=>{const hidden=!s.servicePhasesEnabled||mode.value==='group';phases.hidden=hidden;phases.previousElementSibling.hidden=hidden;};mode.addEventListener('change',update);update();
 if(s.businessMode==='solo'){
  const checks=[...form.querySelectorAll('input[name=staff]')],label=checks[0]?.closest('label')?.previousElementSibling;
  if(label?.tagName==='LABEL')label.remove();checks.forEach(input=>input.closest('label').remove());
  const field=document.createElement('div');field.className='notice primary-professional';field.innerHTML=`<small>Elsődleges szakember</small><strong>${esc(state.staff.find(p=>p.id===s.primaryStaffId)?.name||'')}</strong><input type="hidden" name="staff" value="${esc(s.primaryStaffId)}">`;form.querySelector('#form-error').before(field);
 }
};
const calendarBeforePersonalization=calendarPage;
calendarPage=function(){calendarBeforePersonalization();if(!state.settings.advancedBookingEnabled)document.querySelectorAll('[onclick="groupSessionsDialog()"] ').forEach(b=>b.hidden=true);};
const groupsBeforePersonalization=groupSessionsDialog;
groupSessionsDialog=async function(){await groupsBeforePersonalization();if(!state.settings.advancedBookingEnabled)document.querySelector('#modal [onclick="groupSessionForm()"]')?.remove();};
const offersBeforePersonalization=offersPage;
offersPage=function(){offersBeforePersonalization();const s=state.settings;for(const [key,action]of[['couponsEnabled','couponForm()'],['passesEnabled','passForm()']])if(!s[key])document.querySelector(`#content [onclick="${action}"]`)?.remove();if(!s.couponsEnabled&&!s.passesEnabled)$('#content').insertAdjacentHTML('afterbegin','<p class="notice">A kuponok és az új bérletek létrehozása ki van kapcsolva az Alapbeállításoknál. A korábban kiadottak továbbra is kezelhetők.</p>');};
function domesticPhone(value){let d=String(value).replace(/\D/g,'');if(d.startsWith('0036'))d='06'+d.slice(4);else if(d.startsWith('36'))d='06'+d.slice(2);return d;}
const contactBeforePersonalization=bindContactFields;
bindContactFields=function(root=document){
 if(!root)return;contactBeforePersonalization(root);
 root.querySelectorAll('input[name=phone]:not([readonly])').forEach(input=>{
  if(input.dataset.digitsOnly)return;input.dataset.digitsOnly='1';input.inputMode='numeric';input.pattern='[0-9]*';input.placeholder='06301234567';
  const clean=()=>{input.value=domesticPhone(input.value);input.setCustomValidity('');};input.addEventListener('input',clean);
  input.addEventListener('paste',event=>{event.preventDefault();const text=event.clipboardData?.getData('text')||'';input.setRangeText(text,input.selectionStart,input.selectionEnd,'end');clean();input.dispatchEvent(new Event('input',{bubbles:true}));});
  input.addEventListener('blur',()=>{input.value=domesticPhone(phoneNormalized(input.value));input.setCustomValidity(!input.value||/^06(?:1\d{7}|(?:20|30|31|50|70)\d{7}|(?!(?:20|30|31|50|70))[2-9]\d{7})$/.test(input.value)?'':'Érvénytelen telefonszám. Példa: 06301234567.');});
 });
 root.querySelectorAll('input[name=email]:not([readonly])').forEach(input=>{if(input.dataset.emailChecked)return;input.dataset.emailChecked='1';input.type='email';input.addEventListener('input',()=>input.setCustomValidity(''));input.addEventListener('blur',()=>{input.value=input.value.trim();input.setCustomValidity(input.validity.typeMismatch?'Érvénytelen e-mail-cím. Példa: nev@pelda.hu.':'');});});
};
const bookingBeforePersonalization=bookingForm;
bookingForm=function(dt,staff){bookingBeforePersonalization(dt,staff);const form=$('#booking-form');if(!form||isPublic)return;
 for(const selector of ['#admin-booking-date','#admin-booking-time','[name=name]']){
  const input=form.querySelector(selector),label=input?.previousElementSibling;if(!input||label?.tagName!=='LABEL')continue;
  if(!input.id)input.id='appointment-guest-name';label.htmlFor=input.id;const row=document.createElement('div');row.className='compact-field';label.before(row);row.append(label,input);
 }
 bindContactFields(form);
};
function bookingQrDialog(){
 const url=location.origin+'/foglalas?provider='+encodeURIComponent(providerId()),qr=qrcode(0,'M');qr.addData(url);qr.make();const svg=qr.createSvgTag({cellSize:8,margin:32,scalable:true});
 showModal(`<h2>Foglalóoldalad QR-kódja</h2><div class="booking-qr">${svg}</div><p class="qr-url">${esc(url)}</p><div class="buttons"><button id="qr-svg">SVG letöltése</button><button id="qr-png" class="primary">PNG letöltése</button><button id="qr-print">Nyomtatás</button><button onclick="$('#modal').close()">Bezárás</button></div>`);
 const save=(blob,name)=>{const link=document.createElement('a'),object=URL.createObjectURL(blob);link.href=object;link.download=name;link.click();setTimeout(()=>URL.revokeObjectURL(object),1000);};
 $('#qr-svg').onclick=()=>save(new Blob([svg],{type:'image/svg+xml'}),'foglalas-qr.svg');
 $('#qr-png').onclick=()=>{const canvas=document.createElement('canvas'),n=qr.getModuleCount(),cell=12;canvas.width=canvas.height=(n+8)*cell;const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.fillStyle='#000';for(let y=0;y<n;y++)for(let x=0;x<n;x++)if(qr.isDark(y,x))ctx.fillRect((x+4)*cell,(y+4)*cell,cell,cell);canvas.toBlob(blob=>{if(blob)save(blob,'foglalas-qr.png');});};
 $('#qr-print').onclick=()=>{const popup=window.open('','_blank','width=700,height=800');if(!popup){toast('A nyomtatáshoz engedélyezd a felugró ablakot.');return;}popup.document.write(`<!doctype html><html lang="hu"><meta charset="utf-8"><title>Foglalás QR-kód</title><style>body{text-align:center;font:18px sans-serif;padding:30px}svg{width:300px;height:300px}p{overflow-wrap:anywhere}</style><h1>${esc(state.settings.name)}</h1><h2>Foglalj időpontot!</h2>${svg}<p>${esc(url)}</p></html>`);popup.document.close();popup.focus();popup.print();};
}
const invoiceBeforePersonalization=invoiceForm;
invoiceForm=async function(id){invoiceBeforePersonalization(id);const form=$('#invoice-form'),p=state.payments.find(p=>p.id===id),b=state.bookings.find(b=>b.id===p?.bookingId),client=state.clients.find(c=>c.id===b?.clientId);if(!client?.guestId||p.buyer)return;try{const billing=await api('/api/clients/'+encodeURIComponent(client.id)+'/billing');if(!form.isConnected)return;for(const [key,value]of Object.entries(billing)){const field=form.elements.namedItem(key);if(field&&field.value===field.defaultValue)field.value=value||'';}}catch{}};
const clientBeforePersonalization=clientForm;clientForm=function(id){clientBeforePersonalization(id);const c=state.clients.find(c=>c.id===id);if(c?.nameManaged)$('#client-form [name=name]').readOnly=true;};
let inviteChecked=false;
const guestBeforePersonalization=guestPage;
guestPage=async function(){
 await guestBeforePersonalization();const avatarForm=$('#avatar-form'),contactForm=$('#guest-contact-form');if(!avatarForm||!contactForm)return;
 if(!inviteChecked){inviteChecked=true;try{const claim=await api('/api/guest/invitations/claim','POST',{});if(claim.claimed){await guestPage();toast('A foglalásaidat a saját fiókodhoz kapcsoltuk.');return;}}catch(e){toast(e.message);}}
 const g=await api('/api/guest'),root=$('.public');root.classList.add('guest-account');
 // Keep upload and calendar handlers from the existing account workflow.
 const oldAvatarSection=avatarForm.parentElement,oldContactSection=contactForm.parentElement;
 const top=document.createElement('div');top.className='guest-topbar';const brand=root.querySelector('.brand'),logout=root.querySelector('button[onclick="guestLogout()"]');if(brand)top.append(brand);if(logout)top.append(logout);
 root.querySelector(':scope > h1')?.remove();root.querySelectorAll(':scope > p').forEach(p=>{if(p.textContent.startsWith('Saját foglalásaid')||p.textContent.startsWith('Szolgáltatók, akikhez'))p.remove();});
 const profile=document.createElement('section');profile.className='card item guest-profile';profile.innerHTML=`<div class="guest-profile-heading"><button type="button" class="avatar-change" aria-label="Profilkép cseréje">${g.avatar?`<img class="avatar large-avatar" src="${esc(providerLink(g.avatar))}" alt="Saját profilkép">`:`<span class="avatar large-avatar">${esc(initials(g.name))}</span>`}<span class="avatar-change-overlay">Kép cseréje</span></button><div><small>Saját vendégfiókom</small><h1>${esc(g.name)}</h1></div></div>`;
 avatarForm.hidden=true;profile.append(avatarForm);const avatarError=avatarForm.querySelector('#avatar-error');if(avatarError)profile.append(avatarError);profile.querySelector('.avatar-change').onclick=()=>avatarForm.elements.image.click();avatarForm.elements.image.addEventListener('change',()=>{if(avatarForm.elements.image.files.length)avatarForm.requestSubmit();});
 contactForm.insertAdjacentHTML('afterbegin',`<label for="guest-profile-name">Nevem</label><input id="guest-profile-name" name="name" maxlength="150" required value="${esc(g.name)}">`);contactForm.querySelector('button.primary').textContent='Profil mentése';const contactHint=contactForm.querySelector('.hint');if(contactHint){const emailLabel=contactForm.elements.email.previousElementSibling;emailLabel.insertAdjacentHTML('beforeend',' '+infoTip(contactHint.textContent));contactHint.remove();}profile.append(contactForm);oldAvatarSection.remove();oldContactSection.remove();
 contactForm.onsubmit=async e=>{e.preventDefault();try{await api('/api/guest/profile','PATCH',Object.fromEntries(new FormData(contactForm)));await guestPage();toast('Profil mentve.');}catch(e){$('#contact-error').textContent=e.message;}};
 const providers=document.createElement('section');providers.className='card item linked-providers';providers.innerHTML=`<h2>Szolgáltatóim</h2>${g.providers?.length?g.providers.map(p=>`<a class="linked-provider" href="/foglalas?provider=${Number(p.id)}"><strong>${esc(p.name)}</strong><span>Időpontfoglalás →</span></a>`).join(''):'<p>A foglalásaidhoz kapcsolódó szolgáltatók itt jelennek majd meg.</p>'}`;
 const billing=document.createElement('details');billing.className='card item guest-billing';billing.innerHTML='<summary>Számlázási adataim</summary><p>A szolgáltató a hozzád kapcsolt foglalás számlázásakor használhatja ezeket. Az elérhetőségeidtől külön mentjük.</p><form id="guest-billing-form"></form>';
 root.prepend(top,profile,providers,billing);bindContactFields(profile);
 try{const data=await api('/api/guest/billing'),form=$('#guest-billing-form');form.innerHTML=`<div class="form-grid">${[['name','Számlázási név'],['email','Számlázási e-mail'],['country','Országkód'],['zip','Irányítószám'],['city','Település'],['address','Utca, házszám'],['taxNumber','Adószám (ha van)']].map(([key,label])=>`<div><label for="billing-${key}">${label}</label><input id="billing-${key}" name="${key}" ${key==='email'?'type="email"':''} maxlength="${key==='country'?2:200}" value="${esc(data[key]??(key==='country'?'HU':''))}"></div>`).join('')}</div><p id="billing-error" class="error"></p><div class="buttons"><button type="button" id="clear-billing">Adatok törlése</button><button class="primary">Számlázási adatok mentése</button></div>`;
  const save=async clear=>{try{const body=clear?{}:Object.fromEntries(new FormData(form));await api('/api/guest/billing','PUT',body);await guestPage();toast(clear?'Számlázási adatok törölve.':'Számlázási adatok mentve.');}catch(e){$('#billing-error').textContent=e.message;}};form.onsubmit=e=>{e.preventDefault();save(false);};$('#clear-billing').onclick=()=>save(true);
 }catch(e){billing.insertAdjacentHTML('beforeend',`<p class="error">${esc(e.message)}</p>`);}
};
