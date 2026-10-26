let calendarMode = window.innerWidth < 650 ? "day" : "week",
  calendarAnchor = new Date(),
  reportFrom = datekey(
    new Date(new Date().getFullYear(), new Date().getMonth(), 1),
  ),
  reportTo = datekey(new Date());
const activePeople = () => state.staff.filter((p) => !p.archived);
const bookingValue = (b) =>
  (
    b.serviceSnapshot || state.services.filter((s) => b.services.includes(s.id))
  ).reduce((n, s) => n + Number(s.price), 0);
const serviceNames = (b) =>
  (b.serviceSnapshot || state.services.filter((s) => b.services.includes(s.id)))
    .map((s) => s.name)
    .join(" + ");
const paymentNet = (p) =>
  p.status === "paid" ? Number(p.amount) - Number(p.refundedAmount || 0) : 0;
const initials = (name) =>
  String(name || "")
    .split(/\s+/)
    .map((x) => x[0] || "")
    .slice(0, 2)
    .join("")
    .toUpperCase();
const timeMinutes = (t) => {
  const [h, m] = String(t).split(":").map(Number);
  return h * 60 + m;
};
const minutesTime = (n) =>
  String(Math.floor(n / 60)).padStart(2, "0") +
  ":" +
  String(n % 60).padStart(2, "0");
