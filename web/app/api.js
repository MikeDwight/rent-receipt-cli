/* ============================================================
   Rent Receipt — API client + French formatters
   Replaces data.js from the prototype. No seed data here.
   ============================================================ */
(function () {
  "use strict";

  // ---- Formatters (fr-FR) -------------------------------------------------
  const eur  = new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR", minimumFractionDigits: 2 });
  const eur0 = new Intl.NumberFormat("fr-FR", { style: "currency", currency: "EUR", maximumFractionDigits: 0 });

  function fmtEUR(cents)  { return eur.format((cents || 0) / 100); }
  function fmtEUR0(cents) { return eur0.format((cents || 0) / 100); }

  const MONTHS_FR = ["janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre"];

  function fmtPeriod(period) {
    if (!period) return "";
    const [y, m] = period.split("-").map(Number);
    const name = MONTHS_FR[m - 1] || "";
    return name.charAt(0).toUpperCase() + name.slice(1) + " " + y;
  }
  function fmtPeriodShort(period) {
    const [y, m] = period.split("-").map(Number);
    const name = MONTHS_FR[m - 1] || "";
    return name.slice(0, 4) + ". " + y;
  }
  function fmtDate(iso) {
    if (!iso) return "—";
    const [y, m, d] = iso.split(/[-T ]/g).map(Number);
    return d + " " + (MONTHS_FR[m - 1] || "") + " " + y;
  }
  function fmtDateShort(iso) {
    if (!iso) return "—";
    const [y, m, d] = iso.split(/[-T ]/g).map(Number);
    return String(d).padStart(2, "0") + "/" + String(m).padStart(2, "0") + "/" + y;
  }
  function initials(name) {
    return (name || "").split(/\s+/).filter(Boolean).slice(0, 2).map(s => s[0].toUpperCase()).join("");
  }
  function addMonth(period, delta) {
    let [y, m] = period.split("-").map(Number);
    m += delta;
    while (m > 12) { m -= 12; y += 1; }
    while (m <  1) { m += 12; y -= 1; }
    return y + "-" + String(m).padStart(2, "0");
  }
  function basename(path) {
    if (!path) return "";
    return path.split("/").pop();
  }

  // Current period (Europe/Paris approximate — good enough for UI default)
  const now = new Date();
  const CURRENT_PERIOD = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");

  // ---- API fetch helper ---------------------------------------------------
  async function apiFetch(path, opts) {
    const res = await fetch(path, opts || {});
    if (!res.ok) {
      let msg = "HTTP " + res.status;
      try { const j = await res.json(); msg = j.error || msg; } catch (_) {}
      throw new Error(msg);
    }
    return res.json();
  }

  // ---- Expose globals ------------------------------------------------------
  window.RENT = {
    fmtEUR, fmtEUR0, fmtPeriod, fmtPeriodShort, fmtDate, fmtDateShort,
    initials, addMonth, basename, MONTHS_FR, CURRENT_PERIOD,
    api: apiFetch,
    owner: { full_name: "Bailleur", email: "", address: "", city: "" },
  };
})();
