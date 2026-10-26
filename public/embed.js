/* Ráérő booking web component. No iframe, no third-party cookies, no tracking. */
(() => {
  const defaultEndpoint = new URL(document.currentScript?.src || location.href)
    .origin;
  const escape = (s) =>
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
  class RaeroBooking extends HTMLElement {
    constructor() {
      super();
      this.attachShadow({ mode: "open" });
      this.request = 0;
    }
    async connectedCallback() {
      try {
        this.endpoint = new URL(
          this.getAttribute("endpoint") || defaultEndpoint,
        ).origin;
        if (!["https:", "http:"].includes(new URL(this.endpoint).protocol))
          throw Error("Érvénytelen végpont.");
        this.data = await this.api("/api/public");
        this.render();
      } catch (e) {
        this.shadowRoot.innerHTML = `<p role="alert">A foglaló nem tölthető be. Ellenőrizd, hogy a weboldal címe engedélyezve van-e a foglaló beállításaiban.</p>`;
      }
    }
    async api(path, body) {
      const r = await fetch(this.endpoint + path, {
        method: body ? "POST" : "GET",
        credentials: "omit",
        headers: {
          Accept: "application/json",
          ...(body ? { "Content-Type": "application/json" } : {}),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
      });
      const d = await r.json();
      if (!r.ok) throw Error(d.error || d.message || "Sikertelen kérés.");
      return d;
    }
    render() {
      this.shadowRoot.innerHTML = `<style>:host{display:block;color:#193842;font-family:Arial,sans-serif;max-width:720px;margin:auto}*{box-sizing:border-box}.card{background:white;border:1px solid #bdced8;border-radius:18px;padding:26px;box-shadow:0 5px 20px #17394c0a}h2{font-size:25px;margin:0 0 12px}p{color:#506772;line-height:1.7;font-size:13px}label{display:block;font-size:12px;font-weight:700;margin:16px 0 7px}input,select,button{font:inherit}input,select{width:100%;padding:12px;border:1px solid #b6cbd4;border-radius:8px;color:#193842;background:white}.service{display:flex;align-items:center;gap:10px;background:#eff5f7;padding:12px;border-radius:8px;font-size:13px}.service input{width:auto;accent-color:#126451}.service span{margin-left:auto;font-size:11px;font-weight:400;white-space:nowrap}button{padding:11px 14px;background:white;border:1px solid #b6cbd4;color:#284953;border-radius:8px;cursor:pointer}.slots{display:flex;gap:7px;flex-wrap:wrap}.primary{background:#126451;color:white;border-color:#126451}.actions{margin-top:22px;text-align:right}.error{color:#ab3131;margin-top:12px}.total{font-weight:700;color:#126451}.success{background:#e1f3e8;padding:22px;border-radius:12px}a{color:#126451}.fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:480px){.fields{grid-template-columns:1fr}.card{padding:18px}.service{font-size:12px;flex-wrap:wrap}}</style><div class="card"><h2>${escape(this.data.settings.name)}</h2><p>Válassz szolgáltatást és szabad időpontot. Regisztráció nélkül is foglalhatsz.</p><form><label>Szolgáltatások</label>${this.data.services.map((s) => `<label class="service"><input type="checkbox" name="services" value="${escape(s.id)}">${escape(s.name)}<span>${s.duration} perc · ${s.price.toLocaleString("hu-HU")} Ft</span></label>`).join("")}<p class="total"></p><div class="staff"><label>Munkatárs</label><select name="staff"></select></div><label>Nap</label><input name="date" type="date" required><label>Szabad időpontok (magyarországi idő)</label><div class="slots"><p>Először válassz szolgáltatást.</p></div><label>Név</label><input name="name" required maxlength="150"><div class="fields"><div><label>Telefonszám</label><input name="phone" type="tel" maxlength="40"></div><div><label>E-mail</label><input name="email" type="email" maxlength="200"></div></div><p>Telefonszám vagy e-mail megadása szükséges.</p><div class="error" role="alert"></div><div class="actions"><button class="primary" type="submit">Időpont lefoglalása</button></div></form></div>`;
      const now = new Intl.DateTimeFormat("sv-SE", {
        timeZone: "Europe/Budapest",
      }).format(new Date());
      const date = this.shadowRoot.querySelector("[name=date]");
      date.min = date.value = now;
      this.shadowRoot
        .querySelectorAll("[name=services]")
        .forEach((e) => (e.onchange = () => this.update()));
      date.onchange = () => this.slots();
      this.shadowRoot.querySelector("form").onsubmit = (e) => this.submit(e);
      this.update();
    }
    selected() {
      return [
        ...this.shadowRoot.querySelectorAll("[name=services]:checked"),
      ].map((e) => e.value);
    }
    update() {
      const ids = this.selected(),
        people = this.data.staff.filter((p) =>
          ids.every((id) =>
            this.data.services.find((s) => s.id === id).staff.includes(p.id),
          ),
        );
      const select = this.shadowRoot.querySelector("[name=staff]");
      select.innerHTML =
        (people.length > 1
          ? '<option value="any">Bárki megfelel</option>'
          : "") +
        people
          .map(
            (p) => `<option value="${escape(p.id)}">${escape(p.name)}</option>`,
          )
          .join("");
      this.shadowRoot.querySelector(".staff").hidden = people.length <= 1;
      select.onchange = () => this.slots();
      const services = this.data.services.filter((s) => ids.includes(s.id));
      this.shadowRoot.querySelector(".total").textContent = services.length
        ? `${services.reduce((n, s) => n + s.duration, 0)} perc · ${services.reduce((n, s) => n + s.price, 0).toLocaleString("hu-HU")} Ft`
        : "";
      this.slots();
    }
    async slots() {
      const ticket = ++this.request;
      this.selectedSlot = null;
      const box = this.shadowRoot.querySelector(".slots"),
        ids = this.selected();
      if (!ids.length) {
        box.innerHTML = "<p>Először válassz szolgáltatást.</p>";
        return;
      }
      box.innerHTML = "<p>Szabad időpontok keresése…</p>";
      try {
        const slots = await this.api(
          "/api/slots?" +
            new URLSearchParams({
              date: this.shadowRoot.querySelector("[name=date]").value,
              services: ids.join(","),
              staff: this.shadowRoot.querySelector("[name=staff]").value,
            }),
        );
        if (ticket !== this.request) return;
        box.innerHTML =
          slots
            .map(
              (s, i) =>
                `<button type="button" data-slot="${i}">${s.time}</button>`,
            )
            .join("") || "<p>Nincs szabad időpont. Válassz másik napot.</p>";
        box.querySelectorAll("button").forEach(
          (b) =>
            (b.onclick = () => {
              box
                .querySelectorAll("button")
                .forEach((x) => x.classList.remove("primary"));
              b.classList.add("primary");
              this.selectedSlot = slots[Number(b.dataset.slot)];
            }),
        );
      } catch (e) {
        box.textContent = e.message;
      }
    }
    async submit(e) {
      e.preventDefault();
      const error = this.shadowRoot.querySelector(".error"),
        button = this.shadowRoot.querySelector("[type=submit]");
      error.textContent = "";
      try {
        if (!this.selectedSlot) throw Error("Válassz szabad időpontot.");
        button.disabled = true;
        const f = new FormData(e.target),
          b = await this.api("/api/bookings", {
            name: f.get("name"),
            phone: f.get("phone"),
            email: f.get("email"),
            services: this.selected(),
            staff: this.selectedSlot.staff,
            start: this.selectedSlot.start,
            public: true,
          });
        const manage = this.endpoint + "/modositas?token=" + b.token;
        this.shadowRoot.querySelector("form").innerHTML =
          `<div class="success"><h2>${b.status === "pending" ? "Foglalási kérésed megérkezett!" : "Időpontod megerősítve!"}</h2><p>Mentsd el a hivatkozást, ezen később módosíthatsz vagy lemondhatsz.</p><a target="_blank" rel="noopener" href="${escape(manage)}">Foglalás kezelése →</a></div>`;
        this.dispatchEvent(
          new CustomEvent("raero:booked", {
            bubbles: true,
            composed: true,
            detail: { status: b.status },
          }),
        );
      } catch (err) {
        error.textContent = err.message;
        button.disabled = false;
      }
    }
  }
  if (!customElements.get("raero-booking"))
    customElements.define("raero-booking", RaeroBooking);
})();
