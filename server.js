const http = require("node:http"),
  fs = require("node:fs"),
  path = require("node:path"),
  crypto = require("node:crypto");
const { DatabaseSync } = require("node:sqlite");
const dataDir = process.env.DATA_DIR || path.join(__dirname, "data");
fs.mkdirSync(dataDir, { recursive: true });
const db = new DatabaseSync(path.join(dataDir, "app.db"));
db.exec(
  "CREATE TABLE IF NOT EXISTS state(id INTEGER PRIMARY KEY,data TEXT NOT NULL)",
);
const initial = {
  settings: {
    name: "Harmónia Szalon",
    profession: "Szépségszalon",
    confirmation: true,
    open: 8,
    close: 20,
    reminder: 24,
    channel: "email",
  },
  staff: [
    { id: "anna", name: "Kovács Anna", role: "Fodrász" },
    { id: "eszter", name: "Nagy Eszter", role: "Masszőr" },
  ],
  services: [
    {
      id: "cut",
      name: "Női hajvágás",
      duration: 45,
      price: 8500,
      staff: ["anna"],
    },
    {
      id: "color",
      name: "Hajfestés",
      duration: 95,
      price: 18000,
      staff: ["anna"],
      phases: [30, 35, 30],
    },
    {
      id: "massage",
      name: "Frissítő masszázs",
      duration: 60,
      price: 14000,
      staff: ["eszter"],
    },
  ],
  clients: [],
  bookings: [],
};
if (!db.prepare("SELECT id FROM state WHERE id=1").get())
  db.prepare("INSERT INTO state VALUES(1,?)").run(JSON.stringify(initial));
const passwordPath = path.join(dataDir, "admin-password");
if (!fs.existsSync(passwordPath))
  fs.writeFileSync(passwordPath, crypto.randomBytes(18).toString("base64url"), {
    mode: 0o600,
  });
const password =
  process.env.ADMIN_PASSWORD || fs.readFileSync(passwordPath, "utf8").trim();
const sessions = new Map();
const read = () =>
  JSON.parse(db.prepare("SELECT data FROM state WHERE id=1").get().data);
const save = (s) =>
  db.prepare("UPDATE state SET data=? WHERE id=1").run(JSON.stringify(s));
