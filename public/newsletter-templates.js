/* Text-first templates: no tracking images, scripts, remote fonts or embedded forms. */
function newsletterTemplateTools(form,editor,platform){
 const name=platform?'':(state.settings?.name||''),signature=name?`<p>Szeretettel várunk!<br>${esc(name)}</p>`:'<p>Szeretettel várunk!</p>';
 const templates=[
  ['free','Szabad időpontok','Felszabadult néhány időpontunk','<h2>Találkozzunk ezen a héten!</h2><p>Kedves Vendégünk!</p><p>Felszabadult néhány időpontunk. Ha régóta tervezed a következő látogatást, most érdemes körülnézned a naptárban.</p><p>Írd ide az elérhető napokat és időpontokat.</p>'],
  ['offer','Akció vagy kupon','Egy ajánlat a következő látogatásodhoz','<h2>Egy kis kedvesség neked</h2><p>Kedves Vendégünk!</p><p>Írd ide az ajánlatot: melyik szolgáltatásra és milyen kedvezménnyel vehető igénybe.</p><p><strong>Kuponkód:</strong> írd ide a kódot<br><strong>Érvényes:</strong> írd ide az időszakot</p><p>Írd ide a beváltás feltételeit.</p>'],
  ['new','Új szolgáltatás','Újdonság nálunk','<h2>Ismerd meg új szolgáltatásunkat!</h2><p>Kedves Vendégünk!</p><p>Írd ide a szolgáltatás nevét, és néhány mondatban mutasd be, kinek és miben segít.</p><p><strong>Időtartam:</strong> írd ide<br><strong>Ár:</strong> írd ide</p>'],
  ['notice','Fontos tájékoztatás','Fontos tudnivaló a következő látogatásodhoz','<h2>Fontos tudnivaló</h2><p>Kedves Vendégünk!</p><p>Írd ide a változást, például a szabadságot vagy a nyitvatartás módosítását.</p><p><strong>Érintett időszak:</strong> írd ide a dátumokat</p><p>A már meglévő foglalásokról és az elérhetőségekről itt adhatsz további tájékoztatást.</p>'],
  ['general','Általános hírlevél','Híreink és újdonságaink','<h2>Híreink és újdonságaink</h2><p>Kedves Vendégünk!</p><p>Írd ide a legfontosabb hírt, röviden és személyesen.</p><h3>Amit még érdemes tudnod</h3><p>Itt oszthatsz meg további részleteket.</p>']
 ];
 const panel=document.createElement('section');panel.className='newsletter-template-tools';
 panel.innerHTML=`<h3>Indulj egy sablonból</h3><p>A mintaszövegeket küldés előtt írd át. A sablonváltás lecseréli a tárgyat és a levél tartalmát.</p><div class="newsletter-template-choices">${templates.map(([id,title])=>`<button type="button" data-newsletter-template="${id}">${title}</button>`).join('')}</div><label>Foglalási oldal hivatkozása (nem kötelező)<input type="url" id="newsletter-booking-link" placeholder="https://…"></label><button type="button" id="newsletter-add-link">Foglalási hivatkozás hozzáadása</button><p id="newsletter-template-feedback" role="status"></p>`;
 form.prepend(panel);
 panel.querySelectorAll('[data-newsletter-template]').forEach(button=>button.onclick=()=>{
  if((form.elements.subject.value.trim()||editor.value().replace(/<[^>]*>/g,'').trim())&&!window.confirm('Lecseréled a jelenlegi tárgyat és levéltartalmat a kiválasztott sablonra?'))return;
  const [,title,subject,html]=templates.find(t=>t[0]===button.dataset.newsletterTemplate);form.elements.subject.value=subject;editor.load(html+signature);
  panel.querySelectorAll('[data-newsletter-template]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));panel.querySelector('[role=status]').textContent=title+' betöltve. Írd át a mintaszövegeket a saját adataiddal.';
 });
 panel.querySelector('#newsletter-add-link').onclick=()=>{
  const input=panel.querySelector('input');let value=input.value.trim();if(value&&!/^https?:\/\//i.test(value))value='https://'+value;
  let url;try{url=new URL(value);if(!['http:','https:'].includes(url.protocol)||url.username||url.password)throw Error();}catch{input.setCustomValidity('Érvényes webcímet adj meg.');input.reportValidity();return;}
  input.setCustomValidity('');input.value=url.href;editor.load(editor.value()+`<p><a href="${esc(url.href)}">Időpontot foglalok →</a></p>`);panel.querySelector('[role=status]').textContent='A foglalási hivatkozást hozzáadtuk a levél végéhez.';
 };panel.querySelector('input').oninput=e=>e.target.setCustomValidity('');
 const preview=document.createElement('details');preview.className='newsletter-template-preview';preview.innerHTML='<summary>Levél előnézete</summary><p>A végleges levél a feladó adatait és a leiratkozási hivatkozást is tartalmazza.</p><iframe title="Hírlevél előnézete" sandbox="" style="width:100%;height:460px;border:1px solid #dbe1ef;background:white"></iframe>';
 form.append(preview);
 const refresh=()=>{if(!preview.open)return;preview.querySelector('iframe').srcdoc=`<!doctype html><html lang="hu"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><body style="margin:0;padding:20px;background:#f3f5fa;font:16px/1.7 Arial;color:#334155"><div style="max-width:552px;margin:auto;background:white;border-top:4px solid #244dc8;padding:24px;overflow-wrap:anywhere"><p style="font-weight:bold;color:#172554">${esc(name)}</p>${cleanRich(editor.value())}</div></body></html>`;};preview.ontoggle=refresh;form.addEventListener('input',refresh);panel.addEventListener('click',()=>queueMicrotask(refresh));
}

function newsletterWatchStatus(platform,data,campaign,form,endpoint){
 if(!['preparing','processing','scheduled'].includes(campaign.status))return;
 const message=document.createElement('p');message.setAttribute('role','status');message.className='notice';form.append(message);
 const started=Date.now();let failures=0;
 const tick=async()=>{
  if(!form.isConnected)return;
  const scheduled=campaign.scheduled_at&&new Date(campaign.scheduled_at)>new Date();
  message.textContent=campaign.status==='scheduled'?(scheduled?'A hírlevél időzítve van. Az állapot automatikusan frissül.':'Küldésre vár. Az állapot automatikusan frissül.'):'Címzettek előkészítése… Nem kell másik oldalra lépned.';
  if(Date.now()-started>90000&&!scheduled)message.textContent='A háttérfeldolgozás a szokásosnál tovább tart. A szerver ütemezőjének percenként futnia kell. Az állapotot továbbra is automatikusan ellenőrizzük.';
  try{const next=await api(endpoint+'/campaigns');if(!form.isConnected)return;const found=next.campaigns.find(c=>c.id===campaign.id);if(!found){message.textContent='A hírlevél már nem található a listában.';return;}if(found.status!==campaign.status){marketingEditor(platform,next,found);return;}failures=0;}catch{if(!form.isConnected)return;failures++;message.textContent='Az állapotot most nem sikerült lekérdezni. Hamarosan újrapróbáljuk; ne indítsd el újra a küldést.';}
  if(form.isConnected)setTimeout(tick,failures?10000:3000);
 };setTimeout(tick,1000);
}
