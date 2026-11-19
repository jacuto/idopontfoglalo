/* Text-first templates: no tracking images, scripts, remote fonts or embedded forms. */
function newsletterTemplateTools(form,editor,platform,data={},campaign={}){
 form.dataset.newsletterTemplate=campaign.template_key||'general';
 const bookingUrl=data.bookingUrl||(!platform?new URL('/foglalas?provider='+encodeURIComponent(providerId()),location.origin).href:'');
 const name=platform?'':(state.settings?.name||''),signature=name?`<p>Szeretettel várunk!<br>${esc(name)}</p>`:'<p>Szeretettel várunk!</p>';
 const templates=[
  ['free','Szabad időpontok','Felszabadult néhány időpontunk','<h2>Találkozzunk ezen a héten!</h2><p>Kedves Vendégünk!</p><p>Felszabadult néhány időpontunk. Ha régóta tervezed a következő látogatást, most érdemes körülnézned a naptárban.</p><p>Írd ide az elérhető napokat és időpontokat.</p>'],
  ['offer','Akció vagy kupon','Egy ajánlat a következő látogatásodhoz','<h2>Egy kis kedvesség neked</h2><p>Kedves Vendégünk!</p><p>Írd ide az ajánlatot: melyik szolgáltatásra és milyen kedvezménnyel vehető igénybe.</p><p><strong>Kuponkód:</strong> írd ide a kódot<br><strong>Érvényes:</strong> írd ide az időszakot</p><p>Írd ide a beváltás feltételeit.</p>'],
  ['new','Új szolgáltatás','Újdonság nálunk','<h2>Ismerd meg új szolgáltatásunkat!</h2><p>Kedves Vendégünk!</p><p>Írd ide a szolgáltatás nevét, és néhány mondatban mutasd be, kinek és miben segít.</p><p><strong>Időtartam:</strong> írd ide<br><strong>Ár:</strong> írd ide</p>'],
  ['notice','Fontos tájékoztatás','Fontos tudnivaló a következő látogatásodhoz','<h2>Fontos tudnivaló</h2><p>Kedves Vendégünk!</p><p>Írd ide a változást, például a szabadságot vagy a nyitvatartás módosítását.</p><p><strong>Érintett időszak:</strong> írd ide a dátumokat</p><p>A már meglévő foglalásokról és az elérhetőségekről itt adhatsz további tájékoztatást.</p>'],
  ['general','Általános hírlevél','Híreink és újdonságaink','<h2>Híreink és újdonságaink</h2><p>Kedves Vendégünk!</p><p>Írd ide a legfontosabb hírt, röviden és személyesen.</p><h3>Amit még érdemes tudnod</h3><p>Itt oszthatsz meg további részleteket.</p>']
 ];
 const colors={free:'#08755d',offer:'#7736a8',new:'#a43e24',notice:'#294b68',general:'#284cc4'};
 const panel=document.createElement('section');panel.className='newsletter-template-tools';
 panel.innerHTML=`<h3>Válassz levéldesignt</h3><p>A színes fejléc, a kiemelések és a foglalásgomb a kiküldött levélben is megjelennek. A mintaszövegeket küldés előtt írd át.</p><div class="newsletter-template-choices">${templates.map(([id,title])=>`<button type="button" aria-pressed="${id===form.dataset.newsletterTemplate}" data-newsletter-template="${id}" style="--newsletter-accent:${colors[id]}"><span class="newsletter-design-swatch" aria-hidden="true"><span></span><i></i><i></i><b></b></span><strong>${title}</strong></button>`).join('')}</div>${bookingUrl?'<p class="newsletter-auto-booking">A foglalásgomb automatikusan a saját foglalási oldaladra vezet.</p><button type="button" id="newsletter-add-link">Foglalásgomb hozzáadása a jelenlegi levélhez</button>':''}<p id="newsletter-template-feedback" role="status"></p>`;
 form.prepend(panel);
 panel.querySelectorAll('[data-newsletter-template]').forEach(button=>button.onclick=()=>{
  if((form.elements.subject.value.trim()||editor.value().replace(/<[^>]*>/g,'').trim())&&!window.confirm('Lecseréled a jelenlegi tárgyat és levéltartalmat a kiválasztott sablonra?'))return;
  const [,title,subject,html]=templates.find(t=>t[0]===button.dataset.newsletterTemplate);form.dataset.newsletterTemplate=button.dataset.newsletterTemplate;form.elements.subject.value=subject;const content=html.replace(/(<p><strong>(?:Kuponkód:|Időtartam:|Érintett időszak:)[\s\S]*?<\/p>)/,'<blockquote>$1</blockquote>');editor.load(content+(bookingUrl?`<p><a href="${esc(bookingUrl)}">Időpontot foglalok →</a></p>`:'')+signature);
  panel.querySelectorAll('[data-newsletter-template]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));panel.querySelector('[role=status]').textContent=title+' betöltve. Írd át a mintaszövegeket a saját adataiddal.';
 });
 const add=panel.querySelector('#newsletter-add-link');if(add)add.onclick=()=>{
  if(editor.value().includes(esc(bookingUrl))){panel.querySelector('[role=status]').textContent='A foglalási hivatkozás már szerepel a levélben.';return;}
  editor.load(editor.value()+`<p><a href="${esc(bookingUrl)}">Időpontot foglalok →</a></p>`);panel.querySelector('[role=status]').textContent='A saját foglalási oldaladra vezető gombot hozzáadtuk a levélhez.';
 };
 newsletterDesignPreview(form,()=>editor.value(),platform,()=>form.dataset.newsletterTemplate);

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

function newsletterDesignPreview(form,content,platform,template){
 const preview=document.createElement('details');preview.open=true;preview.className='newsletter-template-preview';preview.innerHTML='<summary>A kiküldött levél designjának előnézete</summary><p>A feladó adatai és a leiratkozás is automatikusan bekerülnek. A levelezőprogramok megjelenítése kismértékben eltérhet.</p><p class="newsletter-preview-status" role="status"></p><iframe title="Hírlevél előnézete" sandbox="" style="width:100%;height:650px;border:1px solid #dbe1ef;background:white"></iframe>';
 form.append(preview);let ticket=0,timer;
 const refresh=()=>{clearTimeout(timer);const cycle=++ticket;if(!preview.open)return;timer=setTimeout(async()=>{
  const status=preview.querySelector('[role=status]');status.textContent='Előnézet frissítése…';
  try{const result=await api((platform?'/api/platform/marketing':'/api/marketing')+'/design-preview','POST',{html:content()||'<p></p>',templateKey:template()||'general'});if(cycle!==ticket||!form.isConnected)return;preview.querySelector('iframe').srcdoc=result.html;status.textContent='';}catch(e){if(cycle===ticket&&form.isConnected)status.textContent='Az előnézetet most nem sikerült betölteni. '+e.message;}
 },350);};
 preview.ontoggle=refresh;form.addEventListener('input',refresh);form.addEventListener('click',()=>queueMicrotask(refresh));refresh();
}
