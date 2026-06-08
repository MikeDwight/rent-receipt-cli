/* ============================================================
   App shell — API-wired (replaces maquette data.js mutations)
   ============================================================ */

const { useState, useEffect, useRef, useMemo, useCallback } = React;

const TITLES = {
  dashboard:  "Tableau de bord",
  quittances: "Quittances",
  paiements:  "Paiements",
  locataires: "Locataires",
  biens:      "Biens",
  reglages:   "Réglages",
};

function App() {
  const persisted = (() => { try { return JSON.parse(localStorage.getItem("rent_ui") || "{}"); } catch (e) { return {}; } })();

  const [theme,  setTheme]  = useState(persisted.theme || "releve");
  const [route,  setRoute]  = useState(persisted.route || "dashboard");
  const [period, setPeriod] = useState(RENT.CURRENT_PERIOD);

  const [owner,      setOwner]      = useState(RENT.owner);
  const [tenants,    setTenants]    = useState([]);
  const [properties, setProperties] = useState([]);
  const [payments,   setPayments]   = useState([]);
  const [loading,    setLoading]    = useState(true);

  const [drawer,     setDrawer]     = useState(null);
  const [modal,      setModal]      = useState(null);
  const [processCtx, setProcessCtx] = useState(null);
  const [toasts,     setToasts]     = useState([]);

  useEffect(() => { document.documentElement.setAttribute("data-theme", theme); }, [theme]);
  useEffect(() => {
    try { localStorage.setItem("rent_ui", JSON.stringify({ theme, route })); } catch (e) {}
  }, [theme, route]);

  // ---- Initial data load --------------------------------------------------
  useEffect(() => {
    Promise.all([
      fetch("/api/tenants").then(r => r.json()),
      fetch("/api/properties").then(r => r.json()),
      fetch("/api/owner").then(r => r.json()),
    ]).then(([tData, pData, oData]) => {
      setTenants(Array.isArray(tData) ? tData : []);
      setProperties(Array.isArray(pData) ? pData : []);
      if (Array.isArray(oData) && oData.length > 0) {
        const o = oData[0];
        setOwner(o);
        RENT.owner = o;
      }
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  // ---- Load payments when period changes ----------------------------------
  useEffect(() => {
    fetch(`/api/payments?period=${period}`)
      .then(r => r.json())
      .then(data => setPayments(Array.isArray(data) ? data : []))
      .catch(() => {});
  }, [period]);

  // ---- Toast --------------------------------------------------------------
  const toast = useCallback((text, opts = {}) => {
    const id = Math.random().toString(36).slice(2);
    setToasts(t => [...t, { id, text, kind: opts.kind || "ok", icon: opts.icon || "check" }]);
    setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), 2600);
  }, []);

  // ---- Derived helpers (same logic as maquette) ---------------------------
  const tenantById     = useCallback(id => tenants.find(t => t.id === id), [tenants]);
  const propertyById   = useCallback(id => properties.find(p => p.id === id), [properties]);
  const paymentForTenant = useCallback(
    (tid, per) => payments.find(p => p.tenant_id === tid && p.period === per),
    [payments]
  );
  const paymentsForPeriod = useCallback(
    (per) => payments.filter(p => p.period === per).sort((a, b) => a.tenant_id - b.tenant_id),
    [payments]
  );

  const rowsForPeriod = useCallback((per) => {
    return tenants.map(t => ({
      tenant:   t,
      property: t.property_id ? propertyById(t.property_id) : null,
      payment:  paymentForTenant(t.id, per) || null,
    })).filter(r => r.property);
  }, [tenants, propertyById, paymentForTenant]);

  const metrics = useCallback((per) => {
    const rows = rowsForPeriod(per);
    let expected = 0, collected = 0, sent = 0, archived = 0, todoCount = 0, maxLot = 1;
    rows.forEach(r => {
      const lot = r.property.rent_amount + r.property.charges_amount;
      expected += lot;
      maxLot = Math.max(maxLot, lot);
      if (r.payment) collected += r.payment.rent_amount + r.payment.charges_amount;
      const rc = r.payment && r.payment.receipt;
      if (rc && rc.sent_at)    sent++;
      if (rc && rc.archived_at) archived++;
      const k = receiptStatus(r.payment).kind;
      if (k === "todo" || k === "warn" || k === "err") todoCount++;
    });
    return { lots: rows.length, expected, collected, sent, archived, todoCount, maxLot };
  }, [rowsForPeriod]);

  const recentActivity = useCallback((per) => {
    const tone = {
      olive: { bg: "var(--olive-soft)", color: "oklch(0.45 0.10 140)" },
      terra: { bg: "var(--terra-soft)", color: "var(--terra-deep)" },
      clay:  { bg: "var(--clay-soft)",  color: "var(--clay)" },
      slate: { bg: "var(--slate-soft)", color: "oklch(0.46 0.07 245)" },
    };
    const ev = [];
    rowsForPeriod(per).forEach(r => {
      const t = r.tenant, p = r.payment;
      if (!p) return;
      const rc = p.receipt;
      if (rc && rc.archive_error)
        ev.push({ icon: "alert", text: `Échec d'archivage — ${t.full_name}`, when: RENT.fmtDate(rc.sent_at || p.paid_at), key: rc.sent_at || p.paid_at, ...tone.clay });
      else if (rc && rc.archived_at)
        ev.push({ icon: "cloud", text: `Quittance archivée — ${t.full_name}`, when: RENT.fmtDate(rc.archived_at), key: rc.archived_at, ...tone.slate });
      if (rc && rc.sent_at && !rc.archive_error)
        ev.push({ icon: "mail", text: `Quittance envoyée — ${t.full_name}`, when: RENT.fmtDate(rc.sent_at), key: rc.sent_at, ...tone.olive });
      if (!rc)
        ev.push({ icon: "euro", text: `Loyer encaissé — ${t.full_name}`, when: RENT.fmtDate(p.paid_at), key: p.paid_at, ...tone.terra });
    });
    ev.sort((a, b) => (b.key || "").localeCompare(a.key || ""));
    return ev.slice(0, 5);
  }, [rowsForPeriod]);

  // ---- Refresh helpers ----------------------------------------------------
  const refreshPayments = useCallback(() => {
    return fetch(`/api/payments?period=${period}`)
      .then(r => r.json())
      .then(data => setPayments(Array.isArray(data) ? data : []));
  }, [period]);

  const refreshTenants = useCallback(() =>
    fetch("/api/tenants").then(r => r.json()).then(data => setTenants(Array.isArray(data) ? data : [])),
  []);

  const refreshProperties = useCallback(() =>
    fetch("/api/properties").then(r => r.json()).then(data => setProperties(Array.isArray(data) ? data : [])),
  []);

  // ---- Store (mutations call API, then refresh) ---------------------------
  const store = {
    period, setPeriod, theme, setTheme, route, setRoute,
    tenants, properties, owner, loading,
    tenantById, propertyById, paymentForTenant, paymentsForPeriod, rowsForPeriod, metrics, recentActivity,
    toast,

    openTenant:   (t)   => setDrawer({ type: "tenant",   item: t }),
    openProperty: (p)   => setDrawer({ type: "property", item: p }),
    openPayment:  (row) => setModal({ type: "payment", row }),
    closeDrawer:  ()    => setDrawer(null),
    closeModal:   ()    => setModal(null),

    openProcess: (tenant, property, mode) => {
      const exists = !!paymentForTenant(tenant.id, period);
      setProcessCtx({ tenant, property, period, mode, paymentExists: exists });
    },

    completeProcess: () => refreshPayments(),

    processAll: async () => {
      const rows = rowsForPeriod(period).filter(r =>
        ["todo", "warn", "err"].includes(receiptStatus(r.payment).kind)
      );
      let done = 0;
      for (const r of rows) {
        const rearchive = receiptStatus(r.payment).kind === "warn";
        try {
          await fetch("/api/process", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              tenant_id:   r.tenant.id,
              property_id: r.property.id,
              period,
              paid_at:     new Date().toISOString().slice(0, 10),
              rearchive,
            }),
          });
          done++;
        } catch (_) {}
      }
      await refreshPayments();
      if (done > 0)
        toast(`${done} quittance${done > 1 ? "s" : ""} émise${done > 1 ? "s" : ""}`, { icon: "checkCircle" });
    },

    saveTenant: (f) => {
      const body = { full_name: f.full_name, email: f.email, address: f.address };
      if (f.id) body.id = f.id;
      fetch("/api/tenants", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      })
        .then(() => { refreshTenants(); setDrawer(null); toast(f.id ? "Locataire mis à jour" : "Locataire créé"); })
        .catch(e => toast(`Erreur : ${e.message}`, { kind: "err", icon: "alert" }));
    },

    removeTenant: (t) => {
      if (!window.confirm(`Supprimer le locataire « ${t.full_name} » ?`)) return;
      fetch(`/api/tenants/${t.id}`, { method: "DELETE" })
        .then(() => { refreshTenants(); toast("Locataire supprimé", { icon: "trash" }); })
        .catch(e => toast(`Erreur : ${e.message}`, { kind: "err", icon: "alert" }));
    },

    saveProperty: (property, data) => {
      const body = { label: data.label, address: data.address, rent_amount: data.rent_amount, charges_amount: data.charges_amount };
      if (property) body.id = property.id;
      fetch("/api/properties", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      })
        .then(() => {
          refreshProperties();
          setDrawer(null);
          toast(property ? "Bien mis à jour" : "Bien créé");
        })
        .catch(e => toast(`Erreur : ${e.message}`, { kind: "err", icon: "alert" }));
    },

    savePayment: (data) => {
      fetch("/api/payments", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      })
        .then(() => { refreshPayments(); setModal(null); toast("Paiement enregistré"); })
        .catch(e => toast(`Erreur : ${e.message}`, { kind: "err", icon: "alert" }));
    },
  };

  // ---- Loading screen -----------------------------------------------------
  if (loading) {
    return (
      <div style={{ display: "grid", placeItems: "center", height: "100vh", color: "var(--ink-3)" }}>
        <div style={{ textAlign: "center" }}>
          <Icon name="refresh" size={28} className="spin" style={{ opacity: 0.4 }} />
          <div style={{ marginTop: 12, fontSize: 13 }}>Chargement…</div>
        </div>
      </div>
    );
  }

  const Screen = {
    dashboard: Dashboard, quittances: Quittances, paiements: Paiements,
    locataires: Locataires, biens: Biens, reglages: Reglages,
  }[route] || Dashboard;

  const firstName = (owner.full_name || "").split(/[\s&]+/)[0];
  const crumb     = firstName ? `${firstName} · ${owner.city || "Régie locative"}` : "Régie locative";

  return (
    <div className="app">
      <Sidebar route={route} setRoute={setRoute} todoCount={metrics(period).todoCount} owner={owner} />
      <div className="main">
        <Topbar title={TITLES[route]} crumb={crumb} theme={theme} setTheme={setTheme} />
        <div className="content">
          <Screen store={store} />
        </div>
      </div>

      {drawer && drawer.type === "tenant"   && <TenantDrawer   tenant={drawer.item}   store={store} />}
      {drawer && drawer.type === "property" && <PropertyDrawer property={drawer.item} store={store} />}
      {modal  && modal.type  === "payment"  && <PaymentModal   row={modal.row}        store={store} />}

      {processCtx && (
        <ProcessFlow
          ctx={processCtx}
          pushToast={toast}
          onClose={() => setProcessCtx(null)}
          onComplete={() => { store.completeProcess(); setProcessCtx(null); }}
        />
      )}

      <Toasts items={toasts} />
    </div>
  );
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
