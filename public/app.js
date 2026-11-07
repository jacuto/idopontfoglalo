let state,
  view = "calendar",
  offset = 0,
  filter = "",
  isPublic = location.pathname.replace(/\/+$/, "") === "/foglalas",
  manageToken = new URLSearchParams(location.search).get("token");
const $ = (s) => document.querySelector(s),
  esc = (s) =>
    String(s ?? "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
const money = (n) => new Intl.NumberFormat("hu-HU").format(n) + " Ft";
const datekey = (d) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
const localdt = (d) =>
  datekey(d) +
  "T" +
  String(d.getHours()).padStart(2, "0") +
  ":" +
  String(d.getMinutes()).padStart(2, "0");
let csrfToken;
const api = async (url, method = "GET", body, tenant) => {
  if (method !== "GET" && !csrfToken) {
    const response = await fetch("/api/csrf", {
      headers: { Accept: "application/json" },
    });
    const csrf = await response.json();
    csrfToken = csrf.token;
  }
  url += (url.includes("?") ? "&" : "?") + "provider=" + encodeURIComponent(tenant ?? (new URLSearchParams(location.search).get("provider") || "1"));
  const r = await fetch(url, {
    method,
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
  const d = await r.json();
  if (!r.ok) throw Error(d.error || d.message || "Sikertelen művelet");
  return d;
};
const toast = (t) => {
  $("#toast").textContent = t;
  $("#toast").style.display = "block";
  setTimeout(() => ($("#toast").style.display = "none"), 3500);
};
const statusName = {
  confirmed: "Megerősítve",
  pending: "Jóváhagyásra vár",
  cancelled: "Lemondva",
  rejected: "Elutasítva",
};
async function refresh() {
  state = await api(isPublic ? "/api/public" : "/api/state");
  render();
}
function showModal(html) {
  $("#modal").innerHTML = html;
  $("#modal").showModal();
}
function serviceChecks() {
 return `<div class="booking-service-picker"><div class="selected-services"></div><select aria-label="Szolgáltatás hozzáadása" onchange="addBookingService(this.value)"><option value="">Válassz szolgáltatást…</option>${state.services.filter(s=>!s.archived).map(s=>`<option value="${esc(s.id)}">${esc(s.name)} · ${s.duration} perc · ${money(s.price)}</option>`).join('')}</select><div hidden>${state.services.filter(s=>!s.archived).map(s=>`<input type="checkbox" name="services" value="${esc(s.id)}">`).join('')}</div></div>`;
}
function renderBookingServices(){
 const root=document.querySelector('.booking-service-picker');if(!root)return;
 const chosen=[...root.querySelectorAll('[name=services]:checked')].map(el=>el.value);
 root.querySelector('.selected-services').innerHTML=chosen.map(id=>{const s=state.services.find(s=>s.id===id);return `<div class="service-choice"><span><strong>${esc(s.name)}</strong><small>${s.duration} perc · ${money(s.price)}</small></span><button type="button" data-remove-service="${esc(id)}" aria-label="${esc(s.name)} eltávolítása">×</button></div>`;}).join('');
 root.querySelectorAll('[data-remove-service]').forEach(button=>button.onclick=()=>{root.querySelector(`[name=services][value="${CSS.escape(button.dataset.removeService)}"]`).checked=false;serviceSelectionChanged();});
 const select=root.querySelector('select');select.innerHTML='<option value="">'+(chosen.length?'Másik szolgáltatás hozzáadása…':'Válassz szolgáltatást…')+'</option>'+state.services.filter(s=>!s.archived&&!chosen.includes(s.id)).map(s=>`<option value="${esc(s.id)}">${esc(s.name)} · ${s.duration} perc · ${money(s.price)}</option>`).join('');select.disabled=chosen.length===state.services.filter(s=>!s.archived).length;
}
function addBookingService(id){if(!id)return;const field=document.querySelector(`.booking-service-picker [name=services][value="${CSS.escape(id)}"]`);if(field){field.checked=true;serviceSelectionChanged();}}
let adminSlotTicket=0;
async function loadAdminTimes(){
 const form=$('#booking-form'),date=$('#admin-booking-date'),select=$('#admin-booking-time');if(!form||!date||!select)return;
 const ticket=++adminSlotTicket,ids=[...form.querySelectorAll('[name=services]:checked')].map(e=>e.value),start=form.elements.namedItem('start');
 const previous=start.value||form.dataset.wanted||'';start.value='';
 if(!ids.length){select.innerHTML='<option value="">Először válassz szolgáltatást</option>';return;}
 select.innerHTML='<option value="">Szabad időpontok keresése…</option>';
 try{const slots=await api('/api/slots?'+new URLSearchParams({date:date.value,services:ids.join(','),staff:form.elements.namedItem('staff').value}));if(ticket!==adminSlotTicket||!form.isConnected)return;
  select.innerHTML=slots.length?'<option value="">Válassz időpontot…</option>'+slots.map(s=>`<option value="${esc(s.start)}">${esc(s.time)}</option>`).join(''):'<option value="">Nincs szabad időpont ezen a napon</option>';
  if(previous){const wanted=new Date(previous).getTime();const slot=slots.find(s=>new Date(s.start).getTime()>=wanted);if(slot){select.value=slot.start;start.value=slot.start;}}
 }catch(err){if(ticket===adminSlotTicket)select.innerHTML=`<option value="">${esc(err.message)}</option>`;}
}
function updateStaff() {
  renderBookingServices();
  const selected = [
    ...document.querySelectorAll("[name=services]:checked"),
  ].map((x) => x.value);
  const staff = state.staff.filter(
    (p) =>
      !p.archived &&
      (state.settings.businessMode !== "solo" ||
        p.id === state.settings.primaryStaffId) &&
      selected.every((id) =>
        state.services.find((s) => s.id === id).staff.includes(p.id),
      ),
  );
  const el = $("[name=staff]");
  const previousStaff=el?.value;
  if (el)
    el.innerHTML =
      (isPublic && staff.length > 1
        ? '<option value="any">Bárki megfelel</option>'
        : "") +
      staff
        .map((p) => `<option value="${p.id}">${esc(p.name)}</option>`)
        .join("");
  if(el&&[...el.options].some(option=>option.value===previousStaff))el.value=previousStaff;
  const total = selected.reduce(
    (a, id) => {
      const s = state.services.find((x) => x.id === id);
      return [a[0] + s.duration, a[1] + s.price];
    },
    [0, 0],
  );
  if (el && state.settings.businessMode === "solo") {
    const wrap = el.closest(".staff-field");
    if (wrap) wrap.hidden = true;
  }
  if (isPublic && el) {
    el.onchange = loadSlots;
    const wrap = el.closest(".staff-field");
    if (wrap)
      wrap.hidden = staff.length <= 1 || state.settings.businessMode === "solo";
    loadSlots();
  }
  if(!isPublic && el){el.onchange=loadAdminTimes;loadAdminTimes();}
  if ($("#total"))
    $("#total").textContent = total[0] + " perc · " + money(total[1]);
}
function bookingFields(dt) {
  return `<label>Szolgáltatások</label>${serviceChecks()}<p id="total" style="margin-top:12px">Válassz szolgáltatást</p><div class="staff-field"><label>Munkatárs</label><select name="staff"></select></div>${isPublic ? `<section id="booking-day-step" hidden><label>Nap kiválasztása</label><input type="date" id="booking-date" min="${datekey(new Date())}" value="" onchange="bookingDaySelected()"></section><section id="booking-slot-step" hidden><label>Szabad időpontok</label><div class="row" id="available-slots"></div></section><input type="hidden" name="start" required>` : `<div class="form-grid appointment-time-fields"><div><label>Nap</label><input id="admin-booking-date" type="date" required min="${datekey(new Date())}" value="${(dt || localdt(new Date())).slice(0,10)}" onchange="loadAdminTimes()"></div><div><label>Időpont</label><select id="admin-booking-time" onchange="this.form.elements.start.value=this.value" required><option value="">Először válassz szolgáltatást</option></select></div></div><input type="hidden" name="start" value="">`}<label>Vendég neve</label><input name="name" required placeholder="Pl. Szabó Kata" ${!isPublic ? 'list="clients" oninput="fillClient(this.value)"' : ""}><datalist id="clients">${(state.clients || []).map((c) => `<option value="${esc(c.name)}">${esc(c.phone)}</option><option value="${esc(c.phone)}">${esc(c.name)}</option>`).join("")}</datalist><div class="row"><div style="flex:1"><label>Telefonszám</label><input name="phone" type="tel" placeholder="+36 30 123 4567" ${!isPublic ? 'list="clients" oninput="fillClient(this.value)"' : ""}></div><div style="flex:1"><label>E-mail</label><input name="email" type="email" placeholder="nev@pelda.hu"></div></div><p style="font-size:11px;margin-top:10px">Telefonszám vagy e-mail megadása szükséges.</p><div class="error" id="form-error"></div>`;
}
function fillClient(value) {
  const c = state.clients.find((c) => c.name === value || c.phone === value);
  if (c)
    for (const k of ["name", "phone", "email"])
      $(`[name=${k}]`).value = c[k] || "";
}
function bookingForm(dt) {
  showModal(
    `<h2>Új időpont</h2><form id="booking-form">${bookingFields(dt)}<div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Időpont mentése</button></div></form>`,
  );
  $("#booking-form").dataset.wanted=dt || "";
  updateStaff();
  $("#booking-form").onsubmit = submitBooking;
}
async function submitBooking(e) {
  e.preventDefault();
  const f = new FormData(e.target),
    body = Object.fromEntries(f);
  body.services = f.getAll("services");
  body.public = isPublic;
  try {
    if (!body.start) throw Error("Válassz szabad időpontot.");
    body.start = new Date(body.start).toISOString();
    let b;
    if (body.staff === "any") {
      let last;
      for (const st of state.staff.filter((p) =>
        body.services.every((id) =>
          state.services.find((s) => s.id === id).staff.includes(p.id),
        ),
      )) {
        try {
          b = await api("/api/bookings", "POST", { ...body, staff: st.id });
          break;
        } catch (err) {
          last = err;
        }
      }
      if (!b) throw last || Error("Nincs elérhető munkatárs");
    } else b = await api("/api/bookings", "POST", body);
    if (isPublic) {
      let remembered = "";
      if (f.get("rememberContact")) {
        try {
          await api('/api/guest/profile', 'PATCH', {email: body.email || '', phone: body.phone || ''});
          remembered = '<p>Elérhetőségeidet megjegyeztük a következő foglaláshoz.</p>';
        } catch {
          remembered = '<p class="notice">A foglalás elkészült, de az elérhetőségek megjegyzése nem sikerült. A saját fiókodban elmentheted őket.</p>';
        }
      }
      trackBooking();
      $("#public-form").innerHTML =
        `<div class="success">${remembered}<h2>${b.status === "pending" ? "Foglalási kérésed megérkezett!" : "Az időpontod megerősítve!"}</h2><p>${b.status === "pending" ? "A szalon jóváhagyására vár." : "Várunk szeretettel!"}</p><p>Mentsd el a módosítási hivatkozásod. A szalon beállításai alapján e-mailes értesítőt is kaphatsz:</p><a href="/modositas?provider=${providerId()}&token=${b.token}">Foglalásom kezelése →</a></div>`;
    } else {
      $("#modal").close();
      await refresh();
      toast("Az időpontot elmentettük.");
    }
  } catch (err) {
    $("#form-error").textContent = err.message;
  }
}
function bookingDetails(id) {
  const b = state.bookings.find((x) => x.id === id);
  showModal(
    `<h2>${esc(b.name)}</h2><p>${new Date(b.start).toLocaleString("hu-HU")}</p><div class="notice">${statusName[b.status]}</div><p>${b.services.map((id) => esc(state.services.find((s) => s.id === id)?.name)).join(" + ")}<br>${esc(phoneFormatted(b.phone))} · ${esc(b.email)}</p><label>Időpont módosítása</label><input type="datetime-local" id="edit-start" value="${localdt(new Date(b.start))}"><button style="margin-top:10px" onclick="saveBookingTime('${b.id}')">Új időpont mentése</button><label>Vendég módosítási hivatkozása</label><input readonly value="${location.origin}/modositas?provider=${providerId()}&token=${b.token}"><div class="buttons"><button onclick="$('#modal').close()">Bezárás</button><button onclick="setStatus('${id}','cancelled')">Lemondás</button>${b.status === "pending" ? `<button onclick="setStatus('${id}','rejected')">Elutasítás</button><button class="primary" onclick="setStatus('${id}','confirmed')">Jóváhagyás</button>` : ""}</div>`,
  );
}
async function setStatus(id, status) {
  try {
    await api("/api/bookings/" + id, "PATCH", { status });
    $("#modal").close();
    await refresh();
    toast("Foglalás frissítve");
  } catch (e) {
    toast(e.message);
  }
}
async function configSave(body) {
  try {
    await api("/api/config", "PUT", body);
    if ($("#modal").open) $("#modal").close();
    await refresh();
    toast("Beállítások mentve");
  } catch (e) {
    toast(e.message);
  }
}
function publicPage() {
  $("#app").innerHTML =
    `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div style="text-align:center"><span class="badge">ONLINE IDŐPONTFOGLALÁS</span><h1 style="margin-top:20px">${esc(state.settings.name)}</h1><p>Egy kis idő, ami csak rólad szól.</p></div><div class="card" id="public-form"><h2>Foglalj időpontot</h2><p>Válassz szolgáltatást és időpontot. Regisztráció nem szükséges.</p><form id="booking-form">${bookingFields()}<div class="buttons"><button class="primary">Időpont lefoglalása →</button></div></form></div></div>`;
  updateStaff();
  $("#booking-form").onsubmit = submitBooking;
}
async function managePage() {
  try {
    const {
      booking: b,
      services,
      paymentReady,
      balance,
    } = await api("/api/manage?token=" + encodeURIComponent(manageToken));
    $("#app").innerHTML =
      `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div class="card"><h1>Az időpontod</h1><h3>${esc(b.name)}</h3><p>${b.services.map((id) => esc(services.find((s) => s.id === id)?.name)).join(" + ")}</p><div class="notice">${statusName[b.status]}</div><p>${new Date(b.start).toLocaleString("hu-HU")}</p>${!["cancelled", "rejected", "no_show"].includes(b.status) ? `<form id="manage-form"><label>Új időpont</label><input name="start" type="datetime-local" value="${localdt(new Date(b.start))}" required><div class="buttons"><button type="button" onclick="cancelBooking()">Foglalás lemondása</button><button class="primary">Időpont módosítása</button></div><div id="form-error" class="error"></div></form>` : ""}${paymentReady && balance > 0 && !["cancelled", "rejected", "no_show"].includes(b.status) ? `<button class="primary" style="margin-top:20px" onclick="payGuest()">Bankkártyás fizetés · ${money(balance)}</button>` : ""}<p style="margin-top:20px;font-size:12px">A változás megjelenik a szolgáltató naptárában. A beállításai szerint a szalon és a vendég is értesítést kap.</p></div></div>`;
    if ($("#manage-form"))
      $("#manage-form").onsubmit = async (e) => {
        e.preventDefault();
        try {
          await api("/api/manage", "PATCH", {
            token: manageToken,
            start: new Date(new FormData(e.target).get("start")).toISOString(),
          });
          await managePage();
          toast("Módosítás mentve");
        } catch (e) {
          $("#form-error").textContent = e.message;
        }
      };
  } catch (e) {
    $("#app").innerHTML =
      `<div class="public card item">${esc(e.message)}</div>`;
  }
}
async function cancelBooking() {
  if (!confirm("Biztosan lemondod a foglalást?")) return;
  await api("/api/manage", "PATCH", { token: manageToken, cancel: true });
  managePage();
}
let slotRequest = 0;
async function loadSlots() {
  if (!$("#available-slots")) return;
  const ticket = ++slotRequest;
  const ids = [...document.querySelectorAll("[name=services]:checked")].map(
    (x) => x.value,
  );
  $("[name=start]").value = "";
  $('#booking-day-step')?.toggleAttribute('hidden', !ids.length);
  $('#booking-slot-step')?.toggleAttribute('hidden', !ids.length || !$('#booking-date').value);
  if (!ids.length || !$('#booking-date').value) {
    $("#available-slots").innerHTML = "<p>Először válassz szolgáltatást.</p>";
    return;
  }
  $("#available-slots").innerHTML = "<p>Szabad időpontok keresése…</p>";
  try {
    const slots = await api(
      "/api/slots?" +
        new URLSearchParams({
          date: $("#booking-date").value,
          services: ids.join(","),
          staff: $("[name=staff]").value,
        }),
    );
    if (ticket !== slotRequest) return;
    renderSlotPicker(slots);
  } catch (e) {
    if (ticket === slotRequest) $("#available-slots").textContent = e.message;
  }
}
function selectSlot(button) {
  document
    .querySelectorAll("[data-start]")
    .forEach((b) => b.classList.remove("primary"));
  button.classList.add("primary");
  $("[name=start]").value = button.dataset.start;
}

async function saveBookingTime(id) {
  try {
    await api("/api/bookings/" + id, "PATCH", {
      start: new Date($("#edit-start").value).toISOString(),
    });
    $("#modal").close();
    await refresh();
    toast("Időpont módosítva");
  } catch (e) {
    toast(e.message);
  }
}
async function logout() {
  await api("/api/logout", "POST", {});
  location.reload();
}
