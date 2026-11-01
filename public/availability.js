function currentAvailability() {
  return state.availability || {overrides:[],blocks:[],lunch:{enabled:false,from:'12:00',to:'12:30',weekdays:[1,2,3,4,5],staffId:null}};
}
function calendarHours(date) {
  const day=currentAvailability().overrides.find(d=>d.date===date);
  if(day?.closed)return [null,null];
  return [timeMinutes(day?.openingTime || state.settings.openingTime || '08:00'),timeMinutes(day?.closingTime || state.settings.closingTime || '20:00')];
}
function calendarRanges(date, staff) {
  const availability=currentAvailability(),ranges=[];
  for(const b of availability.blocks)if(date>=b.fromDate&&date<=b.toDate&&(!b.staffId||b.staffId===staff))ranges.push([b.allDay?0:timeMinutes(b.fromTime),b.allDay?1440:timeMinutes(b.toTime),b.label]);
  const lunch=availability.lunch;
  if(lunch.enabled&&(!lunch.staffId||lunch.staffId===staff)&&lunch.weekdays.includes(new Date(date+'T12:00').getDay()||7))ranges.push([timeMinutes(lunch.from),timeMinutes(lunch.to),'Ebédszünet']);
  return ranges;
}
function calendarSlotBlocked(date, minute) {
  const [open,close]=calendarHours(date);
  if(open===null||minute<open||minute>=close)return true;
  const people=filter?[filter]:activePeople().filter(p=>state.settings.businessMode!=='solo'||p.id===state.settings.primaryStaffId).map(p=>p.id);
  return !people.some(person=>!calendarRanges(date,person).some(([from,to])=>minute>=from&&minute<to));
}
function calendarBlockedBands(date, open, close, scale) {
  const [start,end]=calendarHours(date),ranges=calendarRanges(date,filter||null);
  if(start===null)ranges.push([open,close,'Zárva']);else {if(start>open)ranges.push([open,start,'Zárva']);if(end<close)ranges.push([end,close,'Zárva']);}
  return ranges.filter(([a,b])=>b>open&&a<close).map(([a,b,label])=>`<div class="calendar-block" style="top:${(Math.max(open,a)-open)*scale}px;height:${(Math.min(close,b)-Math.max(open,a))*scale}px"><span>${esc(label)}</span></div>`).join('');
}
function availabilityPeople(selected) {
  return `<option value="">Az egész vállalkozás</option>${state.staff.filter(p=>!p.archived||p.id===selected).map(p=>`<option value="${esc(p.id)}" ${p.id===selected?'selected':''}>${esc(p.name)}${p.archived?' (archivált)':''}</option>`).join('')}`;
}
function availabilityDialog(date=datekey(new Date())) {
  const a=currentAvailability(),day=a.overrides.find(d=>d.date===date),lunch=a.lunch;
  showModal(`<h2>Nyitvatartás és szünetek</h2><p>A beállítások az online foglalásra és a kézi felvitelre is érvényesek. A meglévő foglalásokat nem töröljük; ütközésnél jelezzük, melyiket kell egyeztetni.</p><label>Kiválasztott nap</label><input id="availability-date" type="date" required value="${esc(date)}" onchange="if(this.value)availabilityDialog(this.value)">
  <section class="availability-section"><h3>Napi nyitvatartás</h3><form id="day-hours-form"><label class="check"><input name="enabled" type="checkbox" ${day?'checked':''}>Ezen a napon eltérő nyitvatartás</label><label class="check"><input name="closed" type="checkbox" ${day?.closed?'checked':''}>Egész nap zárva</label><div class="form-grid"><div><label>Nyitás</label><input name="openingTime" type="time" required value="${esc(day?.openingTime||state.settings.openingTime)}"></div><div><label>Zárás</label><input name="closingTime" type="time" required value="${esc(day?.closingTime||state.settings.closingTime)}"></div></div><p class="hint">A felső pipa nélkül a szokásos nyitvatartás érvényes. Eltérő nyitásnál korábbi és későbbi órát is megadhatsz.</p><button class="primary">Napi nyitvatartás mentése</button></form></section>
  <section class="availability-section"><h3>Kieső idő vagy szabadság</h3><form id="time-block-form"><label>Megnevezés (csak neked látható)</label><input name="label" required maxlength="100" value="Elfoglaltság"><div class="form-grid"><div><label>Első nap</label><input name="fromDate" type="date" required value="${esc(date)}"></div><div><label>Utolsó nap</label><input name="toDate" type="date" required value="${esc(date)}"></div></div><label class="check"><input name="allDay" type="checkbox">Teljes napok / szabadság</label><div class="form-grid"><div><label>Kezdés</label><input name="fromTime" type="time" required value="12:00"></div><div><label>Befejezés</label><input name="toTime" type="time" required value="13:00"></div></div><label>Kire vonatkozik?</label><select name="staffId">${availabilityPeople(filter)}</select><p class="hint">Több napnál minden megadott napon ugyanezt az idősávot zárjuk ki. Teljes napokkal szabadság is rögzíthető.</p><button class="primary">Kieső idő hozzáadása</button></form><div class="availability-block-list">${a.blocks.map(b=>`<div class="availability-entry"><span><strong>${esc(b.label)}</strong> · ${esc(b.fromDate)} – ${esc(b.toDate)} · ${b.allDay?'Teljes nap':esc(b.fromTime)+'–'+esc(b.toTime)} · ${esc(state.staff.find(p=>p.id===b.staffId)?.name||'Mindenki')}</span><button class="danger" data-delete-block="${esc(b.id)}" aria-label="Kieső idő törlése">×</button></div>`).join('')||'<p class="hint">Még nincs rögzített kieső idő.</p>'}</div></section>
  <section class="availability-section"><h3>Ismétlődő ebédszünet</h3><form id="lunch-form"><label class="check"><input name="enabled" type="checkbox" ${lunch.enabled?'checked':''}>Ebédszünet bekapcsolása</label><div class="form-grid"><div><label>Kezdés</label><input name="from" type="time" required value="${esc(lunch.from)}"></div><div><label>Befejezés</label><input name="to" type="time" required value="${esc(lunch.to)}"></div></div><div class="weekday-checks">${['Hétfő','Kedd','Szerda','Csütörtök','Péntek','Szombat','Vasárnap'].map((label,i)=>`<label class="check"><input type="checkbox" name="weekdays" value="${i+1}" ${lunch.weekdays.includes(i+1)?'checked':''}>${label}</label>`).join('')}</div><label>Kire vonatkozik?</label><select name="staffId">${availabilityPeople(lunch.staffId)}</select><button class="primary">Ebédszünet mentése</button></form></section><div id="availability-result" role="status"></div><div class="buttons"><button onclick="$('#modal').close()">Bezárás</button></div>`);
  $('#day-hours-form').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target),next={...currentAvailability(),overrides:currentAvailability().overrides.filter(d=>d.date!==date)};if(f.has('enabled'))next.overrides.push({date,closed:f.has('closed'),openingTime:f.get('openingTime'),closingTime:f.get('closingTime')});await saveAvailability(next,'Napi nyitvatartás mentve.');};
  $('#time-block-form').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target),b=Object.fromEntries(f);b.id=crypto.randomUUID();b.allDay=f.has('allDay');b.staffId=b.staffId||null;const next={...currentAvailability(),blocks:[...currentAvailability().blocks,b]};if(await saveAvailability(next,'Kieső idő hozzáadva.')){const result=$('#availability-result').innerHTML;availabilityDialog(date);$('#availability-result').innerHTML=result;bindConflicts();}};
  $('#lunch-form').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target),l=Object.fromEntries(f);l.enabled=f.has('enabled');l.weekdays=f.getAll('weekdays').map(Number);l.staffId=l.staffId||null;await saveAvailability({...currentAvailability(),lunch:l},'Ebédszünet mentve.');};
  document.querySelectorAll('[data-delete-block]').forEach(button=>button.onclick=async()=>{if(await saveAvailability({...currentAvailability(),blocks:currentAvailability().blocks.filter(b=>b.id!==button.dataset.deleteBlock)},'Kieső idő törölve.')){const result=$('#availability-result').innerHTML;availabilityDialog(date);$('#availability-result').innerHTML=result;bindConflicts();}});
}
function bindConflicts() {document.querySelectorAll('[data-conflict]').forEach(button=>button.onclick=()=>{$('#modal').close();bookingDetails(button.dataset.conflict);});}
async function saveAvailability(data,message) {
  try {
    const result=await api('/api/availability','PUT',data);state.availability=result.availability;if(view==='calendar')calendarPage();
    const target=$('#availability-result');if(target){target.className=result.conflicts.length?'notice':'success';target.innerHTML=`<p>${esc(message)}</p>${result.conflicts.length?`<p><strong>${result.conflicts.length} meglévő foglalás ütközik az új időbeosztással.</strong> Ezek megmaradtak; egyeztesd a vendégekkel az áthelyezést.</p>${result.conflicts.slice(0,20).map(b=>`<p><button data-conflict="${esc(b.id)}">${new Date(b.start).toLocaleString('hu-HU')} · ${esc(b.name)}</button></p>`).join('')}`:''}`;bindConflicts();}toast(message);return true;
  }catch(err){const target=$('#availability-result');if(target){target.className='error';target.textContent=err.message;target.scrollIntoView({block:'nearest'});}else toast(err.message);return false;}
}
