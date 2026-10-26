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
const api = async (url, method = "GET", body) => {
  if (method !== "GET" && !csrfToken) {
    const response = await fetch("/api/csrf", {
      headers: { Accept: "application/json" },
    });
    const csrf = await response.json();
    csrfToken = csrf.token;
  }
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
  return state.services
    .filter((s) => !s.archived)
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
      trackBooking();
      $("#public-form").innerHTML =
        `<div class="success"><h2>${b.status === "pending" ? "Foglalási kérésed megérkezett!" : "Az időpontod megerősítve!"}</h2><p>${b.status === "pending" ? "A szalon jóváhagyására vár." : "Várunk szeretettel!"}</p><p>Mentsd el a módosítási hivatkozásod. A szalon beállításai alapján e-mailes értesítőt is kaphatsz:</p><a href="/modositas?token=${b.token}">Foglalásom kezelése →</a></div>`;
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
    `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div style="text-align:center"><span class="badge">ONLINE IDŐPONTFOGLALÁS</span><h1 style="margin-top:20px">${esc(state.settings.name)}</h1><p>Egy kis idő, ami csak rólad szól.</p></div><div class="card" id="public-form"><h2>Foglalj időpontot</h2><p>Válassz szolgáltatást és időpontot. Regisztráció nem szükséges.</p><form id="booking-form">${bookingFields()}<div class="notice">Az időpontok magyarországi idő szerint jelennek meg. Foglalásod a visszaigazoló hivatkozással később kezelhető.</div><div class="buttons"><button class="primary">Időpont lefoglalása →</button></div></form></div></div>`;
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
      `<div class="public"><div class="brand"><span>◷</span>ráérő.</div><div class="card"><h1>Az időpontod</h1><h3>${esc(b.name)}</h3><p>${b.services.map((id) => esc(services.find((s) => s.id === id)?.name)).join(" + ")}</p><div class="notice">${statusName[b.status]}</div><p>${new Date(b.start).toLocaleString("hu-HU")}</p>${!["cancelled", "rejected"].includes(b.status) ? `<form id="manage-form"><label>Új időpont</label><input name="start" type="datetime-local" value="${localdt(new Date(b.start))}" required><div class="buttons"><button type="button" onclick="cancelBooking()">Foglalás lemondása</button><button class="primary">Időpont módosítása</button></div><div id="form-error" class="error"></div></form>` : ""}${paymentReady && balance > 0 && !["cancelled", "rejected"].includes(b.status) ? `<button class="primary" style="margin-top:20px" onclick="payGuest()">Bankkártyás fizetés · ${money(balance)}</button>` : ""}<p style="margin-top:20px;font-size:12px">A változás megjelenik a szolgáltató naptárában. A beállításai szerint a szalon és a vendég is értesítést kap.</p></div></div>`;
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
async function logout() {
  await api("/api/logout", "POST", {});
  location.reload();
}
