const { test, before, after } = require("node:test");
const assert = require("node:assert/strict");
const { mkdtempSync, rmSync } = require("node:fs");
const { tmpdir } = require("node:os");
const { join } = require("node:path");
const data = mkdtempSync(join(tmpdir(), "raero-test-"));
process.env.DATA_DIR = data;
process.env.ADMIN_PASSWORD = "test-password-only";
const { server, validate } = require("../server");
let base, cookie, booking;
const start = "2035-03-12T09:00:00.000Z";
async function request(url, method = "GET", body, auth = false) {
  const r = await fetch(base + url, {
    method,
    headers: {
      "Content-Type": "application/json",
      ...(auth ? { cookie } : {}),
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
  return { status: r.status, body: await r.json(), headers: r.headers };
}
before(async () => {
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  base = "http://127.0.0.1:" + server.address().port;
  const r = await request("/api/login", "POST", {
    password: "test-password-only",
  });
  cookie = r.headers.get("set-cookie").split(";")[0];
});
after(async () => {
  await new Promise((r) => server.close(r));
  rmSync(data, { recursive: true, force: true });
});
test("private customer data requires login; public catalog excludes customers", async () => {
  assert.equal((await request("/api/state")).status, 401);
  const r = await request("/api/public");
  assert.equal(r.status, 200);
  assert.equal(r.body.clients, undefined);
  assert.equal(r.body.bookings, undefined);
});
test("guest booking is pending even when caller requests confirmed", async () => {
  const r = await request("/api/bookings", "POST", {
    name: "Teszt Vendég",
    phone: "+36301234567",
    start,
    staff: "anna",
    services: ["cut"],
    status: "confirmed",
    public: false,
  });
  assert.equal(r.status, 201);
  assert.equal(r.body.status, "pending");
  booking = r.body;
  assert.equal(booking.token.length, 48);
});
test("pending booking reserves time and blocks duplicate", async () => {
  const r = await request("/api/bookings", "POST", {
    name: "Másik Vendég",
    phone: "123",
    start,
    staff: "anna",
    services: ["cut"],
  });
  assert.equal(r.status, 400);
  assert.match(r.body.error, /foglalt/);
});
test("available slot list excludes overlapping bookings", async () => {
  const r = await request("/api/slots?date=2035-03-12&services=cut&staff=anna");
  assert.equal(r.status, 200);
  assert.ok(r.body.length > 0);
  assert.ok(!r.body.some((s) => s.start === start));
});
test("admin approves booking and customer persists in client list", async () => {
  assert.equal(
    (
      await request(
        "/api/bookings/" + booking.id,
        "PATCH",
        { status: "confirmed" },
        true,
      )
    ).body.status,
    "confirmed",
  );
  const r = await request("/api/state", "GET", null, true);
  assert.equal(r.body.clients.length, 1);
  assert.equal(r.body.clients[0].phone, "+36301234567");
});
test("private link allows rescheduling, cancellation and rejects invalid token", async () => {
  assert.equal((await request("/api/manage?token=invalid")).status, 404);
  const moved = await request("/api/manage", "PATCH", {
    token: booking.token,
    start: "2035-03-12T11:00:00.000Z",
  });
  assert.equal(moved.body.status, "pending");
  assert.equal(
    (
      await request("/api/manage", "PATCH", {
        token: booking.token,
        cancel: true,
      })
    ).body.status,
    "cancelled",
  );
  assert.equal(
    (await request("/api/manage", "PATCH", { token: booking.token, start }))
      .status,
    400,
  );
});
test("processing time permits parallel work but blocks active phases", () => {
  const s = {
    settings: { open: 8, close: 20 },
    staff: [{ id: "a" }],
    services: [
      { id: "color", duration: 95, phases: [30, 35, 30], staff: ["a"] },
      { id: "short", duration: 20, staff: ["a"] },
    ],
    bookings: [
      {
        id: "old",
        name: "A",
        phone: "1",
        staff: "a",
        services: ["color"],
        start,
        status: "confirmed",
      },
    ],
  };
  const b = {
    name: "B",
    phone: "2",
    staff: "a",
    services: ["short"],
    start: "2035-03-12T09:35:00.000Z",
  };
  assert.doesNotThrow(() => validate(b, s));
  assert.throws(
    () => validate({ ...b, start: "2035-03-12T10:00:00.000Z" }, s),
    /foglalt/,
  );
});
test("invalid durations and inconsistent phases are rejected", async () => {
  const r = await request(
    "/api/config",
    "PUT",
    {
      services: [
        {
          id: "bad",
          name: "Bad",
          duration: 30,
          price: 1,
          staff: ["anna"],
          phases: [30, 35, 30],
        },
      ],
    },
    true,
  );
  assert.equal(r.status, 400);
});
test("automatic service confirmation and multi-service booking work", async () => {
  const s = (await request("/api/state", "GET", null, true)).body;
  await request(
    "/api/config",
    "PUT",
    { services: s.services.map((v) => ({ ...v, automatic: true })) },
    true,
  );
  const r = await request("/api/bookings", "POST", {
    name: "Auto",
    email: "test@example.com",
    start: "2035-03-13T08:00:00.000Z",
    staff: "anna",
    services: ["cut", "color"],
  });
  assert.equal(r.status, 201);
  assert.equal(r.body.status, "confirmed");
});