function occupied(b, s) {
  let start = new Date(b.start).getTime(),
    ranges = [];
  for (const id of b.services) {
    const v = (b.serviceSnapshot || s.services).find((x) => x.id === id);
    if (!v) throw Error("Ismeretlen szolgáltatás");
    if (v.phases) {
      ranges.push([start, start + v.phases[0] * 60000]);
      ranges.push([
        start + (v.phases[0] + v.phases[1]) * 60000,
        start + v.duration * 60000,
      ]);
    } else ranges.push([start, start + v.duration * 60000]);
    start += v.duration * 60000;
  }
  return ranges;
}
function validate(b, s, ignore) {
  if (!b.name?.trim() || (!b.phone?.trim() && !b.email?.trim()))
    throw Error("Név és legalább egy elérhetőség szükséges.");
  if (
    !Array.isArray(b.services) ||
    !b.services.length ||
    !s.staff.some((x) => x.id === b.staff)
  )
    throw Error("Válassz szolgáltatást és munkatársat.");
  if (!Number.isFinite(new Date(b.start).getTime()))
    throw Error("Érvénytelen időpont.");
  if (b.services.length > 20) throw Error("Túl sok szolgáltatás");
  if (b.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(b.email))
    throw Error("Érvénytelen e-mail-cím");
  if (
    b.services.some(
      (id) => !s.services.find((x) => x.id === id)?.staff.includes(b.staff),
    )
  )
    throw Error("A munkatárs nem végzi az összes kiválasztott szolgáltatást.");
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat("en-GB", {
      timeZone: "Europe/Budapest",
      hour: "numeric",
      minute: "numeric",
      hourCycle: "h23",
    })
      .formatToParts(new Date(b.start))
      .map((x) => [x.type, x.value]),
  );
  const mins = Number(parts.hour) * 60 + Number(parts.minute);
  const duration = b.services.reduce(
    (n, id) => n + s.services.find((x) => x.id === id).duration,
    0,
  );
  if (
    mins < (s.settings.open ?? 8) * 60 ||
    mins + duration > (s.settings.close ?? 20) * 60
  )
    throw Error("Az időpont nem fér a nyitvatartásba.");
  if (new Date(b.start).getTime() < Date.now() - 60000)
    throw Error("Múltbeli időpontra nem lehet foglalni.");
  const ranges = occupied(b, s);
  for (const old of s.bookings.filter(
    (x) =>
      x.id !== ignore &&
      x.staff === b.staff &&
      !["cancelled", "rejected"].includes(x.status),
  ))
    if (
      occupied(old, s).some((a) =>
        ranges.some((c) => a[0] < c[1] && c[0] < a[1]),
      )
    )
      throw Error("Ez az időpont már foglalt. Válassz másikat.");
}
const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, "http://localhost");
  const json = (code, value) => {
    res.setHeader("Cache-Control", "no-store");
    res.setHeader("Referrer-Policy", "no-referrer");
    res.writeHead(code, { "Content-Type": "application/json; charset=utf-8" });
    res.end(JSON.stringify(value));
  };
  try {
    let body = {};
    if (["POST", "PUT", "PATCH"].includes(req.method)) {
      let raw = "";
      for await (const chunk of req) {
        raw += chunk;
        if (raw.length > 1000000) throw Error("Túl nagy kérés");
      }
      body = JSON.parse(raw || "{}");
    }
    const s = read();
    const sid = (req.headers.cookie || "")
      .split(";")
      .map((x) => x.trim())
      .find((x) => x.startsWith("session="))
      ?.slice(8);
    const authed = sessions.has(sid) && sessions.get(sid) > Date.now();
    if (
      url.pathname.startsWith("/api/") &&
      req.headers.origin &&
      req.headers.origin !== `http://${req.headers.host}` &&
      req.headers.origin !== `https://${req.headers.host}`
    )
      return json(403, { error: "Tiltott kérésforrás" });
    if (url.pathname === "/api/login" && req.method === "POST") {
      const a = Buffer.from(String(body.password || "")),
        b = Buffer.from(password);
      if (a.length !== b.length || !crypto.timingSafeEqual(a, b))
        return json(401, { error: "Hibás jelszó" });
      const token = crypto.randomBytes(32).toString("hex");
      sessions.set(token, Date.now() + 86400000);
      res.setHeader(
        "Set-Cookie",
        `session=${token}; HttpOnly; SameSite=Strict; Path=/; Max-Age=86400${req.headers["x-forwarded-proto"] === "https" ? "; Secure" : ""}`,
      );
      return json(200, { ok: true });
    }
    if (url.pathname === "/api/public" && req.method === "GET")
      return json(200, {
        settings: { name: s.settings.name },
        services: s.services,
        staff: s.staff,
      });
    if (url.pathname === "/api/slots" && req.method === "GET") {
      const date = url.searchParams.get("date"),
        ids = (url.searchParams.get("services") || "")
          .split(",")
          .filter(Boolean),
        staff = url.searchParams.get("staff");
      if (!/^\d{4}-\d{2}-\d{2}$/.test(date || "") || !ids.length)
        return json(200, []);
      const slots = [];
      for (
        let minute = (s.settings.open ?? 8) * 60;
        minute < (s.settings.close ?? 20) * 60;
        minute += 15
      ) {
        const wall = `${date}T${String(Math.floor(minute / 60)).padStart(2, "0")}:${String(minute % 60).padStart(2, "0")}:00`;
        let d = new Date(wall + "Z");
        const zone = new Intl.DateTimeFormat("en", {
          timeZone: "Europe/Budapest",
          timeZoneName: "shortOffset",
        })
          .formatToParts(d)
          .find((p) => p.type === "timeZoneName").value;
        const offset = Number(zone.replace("GMT", ""));
        d = new Date(d.getTime() - offset * 3600000);
        for (const p of s.staff.filter(
          (p) => !staff || staff === "any" || p.id === staff,
        )) {
          try {
            validate(
              {
                name: "slot",
                phone: "slot",
                start: d.toISOString(),
                staff: p.id,
                services: ids,
              },
              s,
            );
            slots.push({
              start: d.toISOString(),
              staff: p.id,
              time: wall.slice(11, 16),
            });
            break;
          } catch {}
        }
      }
      return json(200, slots);
    }
    const publicRoute =
      ["/api/public", "/api/manage", "/api/slots"].includes(url.pathname) ||
      (url.pathname === "/api/bookings" && req.method === "POST");
    if (url.pathname.startsWith("/api/") && !publicRoute && !authed)
      return json(401, { error: "Bejelentkezés szükséges" });
    if (url.pathname === "/api/state" && req.method === "GET")
      return json(200, s);
    if (url.pathname === "/api/bookings" && req.method === "POST") {
      const clean = {
        name: String(body.name || "").slice(0, 150),
        phone: String(body.phone || "").slice(0, 40),
        email: String(body.email || "").slice(0, 200),
        start: body.start,
        staff: body.staff,
        services: body.services,
        public: !authed || body.public === true,
      };
      validate(clean, s);
      const b = {
        ...clean,
        serviceSnapshot: clean.services.map((id) =>
          s.services.find((v) => v.id === id),
        ),
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
        id: crypto.randomUUID(),
        token: crypto.randomBytes(24).toString("hex"),
        status:
          clean.public &&
          s.settings.confirmation &&
          !clean.services.every(
            (id) => s.services.find((v) => v.id === id).automatic,
          )
            ? "pending"
            : "confirmed",
      };
      s.bookings.push(b);
      if (
        !s.clients.some((c) =>
          clean.phone ? c.phone === clean.phone : c.email === clean.email,
        )
      )
        s.clients.push({
          id: crypto.randomUUID(),
          name: clean.name,
          phone: clean.phone,
          email: clean.email,
        });
      save(s);
      return json(201, b);
    }
    if (url.pathname.startsWith("/api/bookings/") && req.method === "PATCH") {
      const b = s.bookings.find((x) => x.id === url.pathname.split("/").pop());
      if (!b) return json(404, { error: "Nincs ilyen foglalás" });
      if (
        body.status &&
        !["confirmed", "pending", "cancelled", "rejected"].includes(body.status)
      )
        throw Error("Érvénytelen állapot");
      const next = {
        ...b,
        status: body.status || b.status,
        start: body.start || b.start,
        updatedAt: new Date().toISOString(),
      };
      if (!["cancelled", "rejected"].includes(next.status))
        validate(next, s, b.id);
      Object.assign(b, next);
      save(s);
      return json(200, b);
    }
    if (url.pathname === "/api/manage" && req.method === "GET") {
      const b = s.bookings.find(
        (x) => x.token === url.searchParams.get("token"),
      );
      return b
        ? json(200, { booking: b, services: s.services, staff: s.staff })
        : json(404, { error: "Érvénytelen hivatkozás" });
    }
    if (url.pathname === "/api/manage" && req.method === "PATCH") {
      const b = s.bookings.find((x) => x.token === body.token);
      if (!b) return json(404, { error: "Érvénytelen hivatkozás" });
      if (["cancelled", "rejected"].includes(b.status))
        throw Error("Ez a foglalás már nem módosítható.");
      b.updatedAt = new Date().toISOString();
      if (body.cancel) b.status = "cancelled";
      else {
        const next = { ...b, start: body.start };
        validate(next, s, b.id);
        b.start = next.start;
        b.status =
          s.settings.confirmation &&
          !b.services.every(
            (id) => s.services.find((v) => v.id === id).automatic,
          )
            ? "pending"
            : "confirmed";
      }
      save(s);
      return json(200, b);
    }
    if (url.pathname === "/api/config" && req.method === "PUT") {
      if (body.settings) {
        const v = body.settings;
        if (
          !v.name?.trim() ||
          !Number.isFinite(Number(v.open ?? 8)) ||
          !Number.isFinite(Number(v.close ?? 20)) ||
          Number(v.open ?? 8) >= Number(v.close ?? 20) ||
          Number(v.open ?? 8) < 0 ||
          Number(v.close ?? 20) > 24
        )
          throw Error("Ellenőrizd a nevet és a nyitvatartást.");
      }
      if (
        body.staff &&
        (!Array.isArray(body.staff) ||
          body.staff.some((p) => !p.id || !p.name?.trim()))
      )
        throw Error("Érvénytelen munkatárs");
      if (
        body.services &&
        (!Array.isArray(body.services) ||
          body.services.some(
            (v) =>
              !v.id ||
              !v.name?.trim() ||
              !Number.isFinite(v.duration) ||
              v.duration < 5 ||
              !Number.isFinite(v.price) ||
              v.price < 0 ||
              !Array.isArray(v.staff) ||
              !v.staff.length ||
              v.staff.some(
                (id) => !(body.staff || s.staff).some((p) => p.id === id),
              ) ||
              (v.phases &&
                (!Array.isArray(v.phases) ||
                  v.phases.length !== 3 ||
                  v.phases.some((n) => !Number.isFinite(n) || n < 0) ||
                  v.phases.reduce((a, b) => a + b, 0) !== v.duration)),
          ))
      )
        throw Error("Érvénytelen szolgáltatás vagy szakaszidők");
      for (const k of ["settings", "staff", "services", "clients"])
        if (body[k]) s[k] = body[k];
      save(s);
      return json(200, { ok: true });
    }
    if (url.pathname.startsWith("/api/"))
      return json(404, { error: "Ismeretlen végpont" });
    const file =
      url.pathname === "/app.js"
        ? "app.js"
        : url.pathname === "/style.css"
          ? "style.css"
          : "index.html";
    res.writeHead(200, {
      "Content-Type": file.endsWith(".js")
        ? "text/javascript"
        : file.endsWith(".css")
          ? "text/css"
          : "text/html; charset=utf-8",
    });
    fs.createReadStream(path.join(__dirname, "public", file)).pipe(res);
  } catch (e) {
    json(400, { error: e.message });
  }
});
if (require.main === module) {
  console.log(
    "Szolgáltatói jelszó helye: data/admin-password (vagy ADMIN_PASSWORD környezeti változó).",
  );
  server.listen(process.env.PORT || 3000, "0.0.0.0", () =>
    console.log("Időpontfoglaló elindult, port: " + (process.env.PORT || 3000)),
  );
}
module.exports = { occupied, validate, server };
