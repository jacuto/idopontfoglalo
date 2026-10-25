let state,
  view = "calendar",
  offset = 0,
  filter = "",
  isPublic = location.pathname === "/foglalas",
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
const api = async (url, method = "GET", body) => {
  const r = await fetch(url, {
    method,
    headers: { "Content-Type": "application/json" },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
  const d = await r.json();
  if (!r.ok) throw Error(d.error || "Sikertelen művelet");
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
function render() {
  if (isPublic) return publicPage();
  const labels = {
    calendar: "Naptár",
    clients: "Vendégek",
    services: "Szolgáltatások",
    staff: "Munkatársak",
    settings: "Beállítások",
    billing: "Előfizetés",
    activity: "Foglalási események",
  };
  $("#app").innerHTML =
    `<div class="shell"><aside class="sidebar"><div class="brand"><span>◷</span>ráérő<span style="font-size:17px;background:none;width:auto;margin:0">.</span></div><div class="workspace"><strong>${esc(state.settings.name)}</strong><small>Szolgáltatói munkaterület</small></div><nav>${Object.entries(
      labels,
    )
      .map(
        ([id, name], i) =>
          `<button class="${view === id ? "active" : ""}" data-nav="${id}"><i>${["▦", "♧", "☷", "♙", "⚙", "▱", "◉"][i]}</i><span>${name}</span></button>`,
      )
      .join(
        "",
      )}</nav><div class="trial"><strong>Teret adunk az indulásnak.</strong>2 hónap ingyenes próbaidő<div class="bar"></div>Alapcsomag · 4 000 Ft / hó<br>Kártyaadatok nélkül kipróbálható</div><div class="profile"><div class="avatar">HS</div><div>${esc(state.settings.name)}<small>Bemutató munkaterület</small></div></div></aside><main><header class="topbar"><div class="crumb">Munkaterület <span style="margin:0 12px">/</span> <b>${labels[view]}</b></div><div class="top-actions"><span class="badge">ELSŐ VÁLTOZAT</span><button onclick="window.open('/foglalas','_blank')">↗ Foglalási oldalam</button></div></header><div class="intro"><div><h1>${view === "calendar" ? "Minden időpont a helyén." : labels[view]}</h1><p>${view === "calendar" ? "Kevesebb szervezés. Több idő a vendégeidre." : "A szalonodhoz igazítva, egyszerűen."}</p></div>${["calendar", "clients", "services", "staff"].includes(view) ? `<button class="primary" onclick="${view === "calendar" ? "bookingForm()" : view === "clients" ? "clientForm()" : view === "services" ? "serviceForm()" : "staffForm()"}">＋ ${view === "calendar" ? "Új időpont" : view === "clients" ? "Új vendég" : view === "services" ? "Új szolgáltatás" : "Új munkatárs"}</button>` : ""}</div><div id="content"></div></main></div>`;
  document.querySelectorAll("[data-nav]").forEach(
    (b) =>
      (b.onclick = () => {
        view = b.dataset.nav;
        render();
      }),
  );
  ({
    calendar: calendarPage,
    clients: clientsPage,
    services: servicesPage,
    staff: staffPage,
    settings: settingsPage,
    billing: billingPage,
    activity: activityPage,
  })[view]();
}
function calendarPage() {
  const today = datekey(new Date()),
    active = state.bookings.filter(
      (b) => !["cancelled", "rejected"].includes(b.status),
    ),
    daily = active.filter((b) => datekey(new Date(b.start)) === today),
    pending = active.filter((b) => b.status === "pending");
  const revenue = daily.reduce(
    (n, b) =>
      n +
      b.services.reduce(
        (a, id) => a + (state.services.find((s) => s.id === id)?.price || 0),
        0,
      ),
    0,
  );
  const first = new Date();
  first.setDate(first.getDate() - ((first.getDay() + 6) % 7) + offset * 7);
  const days = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(first);
    d.setDate(d.getDate() + i);
    return d;
  });
  $("#content").innerHTML =
    `<div class="stats"><div class="stat"><div><small>Mai időpontok</small><strong>${daily.length}</strong><em>Gördülékenyen teljen a napod</em></div><div class="symbol">▦</div></div><div class="stat"><div><small>Jóváhagyásra vár</small><strong>${pending.length}</strong><em>${pending.length ? "Új vendégek várják a válaszod" : "Minden foglalás naprakész"}</em></div><div class="symbol">◷</div></div><div class="stat"><div><small>Mai tervezett bevétel</small><strong>${money(revenue)}</strong><em>A foglalt szolgáltatások alapján</em></div><div class="symbol">↗</div></div></div>${pending.length ? `<div class="notice">${pending.length} foglalás vár jóváhagyásra. A részletekhez kattints a sárga időpontokra.</div>` : ""}<div class="card calendar-card"><div class="calendar-tools"><div class="row"><h3>${first.toLocaleDateString("hu-HU", { year: "numeric", month: "long" })}</h3><button onclick="offset--;calendarPage()">‹</button><button onclick="offset++;calendarPage()">›</button><button onclick="offset=0;calendarPage()">Ma</button></div><div class="row"><select style="width:180px" onchange="filter=this.value;calendarPage()"><option value="">Minden munkatárs</option>${state.staff.map((s) => `<option value="${s.id}" ${filter === s.id ? "selected" : ""}>${esc(s.name)}</option>`).join("")}</select><button>Heti nézet</button></div></div><div class="calendar" style="--cols:7"><div class="day-head"></div>${days.map((d) => `<div class="day-head ${datekey(d) === today ? "today" : ""}">${d.toLocaleDateString("hu-HU", { weekday: "short" }).toUpperCase()}<strong>${d.getDate()}</strong></div>`).join("")}${Array.from(
      { length: 12 },
      (_, i) => {
        const h = i + 8;
        return (
          `<div class="hour">${h}:00</div>` +
          days
            .map((d) => {
              const dt = datekey(d) + "T" + String(h).padStart(2, "0") + ":00";
              const bs = active.filter(
                (b) =>
                  datekey(new Date(b.start)) === datekey(d) &&
                  new Date(b.start).getHours() === h &&
                  (!filter || b.staff === filter),
              );
              return `<div class="slot" role="button" tabindex="0" onclick="bookingForm('${dt}')">${bs.map((b) => `<div class="event ${b.status === "pending" ? "pending" : b.staff === state.staff[1]?.id ? "alt" : ""}" onclick="event.stopPropagation();bookingDetails('${b.id}')"><strong>${new Date(b.start).toLocaleTimeString("hu-HU", { hour: "2-digit", minute: "2-digit" })} · ${esc(b.name)}</strong>${b.services.map((id) => esc(state.services.find((s) => s.id === id)?.name)).join(" + ")}<br>${esc(state.staff.find((s) => s.id === b.staff)?.name)}</div>`).join("")}</div>`;
            })
            .join("")
        );
      },
    ).join(
      "",
    )}</div><div class="legend"><span><i class="dot"></i>Megerősített</span><span><i class="dot" style="background:#d3ab58"></i>Jóváhagyásra vár</span><span>Üres idősávra kattintva gyorsan felvehetsz időpontot.</span></div></div>`;
}
function showModal(html) {
  $("#modal").innerHTML = html;
  $("#modal").showModal();
}
function serviceChecks() {
  return state.services
    .map(
      (s) =>
        `<label class="check"><input type="checkbox" name="services" value="${s.id}" onchange="updateStaff()">${esc(s.name)}<span>${s.duration} p · ${money(s.price)}</span></label>`,
    )
    .join("");
}
function updateStaff() {
  const selected = [
    ...document.querySelectorAll("[name=services]:checked"),
  ].map((x) => x.value);
  const staff = state.staff.filter((p) =>
    selected.every((id) =>
      state.services.find((s) => s.id === id).staff.includes(p.id),
    ),
  );
  const el = $("[name=staff]");
  if (el)
    el.innerHTML =
      (isPublic && staff.length > 1
        ? '<option value="any">Bárki megfelel</option>'
        : "") +
      staff
        .map((p) => `<option value="${p.id}">${esc(p.name)}</option>`)
        .join("");
  const total = selected.reduce(
    (a, id) => {
      const s = state.services.find((x) => x.id === id);
      return [a[0] + s.duration, a[1] + s.price];
    },
    [0, 0],
  );
  if (isPublic && el) {
    el.onchange = loadSlots;
    const wrap = el.closest(".staff-field");
    if (wrap) wrap.hidden = staff.length <= 1;
    loadSlots();
  }
  if ($("#total"))
    $("#total").textContent = total[0] + " perc · " + money(total[1]);
}
function bookingFields(dt) {
  return `<label>Szolgáltatások</label>${serviceChecks()}<p id="total" style="margin-top:12px">Válassz szolgáltatást</p><div class="staff-field"><label>Munkatárs</label><select name="staff"></select></div>${isPublic ? `<label>Nap kiválasztása</label><input type="date" id="booking-date" min="${datekey(new Date())}" value="${datekey(new Date())}" onchange="loadSlots()"><label>Szabad időpontok</label><div class="row" id="available-slots"></div><input type="hidden" name="start" required>` : `<label>Időpont</label><input type="datetime-local" name="start" required value="${dt || localdt(new Date())}">`}<label>Vendég neve</label><input name="name" required placeholder="Pl. Szabó Kata" ${!isPublic ? 'list="clients" oninput="fillClient(this.value)"' : ""}><datalist id="clients">${(state.clients || []).map((c) => `<option value="${esc(c.name)}">${esc(c.phone)}</option><option value="${esc(c.phone)}">${esc(c.name)}</option>`).join("")}</datalist><div class="row"><div style="flex:1"><label>Telefonszám</label><input name="phone" type="tel" placeholder="+36 30 123 4567" ${!isPublic ? 'list="clients" oninput="fillClient(this.value)"' : ""}></div><div style="flex:1"><label>E-mail</label><input name="email" type="email" placeholder="nev@pelda.hu"></div></div><p style="font-size:11px;margin-top:10px">Telefonszám vagy e-mail megadása szükséges.</p><div class="error" id="form-error"></div>`;
}
function fillClient(value) {
  const c = state.clients.find((c) => c.name === value || c.phone === value);
  if (c)
    for (const k of ["name", "phone", "email"])
      $(`[name=${k}]`).value = c[k] || "";
}
function bookingForm(dt) {
  showModal(
    `<h2>Új időpont</h2><p>Néhány adat, és már a naptáradban is van.</p><form id="booking-form">${bookingFields(dt)}<div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Időpont mentése</button></div></form>`,
  );
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
      $("#public-form").innerHTML =
        `<div class="success"><h2>${b.status === "pending" ? "Foglalási kérésed megérkezett!" : "Az időpontod megerősítve!"}</h2><p>${b.status === "pending" ? "A szalon jóváhagyására vár." : "Várunk szeretettel!"}</p><p>Ebben a változatban nincs automatikus üzenetküldés. Mentsd el a módosítási hivatkozásod:</p><a href="/modositas?token=${b.token}">Foglalásom kezelése →</a></div>`;
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
    `<h2>${esc(b.name)}</h2><p>${new Date(b.start).toLocaleString("hu-HU")}</p><div class="notice">${statusName[b.status]}</div><p>${b.services.map((id) => esc(state.services.find((s) => s.id === id)?.name)).join(" + ")}<br>${esc(b.phone)} · ${esc(b.email)}</p><label>Időpont módosítása</label><input type="datetime-local" id="edit-start" value="${localdt(new Date(b.start))}"><button style="margin-top:10px" onclick="saveBookingTime('${b.id}')">Új időpont mentése</button><label>Vendég módosítási hivatkozása</label><input readonly value="${location.origin}/modositas?token=${b.token}"><div class="buttons"><button onclick="$('#modal').close()">Bezárás</button><button onclick="setStatus('${id}','cancelled')">Lemondás</button>${b.status === "pending" ? `<button onclick="setStatus('${id}','rejected')">Elutasítás</button><button class="primary" onclick="setStatus('${id}','confirmed')">Jóváhagyás</button>` : ""}</div>`,
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
function clientsPage() {
  $("#content").innerHTML =
    `<div class="row" style="margin-bottom:20px"><input id="search" placeholder="Keresés név, telefonszám vagy e-mail alapján…" oninput="clientRows(this.value)" style="flex:1"><button onclick="exportClients()">↓ Exportálás</button><button onclick="$('#import').click()">↑ Importálás</button><input hidden id="import" type="file" accept=".csv" onchange="importClients(this.files[0])"></div><div class="card"><table><thead><tr><th>Név</th><th>Telefonszám</th><th>E-mail</th></tr></thead><tbody id="client-rows"></tbody></table></div><p style="margin-top:14px;font-size:12px">CSV formátum: name;phone;email — az első sor fejléc.</p>`;
  clientRows("");
}
function clientRows(q) {
  const rows = state.clients.filter((c) =>
    Object.values(c)
      .join(" ")
      .toLocaleLowerCase()
      .includes(q.toLocaleLowerCase()),
  );
  $("#client-rows").innerHTML =
    rows
      .map(
        (c) =>
          `<tr><td><strong>${esc(c.name)}</strong></td><td>${esc(c.phone) || "—"}</td><td>${esc(c.email) || "—"}</td></tr>`,
      )
      .join("") ||
    '<tr><td colspan="3" class="muted">Még nincs megjeleníthető vendég. Az első foglaláskor automatikusan létrejön a névjegy.</td></tr>';
}
function clientForm() {
  showModal(
    `<h2>Új vendég</h2><form id="new-client"><label>Név</label><input name="name" required><label>Telefonszám</label><input name="phone"><label>E-mail</label><input name="email" type="email"><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Mentés</button></div></form>`,
  );
  $("#new-client").onsubmit = async (e) => {
    e.preventDefault();
    state.clients.push({
      id: crypto.randomUUID(),
      ...Object.fromEntries(new FormData(e.target)),
    });
    await configSave({ clients: state.clients });
  };
}
function exportClients() {
  const csv =
    "\uFEFFname;phone;email\n" +
    state.clients
      .map((c) =>
        [c.name, c.phone, c.email]
          .map((x) => '"' + String(x || "").replace(/"/g, '""') + '"')
          .join(";"),
      )
      .join("\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(
    new Blob([csv], { type: "text/csv;charset=utf-8" }),
  );
  a.download = "vendegek.csv";
  a.click();
  URL.revokeObjectURL(a.href);
}
async function importClients(file) {
  if (!file) return;
  try {
    const raw = (await file.text()).replace(/^\uFEFF/, "");
    const rows = [];
    let row = [],
      cell = "",
      quoted = false;
    for (let i = 0; i < raw.length; i++) {
      const c = raw[i];
      if (c === '"') {
        if (quoted && raw[i + 1] === '"') {
          cell += '"';
          i++;
        } else quoted = !quoted;
      } else if (c === ";" && !quoted) {
        row.push(cell);
        cell = "";
      } else if (c === "\n" && !quoted) {
        row.push(cell.replace(/\r$/, ""));
        rows.push(row);
        row = [];
        cell = "";
      } else cell += c;
    }
    if (cell || row.length) {
      row.push(cell);
      rows.push(row);
    }
    if (rows.shift()?.join(";") !== "name;phone;email")
      throw Error("A fejléc legyen: name;phone;email");
    let count = 0;
    for (const [name, phone, email] of rows) {
      if (!name) continue;
      if (
        state.clients.some((c) =>
          phone ? c.phone === phone : email && c.email === email,
        )
      )
        continue;
      state.clients.push({
        id: crypto.randomUUID(),
        name,
        phone: phone || "",
        email: email || "",
      });
      count++;
    }
    await configSave({ clients: state.clients });
    toast(count + " vendég importálva");
  } catch (e) {
    toast(e.message);
  }
}
function servicesPage() {
  $("#content").innerHTML =
    `<div class="grid">${state.services.map((s) => `<div class="card item"><span class="badge">${s.duration} PERC</span><h3 style="margin-top:20px">${esc(s.name)}</h3><p>${s.staff.map((id) => esc(state.staff.find((p) => p.id === id)?.name)).join(", ")}</p><div class="price">${money(s.price)}</div>${s.phases ? `<p>${s.phases[0]} p munka · ${s.phases[1]} p hatóidő · ${s.phases[2]} p munka</p>` : ""}<button style="margin-top:15px" onclick="serviceForm('${s.id}')">Szerkesztés</button></div>`).join("")}</div>`;
}
function serviceForm(editId) {
  const old = state.services.find((s) => s.id === editId);
  showModal(
    `<h2>${old ? "Szolgáltatás szerkesztése" : "Új szolgáltatás"}</h2><form id="new-service"><label>Megnevezés</label><input name="name" required value="${esc(old?.name || "")}"><div class="row"><div><label>Időtartam (perc)</label><input name="duration" type="number" min="5" value="${old?.duration || 60}" required></div><div><label>Ár (Ft)</label><input name="price" type="number" min="0" value="${old?.price ?? 10000}" required></div></div><label>Szakaszok (opcionális, percben: munka, hatóidő, munka)</label><input name="phases" placeholder="Például: 30,35,30" value="${old?.phases?.join(",") || ""}"><label class="check"><input name="automatic" type="checkbox" ${old?.automatic ? "checked" : ""}>Automatikus foglalásmegerősítés</label><label>Ki végzi?</label>${state.staff.map((s) => `<label class="check"><input type="checkbox" name="staff" value="${s.id}" ${!old || old.staff.includes(s.id) ? "checked" : ""}>${esc(s.name)}</label>`).join("")}<div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Mentés</button></div></form>`,
  );
  $("#new-service").onsubmit = async (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    const value = {
      ...old,
      id: old?.id || crypto.randomUUID(),
      name: f.get("name"),
      duration: Number(f.get("duration")),
      price: Number(f.get("price")),
      staff: f.getAll("staff"),
      automatic: f.has("automatic"),
    };
    if (f.get("phases").trim())
      value.phases = f.get("phases").split(",").map(Number);
    else delete value.phases;
    if (!value.staff.length) {
      toast("Legalább egy munkatársat válassz.");
      return;
    }
    if (old)
      state.services = state.services.map((s) => (s.id === old.id ? value : s));
    else state.services.push(value);
    await configSave({ services: state.services });
  };
}
function staffPage() {
  $("#content").innerHTML =
    `<div class="notice">Bemutató: több munkatárs kipróbálható. Éles előfizetés és csomagkorlát ebben a változatban még nincs.</div><div class="grid">${state.staff
      .map(
        (s) =>
          `<div class="card item"><div class="avatar" style="width:42px">${esc(
            s.name
              .split(" ")
              .map((x) => x[0])
              .join(""),
          )}</div><h3 style="margin-top:20px">${esc(s.name)}</h3><p>${esc(s.role)}</p><p style="margin-top:15px">${state.services.filter((v) => v.staff.includes(s.id)).length} szolgáltatás · saját naptár</p></div>`,
      )
      .join("")}</div>`;
}
function staffForm() {
  showModal(
    `<h2>Munkatárs felvétele</h2><form id="new-staff"><label>Név</label><input name="name" required><label>Szakterület</label><select name="role">${["Fodrász", "Masszőr", "Műkörmös", "Pedikűrös", "Szempilla-stylist"].map((x) => `<option>${x}</option>`).join("")}</select><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Mentés</button></div></form>`,
  );
  $("#new-staff").onsubmit = async (e) => {
    e.preventDefault();
    state.staff.push({
      id: crypto.randomUUID(),
      ...Object.fromEntries(new FormData(e.target)),
    });
    await configSave({ staff: state.staff });
  };
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
function settingsPage() {
  $("#content").innerHTML =
    `<div class="card item" style="max-width:720px"><form id="settings"><h3>Szalon és foglalás</h3><label>Vállalkozás neve</label><input name="name" value="${esc(state.settings.name)}" required><label>Szakterület</label><select name="profession">${["Szépségszalon", "Masszázs", "Fodrászat", "Műköröm", "Pedikűr", "Szempilla"].map((x) => `<option ${x === state.settings.profession ? "selected" : ""}>${x}</option>`).join("")}</select><label class="check"><input name="confirmation" type="checkbox" ${state.settings.confirmation ? "checked" : ""}>A vendégfoglalásokhoz kézi jóváhagyás szükséges</label><div class="row"><div><label>Nyitás (óra)</label><input name="open" type="number" min="0" max="23" value="${state.settings.open ?? 8}"></div><div><label>Zárás (óra)</label><input name="close" type="number" min="1" max="24" value="${state.settings.close ?? 20}"></div></div><h3 style="margin-top:30px">Emlékeztetők</h3><div class="notice">Az időzítés elmenthető, az e-mail- és SMS-küldés még nincs bekötve.</div><label>Hány órával előtte?</label><input name="reminder" type="number" min="1" value="${state.settings.reminder}"><label>Csatorna</label><select name="channel">${["email", "sms", "mindkettő"].map((c) => `<option ${c === state.settings.channel ? "selected" : ""}>${c}</option>`).join("")}</select><div class="buttons"><button class="primary">Beállítások mentése</button></div></form><h3>Beágyazás weboldalba</h3><textarea readonly>${esc('<iframe src="' + location.origin + '/foglalas" title="Időpontfoglalás" width="100%" height="1000" style="border:0"></iframe>')}</textarea><p>Saját aldomainhez később domain és üzemeltetési beállítás szükséges.</p></div>`;
  $("#settings").onsubmit = (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    configSave({
      settings: {
        ...Object.fromEntries(f),
        confirmation: f.has("confirmation"),
        reminder: Number(f.get("reminder")),
        open: Number(f.get("open")),
        close: Number(f.get("close")),
      },
    });
  };
}
function billingPage() {
  $("#content").innerHTML =
    `<div class="grid"><div class="card item"><span class="badge">ALAPCSOMAG</span><h2>Az induláshoz minden egy helyen.</h2><div class="price">4 000 Ft <small class="muted">/ hó</small></div><p>A fizetendő végösszeg.<br>1 munkatárs · 1 naptár<br>2 hónap ingyen, bankkártya nélkül.</p><div class="notice">Ez bemutató verzió. Nem indít előfizetést, nem terhel kártyát.</div></div><div class="card item"><h3>Fizetés és számlázás</h3><p>Stripe · bankkártyás előfizetés<br>Átutalás · díjbekérő alapján<br>Billingo · előfizetési számlák</p><h3 style="margin-top:28px">Szalonod számlázója</h3><p>Billingo vagy Számlázz.hu</p><div class="notice">Az integrációk még nincsenek bekötve. A kapcsolatokhoz külön hozzáférés és fejlesztés szükséges.</div></div></div>`;
}
function publicPage() {
  $("#app").innerHTML =
    `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div style="text-align:center"><span class="badge">ONLINE IDŐPONTFOGLALÁS</span><h1 style="margin-top:20px">${esc(state.settings.name)}</h1><p>Egy kis idő, ami csak rólad szól.</p></div><div class="card" id="public-form"><h2>Foglalj időpontot</h2><p>Válassz szolgáltatást és időpontot. Regisztráció nem szükséges.</p><form id="booking-form">${bookingFields()}<div class="notice">Bemutató változat: automatikus értesítőt még nem küldünk. Az időpontok magyarországi idő szerint jelennek meg.</div><div class="buttons"><button class="primary">Időpont lefoglalása →</button></div></form></div></div>`;
  updateStaff();
  $("#booking-form").onsubmit = submitBooking;
}
async function managePage() {
  try {
    const { booking: b, services } = await api(
      "/api/manage?token=" + encodeURIComponent(manageToken),
    );
    $("#app").innerHTML =
      `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div class="card"><h1>Az időpontod</h1><h3>${esc(b.name)}</h3><p>${b.services.map((id) => esc(services.find((s) => s.id === id)?.name)).join(" + ")}</p><div class="notice">${statusName[b.status]}</div><p>${new Date(b.start).toLocaleString("hu-HU")}</p>${!["cancelled", "rejected"].includes(b.status) ? `<form id="manage-form"><label>Új időpont</label><input name="start" type="datetime-local" value="${localdt(new Date(b.start))}" required><div class="buttons"><button type="button" onclick="cancelBooking()">Foglalás lemondása</button><button class="primary">Időpont módosítása</button></div><div id="form-error" class="error"></div></form>` : ""}<p style="margin-top:20px;font-size:12px">A változás megjelenik a szolgáltató naptárában. Automatikus üzenetküldés még nem működik.</p></div></div>`;
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
if (manageToken) managePage();
else
  refresh().catch((e) => {
    $("#app").innerHTML =
      `<div class="public card item"><h2>Szolgáltatói belépés</h2><p>A jelszó az indító terminálban található.</p><form id="login"><label>Jelszó</label><input type="password" name="password" required><div class="buttons"><button class="primary">Belépés</button></div><p id="login-error" class="error"></p></form></div>`;
    $("#login").onsubmit = async (e) => {
      e.preventDefault();
      try {
        await api(
          "/api/login",
          "POST",
          Object.fromEntries(new FormData(e.target)),
        );
        location.reload();
      } catch (e) {
        $("#login-error").textContent = e.message;
      }
    };
  });

let slotRequest = 0;
async function loadSlots() {
  if (!$("#available-slots")) return;
  const ticket = ++slotRequest;
  const ids = [...document.querySelectorAll("[name=services]:checked")].map(
    (x) => x.value,
  );
  $("[name=start]").value = "";
  if (!ids.length) {
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
    $("#available-slots").innerHTML =
      slots
        .map(
          (s) =>
            `<button type="button" data-start="${s.start}" onclick="selectSlot(this)">${s.time}</button>`,
        )
        .join("") ||
      "<p>Erre a napra nincs szabad időpont. Válassz másik napot.</p>";
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
function activityPage() {
  $("#content").innerHTML =
    `<div class="row" style="margin-bottom:18px"><p>Új, módosított és lemondott foglalások aktuális állapota.</p><button onclick="refresh()">Frissítés</button></div><div class="card"><table><thead><tr><th>Vendég</th><th>Időpont</th><th>Állapot</th><th></th></tr></thead><tbody>${
      [...state.bookings]
        .sort(
          (a, b) =>
            new Date(b.updatedAt || b.start) - new Date(a.updatedAt || a.start),
        )
        .map(
          (b) =>
            `<tr><td>${esc(b.name)}</td><td>${new Date(b.start).toLocaleString("hu-HU")}</td><td>${statusName[b.status]}</td><td><button onclick="bookingDetails('${b.id}')">Részletek</button></td></tr>`,
        )
        .join("") || '<tr><td colspan="4">Még nincs foglalás.</td></tr>'
    }</tbody></table></div><div class="notice">Itt követheted a változásokat. Külső e-mail- és SMS-értesítés még nincs.</div>`;
}