function render() {
  if (isPublic) return publicPage();
  if (state.settings.businessMode === "solo" && view === "staff")
    view = "calendar";
  const labels = {
    calendar: "Naptár",
    clients: "Vendégek",
    services: "Szolgáltatások",
    ...(state.settings.businessMode !== "solo" ? { staff: "Munkatársak" } : {}),
    activity: "Foglalási események",
    payments: "Befizetések",
    reports: "Statisztika",
    settings: "Beállítások",
    billing: "Előfizetés",
  };
  const icons = {
    calendar: "▦",
    clients: "♧",
    services: "☷",
    staff: "♙",
    activity: "◉",
    payments: "▱",
    reports: "◔",
    settings: "⚙",
    billing: "↗",
  };
  $("#app").innerHTML =
    `<div class="shell"><aside class="sidebar"><div class="brand"><span>◷</span>ráérő.</div><div class="workspace"><strong>${esc(state.settings.name)}</strong><small>${state.settings.businessMode === "solo" ? "Egyéni vállalkozás" : "Csapatmunkatér"}</small></div><nav>${Object.entries(
      labels,
    )
      .map(
        ([id, name]) =>
          `<button class="${view === id ? "active" : ""}" data-nav="${id}" aria-label="${name}" title="${name}"><i aria-hidden="true">${icons[id]}</i><span>${name}</span></button>`,
      )
      .join(
        "",
      )}</nav><div class="profile"><div class="avatar">${esc(initials(state.settings.name))}</div><div><strong>${esc(state.settings.name)}</strong><small>Szolgáltatói munkaterület</small></div></div></aside><main><header class="topbar"><div class="crumb">Munkaterület / <b>${labels[view]}</b></div><div class="top-actions"><button onclick="window.open('/foglalas','_blank')">↗ Foglalási oldalam</button><button onclick="logout()">Kilépés</button></div></header><div class="intro"><div><span class="eyebrow">${view === "calendar" ? "A NAPOD, ÁTLÁTHATÓAN" : "RÁÉRŐ MUNKATÉR"}</span><h1>${view === "calendar" ? "Minden időpont a helyén." : labels[view]}</h1><p>${view === "calendar" ? "Kevesebb szervezés. Több idő a vendégeidre." : "Minden fontos adat, egy helyen."}</p></div>${["calendar", "clients", "services", "staff"].includes(view) ? `<button class="primary" onclick="${{ calendar: "bookingForm()", clients: "clientForm()", services: "serviceForm()", staff: "staffForm()" }[view]}">＋ ${view === "calendar" ? "Új időpont" : view === "clients" ? "Új vendég" : view === "services" ? "Új szolgáltatás" : "Új munkatárs"}</button>` : ""}</div><div id="content"></div></main></div>`;
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
    activity: activityPage,
    payments: paymentsPage,
    reports: reportsPage,
    settings: settingsPage,
    billing: billingPage,
  })[view]();
}
function calendarMove(direction) {
  if (calendarMode === "month") {
    calendarAnchor = new Date(
      calendarAnchor.getFullYear(),
      calendarAnchor.getMonth() + direction,
      1,
    );
  } else
    calendarAnchor.setDate(
      calendarAnchor.getDate() + direction * (calendarMode === "week" ? 7 : 1),
    );
  calendarPage();
}
function calendarPage() {
  const today = datekey(new Date()),
    active = state.bookings.filter(
      (b) => !["cancelled", "rejected"].includes(b.status),
    ),
    daily = active.filter((b) => datekey(new Date(b.start)) === today),
    pending = active.filter((b) => b.status === "pending");
  const paid = (state.payments || [])
    .filter((p) => datekey(new Date(p.paidAt)) === today)
    .reduce((n, p) => n + paymentNet(p), 0);
  const first = new Date(calendarAnchor);
  if (calendarMode === "week")
    first.setDate(first.getDate() - ((first.getDay() + 6) % 7));
  if (calendarMode === "month") first.setDate(1);
  const days = Array.from(
    { length: calendarMode === "day" ? 1 : 7 },
    (_, i) => {
      const d = new Date(first);
      d.setDate(d.getDate() + i);
      return d;
    },
  );
  if (state.settings.businessMode === "solo") filter = "";
  const filtered = active.filter((b) => !filter || b.staff === filter);
  $("#content").innerHTML =
    `<div class="stats"><div class="stat"><div><small>Mai időpontok</small><strong>${daily.length}</strong></div><div class="symbol">▦</div></div><div class="stat"><div><small>Jóváhagyásra vár</small><strong>${pending.length}</strong></div><div class="symbol">◷</div></div><div class="stat"><div><small>Mai befizetések</small><strong>${money(paid)}</strong><em>Tervezett: ${money(daily.reduce((n, b) => n + bookingValue(b), 0))}</em></div><div class="symbol">↗</div></div></div><div class="card calendar-card"><div class="calendar-tools"><div class="row"><h3>${calendarAnchor.toLocaleDateString("hu-HU", { year: "numeric", month: "long", ...(calendarMode === "day" ? { day: "numeric" } : {}) })}</h3><button aria-label="Előző időszak" onclick="calendarMove(-1)">‹</button><button aria-label="Következő időszak" onclick="calendarMove(1)">›</button><button onclick="calendarAnchor=new Date();calendarPage()">Ma</button></div><div class="row">${
      state.settings.businessMode !== "solo" && activePeople().length > 1
        ? `<select aria-label="Munkatársszűrő" style="width:180px" onchange="filter=this.value;calendarPage()"><option value="">Minden munkatárs</option>${activePeople()
            .map(
              (p) =>
                `<option value="${esc(p.id)}" ${filter === p.id ? "selected" : ""}>${esc(p.name)}</option>`,
            )
            .join("")}</select>`
        : ""
    }<div class="segmented">${[
      ["day", "Napi"],
      ["week", "Heti"],
      ["month", "Havi"],
    ]
      .map(
        ([m, label]) =>
          `<button class="${calendarMode === m ? "selected" : ""}" onclick="calendarMode='${m}';calendarPage()">${label}</button>`,
      )
      .join(
        "",
      )}</div></div></div><div id="calendar-body"></div><div class="legend"><span><i class="dot"></i>Megerősített</span><span><i class="dot" style="background:#d28a12"></i>Jóváhagyásra vár</span><span>Kattints az üres idősávra a gyors felvitelhez.</span></div></div>`;
  if (calendarMode === "month") {
    const start = new Date(first);
    start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
    $("#calendar-body").innerHTML =
      `<div class="month-grid">${["Hétfő", "Kedd", "Szerda", "Csütörtök", "Péntek", "Szombat", "Vasárnap"].map((d) => `<div class="month-label">${d}</div>`).join("")}${Array.from(
        { length: 42 },
        (_, i) => {
          const d = new Date(start);
          d.setDate(d.getDate() + i);
          const key = datekey(d);
          const bs = filtered
            .filter((b) => datekey(new Date(b.start)) === key)
            .sort((a, b) => a.start.localeCompare(b.start));
          return `<div class="month-day ${d.getMonth() !== calendarAnchor.getMonth() ? "outside" : ""} ${key === today ? "today" : ""}"><button class="day-number" onclick="bookingForm('${key}T${state.settings.openingTime}')">${d.getDate()}</button>${bs
            .slice(0, 4)
            .map(
              (b) =>
                `<button class="month-event ${b.status === "pending" ? "pending" : ""}" onclick="bookingDetails('${b.id}')">${new Date(b.start).toLocaleTimeString("hu-HU", { hour: "2-digit", minute: "2-digit" })} ${esc(b.name)}</button>`,
            )
            .join(
              "",
            )}${bs.length > 4 ? `<button class="more" onclick="calendarMode='day';calendarAnchor=new Date('${key}T12:00');calendarPage()">+ ${bs.length - 4} további</button>` : ""}</div>`;
        },
      ).join("")}</div>`;
    return;
  }
  const dayKeys = days.map(datekey),
    visible = filtered.filter((b) =>
      dayKeys.includes(datekey(new Date(b.start))),
    );
  const starts = visible.map((b) => {
    const d = new Date(b.start);
    return d.getHours() * 60 + d.getMinutes();
  });
  const ends = visible.map(
    (b, i) =>
      starts[i] +
      (
        b.serviceSnapshot ||
        state.services.filter((s) => b.services.includes(s.id))
      ).reduce((n, s) => n + s.duration, 0),
  );
  const open = Math.min(
      timeMinutes(state.settings.openingTime || "08:00"),
      ...starts,
    ),
    close = Math.max(
      timeMinutes(state.settings.closingTime || "20:00"),
      ...ends,
    ),
    scale = 1.4,
    height = (close - open) * scale;
  const ticks = [];
  for (let m = open; m < close; m += 30) ticks.push(m);
  $("#calendar-body").innerHTML =
    `<div class="calendar-scroll"><div class="timeline" style="--cols:${days.length};--track-height:${height}px"><div class="timeline-heading"></div>${days.map((d) => `<div class="timeline-heading ${datekey(d) === today ? "today" : ""}">${d.toLocaleDateString("hu-HU", { weekday: "short" })}<strong>${d.getDate()}</strong></div>`).join("")}<div class="time-rail">${ticks.map((m) => `<span style="top:${(m - open) * scale}px">${minutesTime(m)}</span>`).join("")}</div>${days
      .map((d) => {
        const bookings = filtered
          .filter((b) => datekey(new Date(b.start)) === datekey(d))
          .sort((a, b) => a.start.localeCompare(b.start));
        let ends = [];
        const placed = bookings.map((b) => {
          const dt = new Date(b.start),
            start = dt.getHours() * 60 + dt.getMinutes(),
            duration = (
              b.serviceSnapshot ||
              state.services.filter((s) => b.services.includes(s.id))
            ).reduce((n, s) => n + s.duration, 0);
          let lane = ends.findIndex((end) => end <= start);
          if (lane < 0) lane = ends.length;
          ends[lane] = start + duration;
          return { b, start, duration, lane };
        });
        const lanes = Math.max(1, ends.length);
        return `<div class="day-track">${ticks.map((m) => `<button class="time-slot" aria-label="Időpont felvétele: ${datekey(d)} ${minutesTime(m)}" style="top:${(m - open) * scale}px;height:${Math.min(30, close - m) * scale}px" onclick="bookingForm('${datekey(d)}T${minutesTime(m)}')"></button>`).join("")}${placed.map(({ b, start, duration, lane }) => `<button class="calendar-event ${b.status === "pending" ? "pending" : ""}" style="top:${Math.max(0, start - open) * scale}px;height:${Math.max(36, Math.min(duration, close - start) * scale)}px;left:calc(${(lane / lanes) * 100}% + 3px);width:calc(${100 / lanes}% - 6px)" title="${esc(b.name + " · " + serviceNames(b))}" onclick="bookingDetails('${b.id}')"><strong>${new Date(b.start).toLocaleTimeString("hu-HU", { hour: "2-digit", minute: "2-digit" })} · ${esc(b.name)}</strong><span>${esc(serviceNames(b))}</span>${state.settings.businessMode !== "solo" ? `<small>${esc(state.staff.find((p) => p.id === b.staff)?.name || "")}</small>` : ""}</button>`).join("")}</div>`;
      })
      .join("")}</div></div>`;
}
function clientsPage() {
  $("#content").innerHTML =
    `<div class="toolbar"><input id="search" placeholder="Keresés név, telefonszám vagy e-mail alapján…" oninput="clientRows(this.value)"><button onclick="exportData('clients')">↓ Exportálás</button><button onclick="importPicker('clients')">↑ Importálás</button></div><div class="card table-scroll"><table><thead><tr><th>Név</th><th>Telefonszám</th><th>E-mail</th><th></th></tr></thead><tbody id="client-rows"></tbody></table></div>`;
  clientRows("");
}
function clientRows(query) {
  $("#client-rows").innerHTML =
    state.clients
      .filter((c) =>
        Object.values(c).join(" ").toLowerCase().includes(query.toLowerCase()),
      )
      .map(
        (c) =>
          `<tr><td><strong>${esc(c.name)}</strong></td><td>${esc(c.phone) || "—"}</td><td>${esc(c.email) || "—"}</td><td><button onclick="clientForm('${c.id}')">Szerkesztés</button></td></tr>`,
      )
      .join("") ||
    '<tr><td colspan="4">Még nincs megjeleníthető vendég.</td></tr>';
}
function clientForm(id) {
  const c = state.clients.find((c) => c.id === id) || {};
  showModal(
    `<h2>${id ? "Vendég szerkesztése" : "Új vendég"}</h2><form id="client-form"><label>Név</label><input name="name" value="${esc(c.name || "")}" required><label>Telefonszám</label><input name="phone" value="${esc(c.phone || "")}" type="tel"><label>E-mail</label><input name="email" value="${esc(c.email || "")}" type="email"><div id="form-error" class="error"></div><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Mentés</button></div></form>`,
  );
  $("#client-form").onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    try {
      if (id) await api("/api/clients/" + id, "PATCH", data);
      else
        await api("/api/config", "PUT", {
          clients: [...state.clients, { id: crypto.randomUUID(), ...data }],
        });
      $("#modal").close();
      await refresh();
      toast("Vendég mentve");
    } catch (err) {
      $("#form-error").textContent = err.message;
    }
  };
}
function staffPage() {
  $("#content").innerHTML =
    `<div class="toolbar"><p>${activePeople().length} aktív munkatárs</p><button onclick="exportData('staff')">↓ Exportálás</button><button onclick="importPicker('staff')">↑ Importálás</button></div><div class="entity-grid">${activePeople()
      .map(
        (p) =>
          `<article class="card item"><div class="avatar">${esc(initials(p.name))}</div><h3>${esc(p.name)}</h3><p>${esc(p.role)}</p><p>${esc(p.email || "")}<br>${esc(p.phone || "")}</p><p>${state.services.filter((v) => !v.archived && v.staff.includes(p.id)).length} szolgáltatás</p><div class="row item-actions"><button onclick="staffForm('${p.id}')">Szerkesztés</button><button class="danger" onclick="archiveStaff('${p.id}')">Törlés</button></div></article>`,
      )
      .join("")}</div>`;
}
function staffForm(id) {
  const p = state.staff.find((p) => p.id === id) || {};
  showModal(
    `<h2>${id ? "Munkatárs szerkesztése" : "Munkatárs felvétele"}</h2><form id="staff-form"><label>Név</label><input name="name" required value="${esc(p.name || "")}"><label>Szakterület</label><input name="role" required list="roles" value="${esc(p.role || "")}"><datalist id="roles">${["Fodrász", "Masszőr", "Műkörmös", "Pedikűrös", "Szempilla-stylist"].map((v) => `<option>${v}</option>`).join("")}</datalist><label>E-mail</label><input name="email" type="email" value="${esc(p.email || "")}"><label>Telefonszám</label><input name="phone" value="${esc(p.phone || "")}"><div class="error" id="form-error"></div><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Mentés</button></div></form>`,
  );
  $("#staff-form").onsubmit = async (e) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    try {
      if (id) await api("/api/staff/" + id, "PATCH", data);
      else
        await api("/api/config", "PUT", {
          staff: [...state.staff, { id: crypto.randomUUID(), ...data }],
        });
      $("#modal").close();
      await refresh();
      toast("Munkatárs mentve");
    } catch (err) {
      $("#form-error").textContent = err.message;
    }
  };
}
async function archiveStaff(id) {
  if (
    !confirm(
      "Törlöd az aktív munkatársak közül? A korábbi foglalásai megmaradnak. Kizárólag hozzá tartozó szolgáltatásai is archiválódnak.",
    )
  )
    return;
  try {
    await api("/api/staff/" + id, "DELETE");
    await refresh();
    toast("Munkatárs archiválva");
  } catch (e) {
    toast(e.message);
  }
}
function servicesPage() {
  $("#content").innerHTML = `<div class="entity-grid">${state.services
    .filter((s) => !s.archived)
    .map(
      (s) =>
        `<article class="card item"><span class="badge">${s.duration} PERC</span><h3>${esc(s.name)}</h3><p>${s.staff.map((id) => esc(state.staff.find((p) => p.id === id)?.name)).join(", ")}</p><div class="price">${money(s.price)}</div>${s.phases ? `<p>${s.phases[0]} p munka · ${s.phases[1]} p hatóidő · ${s.phases[2]} p munka</p>` : ""}<button onclick="serviceForm('${s.id}')">Szerkesztés</button></article>`,
    )
    .join("")}</div>`;
}
function serviceForm(id) {
  const old = state.services.find((s) => s.id === id) || {};
  showModal(
    `<h2>${id ? "Szolgáltatás szerkesztése" : "Új szolgáltatás"}</h2><form id="service-form"><label>Megnevezés</label><input name="name" value="${esc(old.name || "")}" required><div class="form-grid"><div><label>Időtartam (perc)</label><input name="duration" type="number" min="5" required value="${old.duration || 60}"></div><div><label>Ár (Ft)</label><input name="price" type="number" min="0" step="1" required value="${old.price ?? 10000}"></div></div><label>Szakaszok: munka, hatóidő, munka (opcionális)</label><input name="phases" placeholder="30,35,30" value="${old.phases?.join(",") || ""}"><label class="check"><input name="automatic" type="checkbox" ${old.automatic ? "checked" : ""}>Automatikus megerősítés</label><label>Ki végzi?</label>${activePeople()
      .map(
        (p) =>
          `<label class="check"><input type="checkbox" name="staff" value="${p.id}" ${!id || old.staff.includes(p.id) ? "checked" : ""}>${esc(p.name)}</label>`,
      )
      .join(
        "",
      )}<div id="form-error" class="error"></div><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Mentés</button></div></form>`,
  );
  $("#service-form").onsubmit = async (e) => {
    e.preventDefault();
    const f = new FormData(e.target);
    const v = {
      ...old,
      id: id || crypto.randomUUID(),
      name: f.get("name"),
      duration: Number(f.get("duration")),
      price: Number(f.get("price")),
      staff: f.getAll("staff"),
      automatic: f.has("automatic"),
    };
    if (f.get("phases").trim())
      v.phases = f.get("phases").split(",").map(Number);
    else delete v.phases;
    try {
      await api("/api/config", "PUT", {
        services: id
          ? state.services.map((s) => (s.id === id ? v : s))
          : [...state.services, v],
      });
      $("#modal").close();
      await refresh();
      toast("Szolgáltatás mentve");
    } catch (err) {
      $("#form-error").textContent = err.message;
    }
  };
}
const csvFields = {
  clients: ["id", "name", "phone", "email"],
  staff: ["id", "name", "role", "phone", "email"],
  bookings: [
    "id",
    "name",
    "phone",
    "email",
    "start",
    "staff",
    "services",
    "serviceSnapshot",
    "status",
  ],
};
function exportData(kind) {
  const rows = kind === "staff" ? activePeople() : state[kind],
    fields = csvFields[kind];
  const quote = (v) => {
    let text = String(Array.isArray(v) ? JSON.stringify(v) : (v ?? ""));
    if (
      typeof v === "string" &&
      (/^'/.test(text) || /^\s*[=+\-@\t\r]/.test(text))
    )
      text = "'" + text;
    return '"' + text.replace(/"/g, '""') + '"';
  };
  const csv =
    "\uFEFF" +
    fields.join(";") +
    "\n" +
    rows.map((row) => fields.map((k) => quote(row[k])).join(";")).join("\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(
    new Blob([csv], { type: "text/csv;charset=utf-8" }),
  );
  a.download = {
    clients: "vendegek.csv",
    staff: "munkatarsak.csv",
    bookings: "foglalasok.csv",
  }[kind];
  a.click();
  URL.revokeObjectURL(a.href);
}
function parseCsv(raw) {
  const rows = [];
  let row = [],
    cell = "",
    quoted = false;
  raw = raw.replace(/^\uFEFF/, "");
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
  if (quoted) throw Error("Lezáratlan idézőjel a CSV-ben.");
  if (cell || row.length) {
    row.push(cell.replace(/\r$/, ""));
    rows.push(row);
  }
  for (const row of rows)
    for (let i = 0; i < row.length; i++)
      if (
        row[i].startsWith("'") &&
        (/^'/.test(row[i].slice(1)) || /^\s*[=+\-@\t\r]/.test(row[i].slice(1)))
      )
        row[i] = row[i].slice(1);
  const fields = rows.shift()?.map((x) => x.trim());
  if (!fields?.includes("name")) throw Error("Hiányzó name fejléc.");
  return rows
    .filter((r) => r.some(Boolean))
    .map((r) =>
      Object.fromEntries(
        fields.map((key, i) => [
          key,
          ["services", "serviceSnapshot"].includes(key)
            ? JSON.parse(r[i] || "[]")
            : r[i] || "",
        ]),
      ),
    );
}
function importPicker(kind) {
  showModal(
    `<h2>${{ clients: "Vendégek", staff: "Munkatársak", bookings: "Foglalások" }[kind]} importálása</h2><p>Pontosvesszővel tagolt CSV, fejléc: <code>${csvFields[kind].join(";")}</code></p><p>Az exportált fájl visszaolvasható. Azonos azonosító frissít, új azonosító új tételt hoz létre. Foglalásimport nem küld értesítőket.</p><input style="margin-top:20px" id="csv-file" type="file" accept=".csv"><div class="error" id="form-error"></div><div class="buttons"><button onclick="$('#modal').close()">Mégse</button><button class="primary" id="import-submit">Importálás</button></div>`,
  );
  $("#import-submit").onclick = async () => {
    try {
      const file = $("#csv-file").files[0];
      if (!file) throw Error("Válassz CSV-fájlt.");
      const records = parseCsv(await file.text());
      const r = await api("/api/imports/" + kind, "POST", { records });
      $("#modal").close();
      await refresh();
      toast(`${r.added} új, ${r.updated} frissített tétel`);
    } catch (e) {
      $("#form-error").textContent = e.message;
    }
  };
}
function activityPage() {
  $("#content").innerHTML =
    `<div class="toolbar"><p>Foglalások és változások</p><button onclick="exportData('bookings')">↓ Exportálás</button><button onclick="importPicker('bookings')">↑ Importálás</button><button onclick="refresh()">Frissítés</button></div><div class="card table-scroll"><table><thead><tr><th>Vendég</th><th>Időpont</th><th>Szolgáltatás</th><th>Állapot</th><th></th></tr></thead><tbody>${
      [...state.bookings]
        .sort(
          (a, b) =>
            new Date(b.updatedAt || b.start) - new Date(a.updatedAt || a.start),
        )
        .map(
          (b) =>
            `<tr><td>${esc(b.name)}</td><td>${new Date(b.start).toLocaleString("hu-HU")}</td><td>${esc(serviceNames(b))}</td><td><span class="status ${b.status}">${statusName[b.status]}</span></td><td><button onclick="bookingDetails('${b.id}')">Részletek</button></td></tr>`,
        )
        .join("") || '<tr><td colspan="5">Még nincs foglalás.</td></tr>'
    }</tbody></table></div>`;
}
function settingsPage() {
  const s = state.settings;
  $("#content").innerHTML =
    `<div class="settings-layout"><div class="card item"><form id="settings-form"><h2>Vállalkozás és foglalás</h2><div class="form-grid"><div><label>Vállalkozás neve</label><input name="name" value="${esc(s.name)}" required></div><div><label>Szakterület</label><select name="profession">${["Szépségszalon", "Masszázs", "Fodrászat", "Műköröm", "Pedikűr", "Szempilla"].map((v) => `<option ${v === s.profession ? "selected" : ""}>${v}</option>`).join("")}</select></div><div><label>Működés</label><select name="businessMode"><option value="solo" ${s.businessMode === "solo" ? "selected" : ""}>Egyéni vállalkozás</option><option value="team" ${s.businessMode !== "solo" ? "selected" : ""}>Több munkatársas vállalkozás</option></select></div><div><label>Elsődleges szakember (egyéni működésnél)</label><select name="primaryStaffId">${activePeople()
      .map(
        (p) =>
          `<option value="${p.id}" ${p.id === s.primaryStaffId ? "selected" : ""}>${esc(p.name)}</option>`,
      )
      .join(
        "",
      )}</select></div><div><label>Nyitás</label><input name="openingTime" type="time" required value="${s.openingTime || "08:00"}"></div><div><label>Zárás</label><input name="closingTime" type="time" required value="${s.closingTime || "20:00"}"></div></div><label class="check"><input name="confirmation" type="checkbox" ${s.confirmation ? "checked" : ""}>Az új vendégfoglaláshoz jóváhagyás szükséges</label><p class="hint">Egyéni módban a munkatársmenü és a naptár munkatársszűrője eltűnik. A vendégek az elsődleges szakemberhez foglalnak; a korábbi adatok megmaradnak.</p>
 <details open><summary>E-mailes értesítések és emlékeztetők</summary><label>Szolgáltató értesítési e-mail-címe</label><input name="providerEmail" type="email" value="${esc(s.providerEmail || "")}"><label class="check"><input name="notifyGuest" type="checkbox" ${s.notifyGuest ? "checked" : ""}>Vendég értesítése új foglalásról, módosításról és lemondásról</label><label class="check"><input name="notifyProvider" type="checkbox" ${s.notifyProvider ? "checked" : ""}>Szolgáltató értesítése új foglalásról, módosításról és lemondásról</label><label class="check"><input name="reminderGuest" type="checkbox" ${s.reminderGuest ? "checked" : ""}>Időpont előtti emlékeztető a vendégnek</label><div class="form-grid"><div><label>Hány órával előtte?</label><input name="reminder" type="number" min="1" max="8760" value="${s.reminder}" required></div><div><label>Emlékeztető csatornája</label><select name="channel">${[
   ["email", "E-mail"],
   ["sms", "SMS"],
   ["mindkettő", "E-mail és SMS"],
 ]
   .map(
     ([v, l]) =>
       `<option value="${v}" ${v === s.channel ? "selected" : ""}>${l}</option>`,
   )
   .join(
     "",
   )}</select></div></div><p class="hint">Küldéshez állítsd be az SMTP- vagy Twilio-kapcsolatot és a percenként futó cronfeladatot. A hiányzó kapcsolat miatt várakozó levelek a küldési sorban látszanak.</p></details>
 <details><summary>Számlázási adatok</summary><div class="form-grid">${[
   ["billingName", "Számlázási név"],
   ["billingTaxNumber", "Adószám (ha van)"],
   ["billingCountry", "Országkód"],
   ["billingZip", "Irányítószám"],
   ["billingCity", "Település"],
   ["billingAddress", "Utca, házszám"],
 ]
   .map(
     ([k, l]) =>
       `<div><label>${l}</label><input name="${k}" value="${esc(s[k] || "")}" ${k === "billingCountry" ? 'maxlength="2"' : ""}></div>`,
   )
   .join(
     "",
   )}</div><label>Vendégeid számlázója</label><select name="invoiceProvider">${[
   ["none", "Nincs csatlakoztatva"],
   ["billingo", "Billingo"],
   ["szamlazz", "Számlázz.hu"],
 ]
   .map(
     ([v, l]) =>
       `<option value="${v}" ${v === s.invoiceProvider ? "selected" : ""}>${l}</option>`,
   )
   .join(
     "",
   )}</select><label>Áfakulcs a vendégszámlákon</label><select name="invoiceVat">${["AAM", "27%", "18%", "5%", "0%"].map((v) => `<option ${v === s.invoiceVat ? "selected" : ""}>${v}</option>`).join("")}</select><p class="hint">A saját vállalkozásod számlázási adatai az előfizetéshez tartoznak. A vendég számlázási adatait számlakiállításkor külön kell megadni. A számla a Befizetések menüből indítható.</p></details>
 <details><summary>Google és Facebook mérés</summary><p class="hint">Azonosítót adj meg, ne teljes JavaScript-kódot. A mérés csak a vendég hozzájárulása után indul.</p><div class="form-grid">${[
   ["googleAnalyticsId", "Google Analytics 4", "G-XXXXXXXXXX"],
   ["googleTagManagerId", "Google Tag Manager", "GTM-XXXXXXX"],
   ["googleAdsId", "Google Ads", "AW-123456789"],
   [
     "googleAdsLabel",
     "Google Ads foglalási konverzió címkéje",
     "Konverziócímke",
   ],
   ["facebookPixelId", "Meta / Facebook Pixel", "123456789012345"],
 ]
   .map(
     ([k, l, p]) =>
       `<div><label>${l}</label><input name="${k}" value="${esc(s[k] || "")}" placeholder="${p}"></div>`,
   )
   .join("")}</div></details>
 <details><summary>Beágyazás saját weboldalba</summary><label>Engedélyezett weboldalak (soronként egy eredet)</label><textarea name="embedOrigins" placeholder="https://digitmarketing.hu">${esc((s.embedOrigins || []).join("\n"))}</textarea><label>Beilleszthető kód</label><textarea readonly rows="4">${esc('<script charset="utf-8" src="' + location.origin + '/embed.js" defer></script>\n<raero-booking endpoint="' + location.origin + '"></raero-booking>')}</textarea><p class="hint">Önálló webkomponens, iframe nélkül, elkülönített stílusokkal. A befogadó oldal pontos https://domain.hu címét engedélyezni kell. A foglalás adatvédelmi/mérési beállításait a befogadó oldal is vegye figyelembe.</p></details><div id="settings-error" class="error"></div><div class="buttons"><button class="primary">Beállítások mentése</button></div></form></div><div class="card item"><h2>Szolgáltatói kapcsolatok</h2><p>A titkos kulcsok titkosítva tárolódnak. Az üresen hagyott mezők megtartják a korábbi értéket.</p><div id="connections-form"><p>Kapcsolatok betöltése…</p></div></div><div class="card item"><h2>Értesítési küldési sor</h2><div class="row"><button onclick="deliveryRows()">Frissítés</button><button onclick="runDeliveries()">Esedékes küldések most</button></div><div class="table-scroll" id="delivery-table"></div></div></div>`;
  $("#settings-form").onsubmit = async (e) => {
    e.preventDefault();
    const f = new FormData(e.target),
      v = Object.fromEntries(f);
    for (const k of [
      "confirmation",
      "notifyGuest",
      "notifyProvider",
      "reminderGuest",
    ])
      v[k] = f.has(k);
    v.reminder = Number(v.reminder);
    v.open = timeMinutes(v.openingTime) / 60;
    v.close = timeMinutes(v.closingTime) / 60;
    try {
      v.embedOrigins = v.embedOrigins
        .split(/\n/)
        .map((x) => x.trim())
        .filter(Boolean)
        .map((x) => {
          const u = new URL(x);
          if (!["https:", "http:"].includes(u.protocol))
            throw Error("Csak http/https webcím adható meg.");
          return u.origin;
        });
      await api("/api/config", "PUT", { settings: v });
      await refresh();
      toast("Beállítások mentve");
    } catch (err) {
      $("#settings-error").textContent = err.message;
    }
  };
  connectionsForm();
  deliveryRows();
}
async function connectionsForm() {
  try {
    const c = await api("/api/connections");
    if (!$("#connections-form")) return;
    const groups = [
      [
        "E-mail / SMTP",
        [
          ["smtp_host", "SMTP-kiszolgáló"],
          ["smtp_port", "Port (általában 587 vagy 465)"],
          ["smtp_username", "Felhasználónév"],
          ["smtp_password", "SMTP-jelszó", "secret"],
          ["mail_from", "Feladó e-mail-címe"],
          ["mail_name", "Feladó neve"],
        ],
      ],
      [
        "Számlázók",
        [
          ["billingo_key", "Billingo API-kulcs", "secret"],
          ["billingo_block", "Billingo számlatömb azonosító"],
          ["szamlazz_key", "Számlázz.hu Számla Agent-kulcs", "secret"],
        ],
      ],
      [
        "Stripe — vendégek kártyás fizetése",
        [
          ["stripe_key", "Stripe titkos API-kulcs", "secret"],
          ["stripe_webhook_secret", "Webhook aláírási titok", "secret"],
        ],
      ],
      [
        "SMS / Twilio",
        [
          ["twilio_sid", "Account SID", "secret"],
          ["twilio_token", "Auth Token", "secret"],
          [
            "twilio_from",
            "Feladó telefonszám vagy Messaging Service azonosító",
          ],
        ],
      ],
      [
        "Google és Facebook belépés",
        [
          ["google_client_id", "Google Client ID"],
          ["google_client_secret", "Google Client Secret", "secret"],
          ["facebook_client_id", "Facebook App ID"],
          ["facebook_client_secret", "Facebook App Secret", "secret"],
          [
            "admin_google_emails",
            "Adminnak engedélyezett Google e-mailek (vesszővel)",
          ],
          [
            "admin_facebook_ids",
            "Adminnak engedélyezett Facebook felhasználóazonosítók (vesszővel)",
          ],
        ],
      ],
    ];
    $("#connections-form").innerHTML =
      `<form id="connection-settings">${groups.map(([title, fields]) => `<details><summary>${title}</summary><div class="form-grid">${fields.map(([key, label, secret]) => `<div><label>${label} ${c[key + "_configured"] ? '<span class="configured">megadva</span>' : ""}</label><input name="${key}" type="${secret ? "password" : "text"}" autocomplete="off" value="${secret ? "" : esc(c[key] || "")}" placeholder="${secret && c[key + "_configured"] ? "Már megadva — üresen hagyható" : ""}"></div>`).join("")}</div></details>`).join("")}<div class="notice">OAuth-visszahívási címek:<br><code>${esc(location.origin)}/auth/google/callback</code><br><code>${esc(location.origin)}/auth/facebook/callback</code><br>Stripe webhook: <code>${esc(location.origin)}/api/stripe/webhook</code><br>Vendégfiók létrehozható; adminbelépéshez külön engedélyezett fiók szükséges.</div><div class="error" id="connections-error"></div><div class="buttons"><button class="primary">Kapcsolatok mentése</button></div></form>`;
    $("#connection-settings").onsubmit = async (e) => {
      e.preventDefault();
      try {
        const data = Object.fromEntries(new FormData(e.target));
        if (data.smtp_port) data.smtp_port = Number(data.smtp_port);
        if (data.billingo_block)
          data.billingo_block = Number(data.billingo_block);
        await api("/api/connections", "PUT", data);
        toast("Kapcsolatok mentve");
        connectionsForm();
      } catch (err) {
        $("#connections-error").textContent = err.message;
      }
    };
  } catch (e) {
    if ($("#connections-form")) $("#connections-form").textContent = e.message;
  }
}
async function deliveryRows() {
  try {
    const rows = await api("/api/deliveries");
    if (!$("#delivery-table")) return;
    const labels = {
      pending: "Várakozik",
      blocked: "Kapcsolatra vár",
      processing: "Küldés alatt",
      sent: "Elküldve",
      skipped: "Már nem aktuális",
      failed: "Sikertelen",
    };
    $("#delivery-table").innerHTML =
      `<table><thead><tr><th>#</th><th>Állapot</th><th>Esedékes</th><th></th></tr></thead><tbody>${rows.map((r) => `<tr><td>${r.id}</td><td>${labels[r.status] || r.status}${r.error ? `<small class="muted"> · ${esc(r.error)}</small>` : ""}</td><td>${new Date(r.available_at).toLocaleString("hu-HU")}</td><td>${["failed", "blocked"].includes(r.status) ? `<button onclick="retryDelivery(${r.id})">Újrapróbálás</button>` : ""}</td></tr>`).join("") || '<tr><td colspan="4">Nincs küldési feladat.</td></tr>'}</tbody></table>`;
  } catch (e) {
    if ($("#delivery-table")) $("#delivery-table").textContent = e.message;
  }
}
async function runDeliveries() {
  try {
    const r = await api("/api/deliveries/run", "POST", {});
    toast(
      `${r.sent} elküldve · ${r.blocked} kapcsolatra vár · ${r.failed} hiba`,
    );
    deliveryRows();
  } catch (e) {
    toast(e.message);
  }
}
async function retryDelivery(id) {
  await api("/api/deliveries/" + id + "/retry", "POST", {});
  deliveryRows();
}
function paymentsPage() {
  const payments = [...(state.payments || [])].sort(
    (a, b) => new Date(b.paidAt) - new Date(a.paidAt),
  );
  $("#content").innerHTML =
    `<div class="toolbar"><p>A ténylegesen beérkezett összegek nyilvántartása.</p><button class="primary" onclick="paymentForm()">＋ Befizetés rögzítése</button></div><div class="card table-scroll"><table><thead><tr><th>Vendég / időpont</th><th>Befizetés</th><th>Összeg</th><th>Fizetés</th><th>Számla</th><th></th></tr></thead><tbody>${
      payments
        .map((p) => {
          const b = state.bookings.find((b) => b.id === p.bookingId),
            invoice = (state.invoiceRequests || []).find(
              (i) =>
                (i.paymentId === p.id || i.bookingId === p.bookingId) &&
                ["issued", "issuing", "unknown"].includes(i.status),
            );
          return `<tr><td><strong>${esc(b?.name || "Ismeretlen")}</strong><small>${b ? new Date(b.start).toLocaleString("hu-HU") : ""}</small></td><td>${new Date(p.paidAt).toLocaleDateString("hu-HU")}</td><td><strong>${money(paymentNet(p))}</strong>${p.refundedAmount ? `<small>Visszatérítve: ${money(p.refundedAmount)}</small>` : ""}</td><td>${{ cash: "Készpénz", card: "Bankkártya", transfer: "Átutalás" }[p.method]}<small>${p.status === "void" ? "Érvénytelenítve" : p.status === "refunded" ? "Visszatérítve" : p.source === "stripe" ? "Stripe által igazolva" : "Kézzel rögzítve"}</small></td><td>${invoice ? esc(invoice.number || { issuing: "Folyamatban", unknown: "Ellenőrzendő" }[invoice.status]) + (invoice.status === "unknown" ? `<button onclick="invoiceReconcileForm('${invoice.id}')">Egyeztetés</button>` : "") : p.status === "paid" ? `<button onclick="invoiceForm('${p.id}')">Számla készítése</button>` : "—"}</td><td>${p.source === "manual" && p.status === "paid" ? `<button onclick="voidPayment('${p.id}')">Érvénytelenítés</button>` : ""}</td></tr>`;
        })
        .join("") ||
      '<tr><td colspan="6">Még nincs rögzített befizetés.</td></tr>'
    }</tbody></table></div><div class="notice">Kézi rögzítéskor a beérkezést te igazolod. A Stripe-befizetést csak ellenőrzött webhook írja jóvá. A számlázás külön művelet.</div>`;
}
function paymentForm(bookingId) {
  const choices = state.bookings.filter(
    (b) =>
      bookingValue(b) >
      (state.payments || [])
        .filter((p) => p.bookingId === b.id)
        .reduce((n, p) => n + paymentNet(p), 0),
  );
  showModal(
    `<h2>Befizetés rögzítése</h2><form id="payment-form"><label>Foglalás</label><select name="bookingId" required>${choices.map((b) => `<option value="${b.id}" ${b.id === bookingId ? "selected" : ""}>${esc(b.name)} · ${new Date(b.start).toLocaleString("hu-HU")} · ${money(bookingValue(b))}</option>`).join("")}</select><label>Összeg (Ft)</label><input name="amount" type="number" min="1" step="1" required><label>Fizetés módja</label><select name="method"><option value="cash">Készpénz</option><option value="card">Bankkártya</option><option value="transfer">Átutalás</option></select><label>Befizetés ideje</label><input name="paidAt" type="datetime-local" required value="${localdt(new Date())}"><label>Megjegyzés</label><input name="note"><div id="form-error" class="error"></div><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Mentés</button></div></form>`,
  );
  const update = () => {
    const id = $("[name=bookingId]").value,
      b = state.bookings.find((x) => x.id === id);
    $("[name=amount]").value = b
      ? bookingValue(b) -
        (state.payments || [])
          .filter((p) => p.bookingId === id)
          .reduce((n, p) => n + paymentNet(p), 0)
      : "";
  };
  $("[name=bookingId]").onchange = update;
  update();
  $("#payment-form").onsubmit = async (e) => {
    e.preventDefault();
    try {
      const f = Object.fromEntries(new FormData(e.target));
      f.amount = Number(f.amount);
      f.paidAt = new Date(f.paidAt).toISOString();
      await api("/api/payments", "POST", f);
      $("#modal").close();
      await refresh();
      toast("Befizetés rögzítve");
    } catch (err) {
      $("#form-error").textContent = err.message;
    }
  };
}
async function voidPayment(id) {
  if (
    !confirm(
      "Érvényteleníted a kézzel rögzített befizetést? A kiállított számlát ez nem sztornózza.",
    )
  )
    return;
  try {
    await api("/api/payments/" + id + "/void", "POST", {});
    await refresh();
  } catch (e) {
    toast(e.message);
  }
}
function invoiceForm(paymentId) {
  const p = state.payments.find((p) => p.id === paymentId),
    b = state.bookings.find((b) => b.id === p.bookingId),
    buyer = p.buyer || { name: b.name, email: b.email, country: "HU" };
  showModal(
    `<h2>Számla készítése</h2><p>${money(bookingValue(b))} · teljes foglalás · ${esc(state.settings.invoiceProvider)}</p><form id="invoice-form"><div class="form-grid">${[
      ["name", "Vevő neve"],
      ["email", "E-mail"],
      ["country", "Országkód"],
      ["zip", "Irányítószám"],
      ["city", "Település"],
      ["address", "Utca, házszám"],
      ["taxNumber", "Adószám (ha van)"],
    ]
      .map(
        ([k, l]) =>
          `<div><label>${l}</label><input name="${k}" value="${esc(buyer[k] || "")}" ${!["email", "taxNumber"].includes(k) ? "required" : ""}></div>`,
      )
      .join(
        "",
      )}</div><div class="notice">Valódi számla készül a megadott számlázóban. Ellenőrizd a vevő adatait és az áfakulcsot: ${esc(state.settings.invoiceVat)}.</div><div id="form-error" class="error"></div><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Számla kiállítása</button></div></form>`,
  );
  $("#invoice-form").onsubmit = async (e) => {
    e.preventDefault();
    const button = e.target.querySelector("button.primary");
    button.disabled = true;
    try {
      const r = await api("/api/invoices", "POST", {
        paymentId,
        buyer: Object.fromEntries(new FormData(e.target)),
      });
      $("#modal").close();
      await refresh();
      toast("Számla elkészült: " + r.number);
    } catch (err) {
      $("#form-error").textContent = err.message;
      button.disabled = false;
    }
  };
}
function reportsPage() {
  const ps = (state.payments || []).filter((p) => {
    const d = datekey(new Date(p.paidAt));
    return d >= reportFrom && d <= reportTo && paymentNet(p) > 0;
  });
  const total = ps.reduce((n, p) => n + paymentNet(p), 0),
    parts = new Map();
  for (const p of ps) {
    const b = state.bookings.find((b) => b.id === p.bookingId);
    if (!b) continue;
    const services =
      b.serviceSnapshot ||
      state.services.filter((s) => b.services.includes(s.id));
    const sum = services.reduce((n, s) => n + s.price, 0);
    for (const s of services)
      parts.set(
        s.name,
        (parts.get(s.name) || 0) +
          (sum
            ? (paymentNet(p) * s.price) / sum
            : paymentNet(p) / services.length),
      );
  }
  const entries = [...parts].sort((a, b) => b[1] - a[1]);
  const colors = [
    "#176b56",
    "#5b56d6",
    "#d38a17",
    "#0e8ea1",
    "#cc5278",
    "#5b8c2e",
  ];
  let acc = 0;
  const gradient = entries
    .map(([, v], i) => {
      const start = acc;
      acc += (v / (total || 1)) * 100;
      return `${colors[i % colors.length]} ${start}% ${acc}%`;
    })
    .join(",");
  const planned = state.bookings
    .filter(
      (b) =>
        !["cancelled", "rejected"].includes(b.status) &&
        datekey(new Date(b.start)) >= reportFrom &&
        datekey(new Date(b.start)) <= reportTo,
    )
    .reduce((n, b) => n + bookingValue(b), 0);
  $("#content").innerHTML =
    `<div class="toolbar"><label>Kezdőnap<input id="report-from" type="date" value="${reportFrom}"></label><label>Zárónap<input id="report-to" type="date" value="${reportTo}"></label><button class="primary" onclick="applyReports()">Szűrés</button><button onclick="reportFrom=datekey(new Date(new Date().getFullYear(),new Date().getMonth(),1));reportTo=datekey(new Date());reportsPage()">Ez a hónap</button><button onclick="reportFrom=datekey(new Date(new Date().getFullYear(),0,1));reportTo=datekey(new Date());reportsPage()">Ez az év</button></div><div class="stats"><div class="stat"><div><small>Tényleges befizetés</small><strong>${money(total)}</strong><em>A befizetés dátuma alapján, visszatérítésekkel csökkentve</em></div></div><div class="stat"><div><small>Befizetések száma</small><strong>${ps.length}</strong></div></div><div class="stat"><div><small>Tervezett szolgáltatásérték</small><strong>${money(planned)}</strong><em>A foglalás dátuma alapján</em></div></div></div><div class="card item"><h2>Bevétel szolgáltatásonként</h2><p>A több szolgáltatásos foglalások befizetését az árak arányában osztjuk fel.</p><div class="chart-layout"><div class="donut" role="img" aria-label="Szolgáltatásonkénti bevétel kördiagram" style="background:${entries.length ? `conic-gradient(${gradient})` : "#e2e8f0"}"><div><strong>${money(total)}</strong><small>Beérkezett összeg</small></div></div><div class="chart-legend">${entries.map(([name, v], i) => `<div><span class="chart-dot" style="background:${colors[i % colors.length]}"></span><span>${esc(name)}</span><strong>${money(Math.round(v))}</strong><small>${Math.round((v / (total || 1)) * 100)}%</small></div>`).join("") || "<p>Ebben az időszakban még nincs befizetés.</p>"}</div></div></div>`;
}
function applyReports() {
  const from = $("#report-from").value,
    to = $("#report-to").value;
  if (!from || !to || from > to) {
    toast("Érvényes időszakot válassz.");
    return;
  }
  reportFrom = from;
  reportTo = to;
  reportsPage();
}
function billingPage() {
  const sub = state.subscription || {};
  $("#content").innerHTML =
    `<div class="entity-grid"><div class="card item"><span class="badge">ALAPCSOMAG</span><h2>4 000 Ft / hó</h2><p>A fizetendő végösszeg. 1 munkatárs, 1 naptár.</p><p>Tervezett indulási ajánlat: két hónap ingyen, kártyaadatok nélkül.</p><div class="notice">Előfizetés állapota: ${esc({ active: "Aktív", trialing: "Próbaidő", canceled: "Megszűnt", past_due: "Fizetésre vár" }[sub.status] || "Nincs aktív előfizetés")}</div><button class="primary" onclick="startSubscription()">Előfizetés bankkártyával</button><p class="hint">A gomb külön Stripe-fizetési oldalra visz. Automatikus terheléshez ott adsz jóváhagyást. A központi előfizetési kapcsolat külön szerverbeállítást igényel.</p></div><div class="card item"><h2>Számlázási adataid</h2><p>${esc(state.settings.billingName || "Még nincs megadva")}<br>${esc(state.settings.billingZip || "")} ${esc(state.settings.billingCity || "")}<br>${esc(state.settings.billingAddress || "")}<br>${esc(state.settings.billingTaxNumber || "")}</p><button onclick="view='settings';render()">Adatok szerkesztése</button><div class="notice">A több vállalkozást kiszolgáló regisztráció, a csomagkorlátozás és az átutalásos előfizetés központi kezelése még külön fejlesztés. A vendégek befizetései már a Befizetések menüben kezelhetők.</div></div></div>`;
}
async function startSubscription() {
  try {
    const r = await api("/api/subscription/checkout", "POST", {});
    location.assign(r.url);
  } catch (e) {
    toast(e.message);
  }
}
async function socialButtons(container, mode = "guest") {
  try {
    const p = await api("/api/auth/providers");
    if (!$(container)) return;
    $(container).innerHTML =
      ["google", "facebook"]
        .filter((k) => p[k])
        .map(
          (k) =>
            `<a class="social-button" href="/auth/${k}?mode=${mode}">${k === "google" ? "Google" : "Facebook"}-fiókkal ${mode === "admin" ? "belépés" : "belépés / regisztráció"}</a>`,
        )
        .join("") ||
      '<p class="hint">A közösségi belépés a szolgáltatói kapcsolat beállítása után érhető el.</p>';
  } catch {}
}
async function guestPage() {
  try {
    const g = await api("/api/guest");
    $("#app").innerHTML =
      `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><h1>Szia, ${esc(g.name)}!</h1><p>A fiókodhoz tartozó foglalások.</p><div class="row"><a href="/foglalas">Új foglalás</a><button onclick="guestLogout()">Kilépés</button></div>${g.bookings.map((b) => `<div class="card"><h3>${esc((b.serviceSnapshot || []).map((s) => s.name).join(" + "))}</h3><p>${new Date(b.start).toLocaleString("hu-HU")} · ${statusName[b.status]}</p><a href="/modositas?token=${b.token}">Foglalás kezelése →</a></div>`).join("") || '<div class="card">Még nincs a fiókodhoz kapcsolt foglalás.</div>'}</div>`;
  } catch {
    $("#app").innerHTML =
      `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div class="card"><h1>Vendégfiók</h1><p>Lépj be Google- vagy Facebook-fiókkal. Első belépéskor vendégfiókot hozunk létre. Foglalni belépés nélkül is lehet.</p><div id="guest-social" class="social-buttons"></div>${new URLSearchParams(location.search).has("authError") ? '<p class="error">A belépés nem sikerült. Próbáld újra.</p>' : ""}<a href="/foglalas">Foglalás belépés nélkül →</a></div></div>`;
    socialButtons("#guest-social");
  }
}
async function guestLogout() {
  await api("/api/guest/logout", "POST", {});
  guestPage();
}
function loginPage() {
  $("#app").innerHTML =
    `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div class="card"><h1>Szolgáltatói belépés</h1><p>A telepítéskor beállított jelszavaddal lépj be.</p><form id="login"><label>Jelszó</label><input type="password" name="password" required><div class="buttons"><button class="primary">Belépés</button></div><p id="login-error" class="error">${new URLSearchParams(location.search).has("authError") ? "A fiók nincs engedélyezve, vagy a közösségi belépés sikertelen." : ""}</p></form><div class="social-buttons" id="admin-social"></div><a href="/fiokom">Vendégként szeretnék belépni →</a></div></div>`;
  socialButtons("#admin-social", "admin");
  $("#login").onsubmit = async (e) => {
    e.preventDefault();
    try {
      await api(
        "/api/login",
        "POST",
        Object.fromEntries(new FormData(e.target)),
      );
      location.reload();
    } catch (err) {
      $("#login-error").textContent = err.message;
    }
  };
}
const originalPublicPage = publicPage;
publicPage = function () {
  originalPublicPage();
  const card = $("#public-form");
  if (card) {
    const link = document.createElement("p");
    link.className = "guest-login-link";
    link.innerHTML =
      '<a href="/fiokom">Google / Facebook-belépés és saját foglalásaim →</a>';
    card.prepend(link);
  }
  trackingConsent();
  if (card) {
    const privacy = document.createElement("button");
    privacy.className = "privacy-button";
    privacy.textContent = "Mérési hozzájárulás módosítása";
    privacy.onclick = resetConsent;
    card.append(privacy);
  }
};
if (manageToken) managePage();
else if (location.pathname.replace(/\/+$/, "") === "/fiokom") guestPage();
else refresh().catch(loginPage);
function trackingConsent() {
  if (!state?.settings) return;
  const s = state.settings;
  if (
    ![
      s.googleAnalyticsId,
      s.googleTagManagerId,
      s.googleAdsId,
      s.facebookPixelId,
    ].some(Boolean)
  )
    return;
  let choice;
  try {
    choice = JSON.parse(localStorage.getItem("raero-consent-v1") || "null");
  } catch {}
  if (choice) {
    enableTracking(choice);
    return;
  }
  const banner = document.createElement("div");
  banner.id = "consent-banner";
  banner.className = "consent-banner";
  banner.innerHTML =
    '<strong>Adatvédelmi beállítások</strong><p>A foglalás mérés nélkül is működik. Engedélyezed a látogatottságmérést és a marketingcélú követést?</p><div class="row"><button onclick="chooseConsent(false,false)">Csak szükséges</button><button onclick="chooseConsent(true,false)">Csak statisztika</button><button class="primary" onclick="chooseConsent(true,true)">Mindet engedélyezem</button></div>';
  document.body.append(banner);
}
function chooseConsent(analytics, marketing) {
  const choice = { analytics, marketing };
  localStorage.setItem("raero-consent-v1", JSON.stringify(choice));
  $("#consent-banner")?.remove();
  enableTracking(choice);
}
function enableTracking(choice) {
  const s = state.settings;
  if (window.raeroTrackingStarted) return;
  window.raeroTrackingStarted = true;
  const load = (url) => {
    const script = document.createElement("script");
    script.src = url;
    script.async = true;
    document.head.append(script);
  };
  window.dataLayer = window.dataLayer || [];
  window.gtag =
    window.gtag ||
    function () {
      dataLayer.push(arguments);
    };
  gtag("consent", "default", {
    analytics_storage: choice.analytics ? "granted" : "denied",
    ad_storage: choice.marketing ? "granted" : "denied",
    ad_user_data: choice.marketing ? "granted" : "denied",
    ad_personalization: choice.marketing ? "granted" : "denied",
  });
  const ids = [
    ...(choice.analytics && s.googleAnalyticsId ? [s.googleAnalyticsId] : []),
    ...(choice.marketing && s.googleAdsId ? [s.googleAdsId] : []),
  ];
  if (ids.length) {
    load(
      "https://www.googletagmanager.com/gtag/js?id=" +
        encodeURIComponent(ids[0]),
    );
    gtag("js", new Date());
    ids.forEach((id) => gtag("config", id));
  }
  if (choice.analytics && choice.marketing && s.googleTagManagerId) {
    dataLayer.push({ "gtm.start": Date.now(), event: "gtm.js" });
    load(
      "https://www.googletagmanager.com/gtm.js?id=" +
        encodeURIComponent(s.googleTagManagerId),
    );
  }
  if (choice.marketing && s.facebookPixelId) {
    window.fbq =
      window.fbq ||
      function () {
        (fbq.queue = fbq.queue || []).push(arguments);
      };
    fbq.loaded = true;
    fbq.version = "2.0";
    load("https://connect.facebook.net/en_US/fbevents.js");
    fbq("init", s.facebookPixelId);
    fbq("track", "PageView");
  }
}
function trackBooking() {
  let c;
  try {
    c = JSON.parse(localStorage.getItem("raero-consent-v1") || "null");
  } catch {}
  if (!c) return;
  const s = state.settings;
  if (c.analytics) {
    window.gtag?.("event", "booking_completed");
    window.dataLayer?.push({ event: "booking_completed" });
  }
  if (c.marketing) {
    if (s.googleAdsId && s.googleAdsLabel)
      window.gtag?.("event", "conversion", {
        send_to: s.googleAdsId + "/" + s.googleAdsLabel,
      });
    window.fbq?.("track", "Schedule");
  }
}

async function payGuest() {
  try {
    const r = await api("/api/manage/checkout", "POST", { token: manageToken });
    location.assign(r.url);
  } catch (e) {
    toast(e.message);
  }
}

function resetConsent() {
  localStorage.removeItem("raero-consent-v1");
  location.reload();
}

function invoiceReconcileForm(id) {
  showModal(
    `<h2>Számla eredményének egyeztetése</h2><p>Előbb ellenőrizd a számlázó felületén a foglalás/befizetés alapján, létrejött-e a számla. Ne indíts új számlát, ha a meglévő eredmény bizonytalan.</p><form id="reconcile-form"><label>Ellenőrzés eredménye</label><select name="decision" required><option value="">Válassz</option><option value="issued">A számla létrejött</option><option value="not_created">Biztosan nem készült számla</option></select><label>Számlaszám (ha elkészült)</label><input name="number"><label>Szolgáltatói azonosító (ha van)</label><input name="reference"><label class="check"><input name="confirmed" type="checkbox" required>Ellenőriztem a számlázóban az eredményt.</label><div id="form-error" class="error"></div><div class="buttons"><button type="button" onclick="$('#modal').close()">Mégse</button><button class="primary">Egyeztetés mentése</button></div></form>`,
  );
  $("#reconcile-form").onsubmit = async (e) => {
    e.preventDefault();
    try {
      const f = Object.fromEntries(new FormData(e.target));
      f.confirmed = true;
      await api("/api/invoices/" + id + "/reconcile", "POST", f);
      $("#modal").close();
      await refresh();
      toast("Egyeztetés rögzítve");
    } catch (err) {
      $("#form-error").textContent = err.message;
    }
  };
}
