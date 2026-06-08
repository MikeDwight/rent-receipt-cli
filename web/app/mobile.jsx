/* ============================================================
   Mobile app shell — store API-wired, bottom sheets, tab bar
   Screens defined in mobile-screens.jsx (loaded before this).
   ============================================================ */
const { useState, useEffect, useCallback } = React;

/* ---- generic bottom sheet -------------------------------------------- */
function Sheet({ title, sub, icon, onClose, dismissable = true, children, footer }) {
  return (
    <>
      <div className="m-scrim" onClick={dismissable ? onClose : undefined} />
      <div className="m-sheet" role="dialog" aria-modal="true">
        <div className="m-handle" />
        <div className="m-sheet-head">
          {icon && <div className="m-iconbtn terra" style={{ pointerEvents: "none" }}><Icon name={icon} size={18} /></div>}
          <div style={{ flex: 1, minWidth: 0 }}>
            <h2 style={{ whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis" }}>{title}</h2>
            {sub && <div className="small muted" style={{ marginTop: 2 }}>{sub}</div>}
          </div>
          {dismissable && <button className="m-iconbtn sec" onClick={onClose} aria-label="Fermer"><Icon name="x" size={18} /></button>}
        </div>
        <div className="m-sheet-body">{children}</div>
        {footer && <div className="m-sheet-foot">{footer}</div>}
      </div>
    </>
  );
}

/* ---- process sheet ---------------------------------------------------- */
function ProcessSheet({ ctx, store }) {
  const { tenant, property, period, mode } = ctx;
  const [stage, setStage] = useState("confirm");
  const [dryRun, setDryRun] = useState(false);
  const [generateOnly, setGenerateOnly] = useState(false);
  const [error, setError] = useState(null);

  const total = property.rent_amount + property.charges_amount + (property.services_amount || 0);
  const titleByMode = {
    process: "Encaisser & quittancer",
    rearchive: "Reprendre l'archivage",
    resend: "Renvoyer la quittance",
    send: "Envoyer la quittance",
  };

  async function run() {
    setStage("running");
    try {
      const res = await fetch("/api/process", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          tenant_id: tenant.id,
          property_id: property.id,
          period,
          paid_at: new Date().toISOString().slice(0, 10),
          dry_run: dryRun,
          resend: mode === "resend",
          rearchive: mode === "rearchive",
          generate_only: generateOnly,
        }),
      });
      const data = await res.json();
      if (!data.result?.ok) {
        setError((data.result?.error_messages || ["Erreur inconnue"]).join(", "));
        setStage("error");
      } else {
        setStage("done");
        if (!dryRun) store.completeProcess();
      }
    } catch (e) {
      setError(e.message);
      setStage("error");
    }
  }

  const footer = stage === "confirm" ? (
    <>
      <button className="m-btn sec" onClick={store.closeSheet}>Annuler</button>
      <button className="m-btn" onClick={run}>
        <Icon name={dryRun ? "eye" : "bolt"} size={17} />{dryRun ? "Simuler" : "Lancer"}
      </button>
    </>
  ) : stage === "running" ? (
    <button className="m-btn" disabled><Icon name="refresh" size={16} className="spin" />Traitement…</button>
  ) : stage === "error" ? (
    <>
      <button className="m-btn sec" onClick={store.closeSheet}>Fermer</button>
      <button className="m-btn" onClick={() => { setStage("confirm"); setError(null); }}><Icon name="refresh" size={16} />Réessayer</button>
    </>
  ) : (
    <button className="m-btn" onClick={store.closeSheet}>Terminé</button>
  );

  return (
    <Sheet title={titleByMode[mode] || "Quittancer"} sub={`${tenant.full_name} · ${RENT.fmtPeriod(period)}`}
      icon="bolt" onClose={store.closeSheet} dismissable={stage !== "running"} footer={footer}>

      {stage === "confirm" && (
        <div>
          <div className="m-card pad" style={{ background: "var(--surface-2)" }}>
            <div className="m-eyebrow">{property.label}</div>
            <dl className="m-dl" style={{ marginTop: 12 }}>
              <dt>Loyer</dt><dd className="num">{RENT.fmtEUR(property.rent_amount)}</dd>
              <dt>Charges</dt><dd className="num">{RENT.fmtEUR(property.charges_amount)}</dd>
              {(property.services_amount || 0) > 0 && <>
                <dt>Services</dt><dd className="num">{RENT.fmtEUR(property.services_amount)}</dd>
              </>}
              <dt style={{ color: "var(--ink)", fontWeight: 600 }}>Total</dt>
              <dd className="num" style={{ fontWeight: 700 }}>{RENT.fmtEUR(total)}</dd>
            </dl>
          </div>
          <p className="small muted" style={{ lineHeight: 1.5, marginTop: 14 }}>
            {mode === "process" && !generateOnly && "Enchaîne tout le flux : paiement, PDF, email au locataire, archivage Nextcloud."}
            {mode === "process" && generateOnly && "Génère le PDF uniquement. Aucun email ne sera envoyé."}
            {mode === "rearchive" && "Relance uniquement le dépôt Nextcloud, sans renvoyer d'email."}
            {mode === "resend" && "Renvoie l'email au locataire puis revérifie l'archivage."}
            {mode === "send" && "Envoie la quittance par email au locataire et l'archive sur Nextcloud."}
          </p>
          {mode === "process" && (
            <label style={{ display: "flex", gap: 10, alignItems: "center", padding: "10px 2px", fontSize: 13.5 }}>
              <input type="checkbox" checked={generateOnly} onChange={e => { setGenerateOnly(e.target.checked); if (e.target.checked) setDryRun(false); }} style={{ width: 18, height: 18 }} />
              <span><b>Générer seulement</b> · sans envoi email</span>
            </label>
          )}
          {!generateOnly && (
            <label style={{ display: "flex", gap: 10, alignItems: "center", padding: "10px 2px", fontSize: 13.5 }}>
              <input type="checkbox" checked={dryRun} onChange={e => { setDryRun(e.target.checked); if (e.target.checked) setGenerateOnly(false); }} style={{ width: 18, height: 18 }} />
              <span><b>Simulation</b> · aucune écriture</span>
            </label>
          )}
        </div>
      )}

      {stage === "running" && (
        <div style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 16, padding: "32px 0" }}>
          <Icon name="refresh" size={32} className="spin" style={{ color: "var(--terra)" }} />
          <div style={{ fontWeight: 600 }}>Traitement en cours…</div>
        </div>
      )}

      {stage === "done" && (
        <div className="m-card pad" style={{ background: dryRun ? "var(--ochre-soft)" : "var(--olive-soft)", border: "none" }}>
          <div style={{ display: "flex", gap: 11, alignItems: "center" }}>
            <Icon name={dryRun ? "eye" : "checkCircle"} size={22} style={{ color: dryRun ? "oklch(0.5 0.11 70)" : "oklch(0.45 0.10 140)" }} />
            <div>
              <div style={{ fontWeight: 700, fontSize: 14 }}>
                {dryRun ? "Simulation terminée" : generateOnly ? "PDF généré" : mode === "send" ? "Quittance envoyée" : "Quittance émise"}
              </div>
              <div className="small" style={{ color: "var(--ink-2)" }}>{dryRun ? "Aucune modification." : "Opération réussie."}</div>
            </div>
          </div>
        </div>
      )}

      {stage === "error" && (
        <div className="m-card pad" style={{ background: "var(--clay-soft)", border: "none" }}>
          <div style={{ display: "flex", gap: 11, alignItems: "center" }}>
            <Icon name="alert" size={22} style={{ color: "var(--clay)" }} />
            <div>
              <div style={{ fontWeight: 700, fontSize: 14 }}>Erreur</div>
              <div className="small" style={{ color: "var(--ink-2)" }}>{error}</div>
            </div>
          </div>
        </div>
      )}
    </Sheet>
  );
}

/* ---- payment sheet ---------------------------------------------------- */
function PaymentSheet({ ctx, store }) {
  const tenantsList = store.tenants.filter(t => t.property_id);
  const preset = ctx && ctx.tenant ? ctx.tenant : (tenantsList[0] || store.tenants[0]);
  const [tenantId, setTenantId] = useState(preset ? preset.id : null);
  const tenant = tenantId ? store.tenantById(tenantId) : null;
  const property = tenant ? store.propertyById(tenant.property_id) : null;
  const toE = c => (c / 100).toString().replace(".", ",");
  const [rent, setRent] = useState(toE(property?.rent_amount || 0));
  const [charges, setCharges] = useState(toE(property?.charges_amount || 0));
  const [services, setServices] = useState(toE(property?.services_amount || 0));
  const [paidAt, setPaidAt] = useState(new Date().toISOString().slice(0, 10));

  useEffect(() => {
    if (property) {
      setRent(toE(property.rent_amount));
      setCharges(toE(property.charges_amount));
      setServices(toE(property.services_amount || 0));
    }
  }, [tenantId]);

  const cents = s => Math.round(parseFloat(String(s).replace(",", ".") || "0") * 100);
  const total = cents(rent) + cents(charges) + cents(services);

  if (!tenant || !property) return (
    <Sheet title="Saisir un paiement" icon="euro" onClose={store.closeSheet}
      footer={<button className="m-btn sec" onClick={store.closeSheet}>Annuler</button>}>
      <p className="small muted">Aucun locataire avec bien associé.</p>
    </Sheet>
  );

  return (
    <Sheet title="Saisir un paiement" sub={`${RENT.fmtPeriod(store.period)}`} icon="euro" onClose={store.closeSheet}
      footer={<>
        <button className="m-btn sec" onClick={store.closeSheet}>Annuler</button>
        <button className="m-btn" onClick={() => store.savePayment({
          tenant_id: tenantId, property_id: property.id, period: store.period,
          rent_amount: cents(rent), charges_amount: cents(charges), services_amount: cents(services), paid_at: paidAt,
        })}><Icon name="check" size={16} />Enregistrer</button>
      </>}>
      <Field label="Locataire">
        <select className="select" value={tenantId} onChange={e => setTenantId(Number(e.target.value))}>
          {store.tenants.filter(t => t.property_id).map(t => {
            const p = store.propertyById(t.property_id);
            return <option key={t.id} value={t.id}>{t.full_name}{p ? ` — ${p.label}` : ""}</option>;
          })}
        </select>
      </Field>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
        <Field label="Loyer"><MoneyInput value={rent} onChange={setRent} /></Field>
        <Field label="Charges"><MoneyInput value={charges} onChange={setCharges} /></Field>
      </div>
      {(property.services_amount || 0) > 0 && (
        <Field label="Services résidence"><MoneyInput value={services} onChange={setServices} /></Field>
      )}
      <Field label="Encaissé le"><input className="input" type="date" value={paidAt} onChange={e => setPaidAt(e.target.value)} /></Field>
      <div className="m-card pad" style={{ background: "var(--terra-soft)", border: "none", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <span className="m-eyebrow" style={{ color: "var(--terra-deep)" }}>Total encaissé</span>
        <span className="num" style={{ fontSize: 21, fontWeight: 700, color: "var(--terra-deep)" }}>{RENT.fmtEUR(total)}</span>
      </div>
    </Sheet>
  );
}

/* ---- tenant sheet ----------------------------------------------------- */
function TenantSheet({ ctx, store }) {
  const t = ctx && ctx.item;
  const [f, setF] = useState(() => t ? { ...t } : { full_name: "", email: "", address: "" });
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));
  const valid = f.full_name.trim() && /\S+@\S+\.\S+/.test(f.email) && f.address.trim();
  return (
    <Sheet title={t ? "Modifier le locataire" : "Nouveau locataire"} sub={t ? `tenant #${t.id}` : "tenant:upsert"} icon="tenants" onClose={store.closeSheet}
      footer={<>
        <button className="m-btn sec" onClick={store.closeSheet}>Annuler</button>
        <button className="m-btn" disabled={!valid} onClick={() => store.saveTenant(f)}>
          <Icon name="check" size={16} />{t ? "Enregistrer" : "Créer"}
        </button>
      </>}>
      <Field label="Nom complet"><input className="input" value={f.full_name} onChange={e => set("full_name", e.target.value)} placeholder="Jean Dupont" /></Field>
      <Field label="Email" hint="Adresse d'envoi de la quittance."><input className="input" value={f.email} onChange={e => set("email", e.target.value)} placeholder="jean@example.com" /></Field>
      <Field label="Adresse"><textarea className="textarea" value={f.address} onChange={e => set("address", e.target.value)} /></Field>
    </Sheet>
  );
}

/* ---- property sheet --------------------------------------------------- */
function PropertySheet({ ctx, store }) {
  const p = ctx && ctx.item;
  const toE = c => (c / 100).toString().replace(".", ",");
  const [f, setF] = useState(() => p
    ? { label: p.label, address: p.address, rent: toE(p.rent_amount), charges: toE(p.charges_amount), services: toE(p.services_amount || 0) }
    : { label: "", address: "", rent: "", charges: "", services: "" });
  const set = (k, v) => setF(s => ({ ...s, [k]: v }));
  const cents = s => Math.round(parseFloat(String(s).replace(",", ".") || "0") * 100);
  const total = cents(f.rent) + cents(f.charges) + cents(f.services);
  const valid = f.label.trim() && f.address.trim() && cents(f.rent) > 0;
  return (
    <Sheet title={p ? "Modifier le bien" : "Nouveau bien"} sub={p ? `property #${p.id}` : "property:upsert"} icon="building" onClose={store.closeSheet}
      footer={<>
        <button className="m-btn sec" onClick={store.closeSheet}>Annuler</button>
        <button className="m-btn" disabled={!valid} onClick={() => store.saveProperty(p, { label: f.label, address: f.address, rent_amount: cents(f.rent), charges_amount: cents(f.charges), services_amount: cents(f.services) })}>
          <Icon name="check" size={16} />{p ? "Enregistrer" : "Créer"}
        </button>
      </>}>
      <Field label="Libellé"><input className="input" value={f.label} onChange={e => set("label", e.target.value)} placeholder="Studio JJ. Rousseau" /></Field>
      <Field label="Adresse"><textarea className="textarea" value={f.address} onChange={e => set("address", e.target.value)} /></Field>
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
        <Field label="Loyer"><MoneyInput value={f.rent} onChange={v => set("rent", v)} /></Field>
        <Field label="Charges"><MoneyInput value={f.charges} onChange={v => set("charges", v)} /></Field>
      </div>
      <Field label="Services résidence"><MoneyInput value={f.services} onChange={v => set("services", v)} /></Field>
      <div className="m-card pad" style={{ background: "var(--surface-2)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <span className="m-eyebrow">Total mensuel TTC</span>
        <span className="num" style={{ fontSize: 21, fontWeight: 700, color: "var(--terra-deep)" }}>{RENT.fmtEUR(total)}</span>
      </div>
    </Sheet>
  );
}

/* ---- tab bar ---------------------------------------------------------- */
const M_TABS = [
  { id: "bord",       label: "Bord",      icon: "dashboard" },
  { id: "quittances", label: "Quittances", icon: "receipt"  },
  { id: "paiements",  label: "Paiements",  icon: "payments" },
  { id: "gestion",    label: "Gestion",    icon: "building" },
  { id: "reglages",   label: "Réglages",   icon: "settings" },
];

/* ---- mobile root ------------------------------------------------------ */
function MobileApp() {
  const persisted = (() => { try { return JSON.parse(localStorage.getItem("rent_ui") || "{}"); } catch (e) { return {}; } })();

  const [route,      setRoute]      = useState(persisted.m_route || "bord");
  const [period,     setPeriod]     = useState(RENT.CURRENT_PERIOD);
  const [owner,      setOwner]      = useState(RENT.owner);
  const [tenants,    setTenants]    = useState([]);
  const [properties, setProperties] = useState([]);
  const [payments,   setPayments]   = useState([]);
  const [loading,    setLoading]    = useState(true);
  const [sheet,      setSheet]      = useState(null);
  const [toasts,     setToasts]     = useState([]);

  useEffect(() => {
    try { const prev = JSON.parse(localStorage.getItem("rent_ui") || "{}"); localStorage.setItem("rent_ui", JSON.stringify({ ...prev, m_route: route })); } catch (e) {}
  }, [route]);

  useEffect(() => {
    Promise.all([
      fetch("/api/tenants").then(r => r.json()),
      fetch("/api/properties").then(r => r.json()),
      fetch("/api/owner").then(r => r.json()),
    ]).then(([tData, pData, oData]) => {
      setTenants(Array.isArray(tData) ? tData : []);
      setProperties(Array.isArray(pData) ? pData : []);
      if (Array.isArray(oData) && oData.length > 0) { const o = oData[0]; setOwner(o); RENT.owner = o; }
      setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  useEffect(() => {
    fetch(`/api/payments?period=${period}`).then(r => r.json()).then(d => setPayments(Array.isArray(d) ? d : [])).catch(() => {});
  }, [period]);

  const toast = useCallback((text, opts = {}) => {
    const id = Math.random().toString(36).slice(2);
    setToasts(t => [...t, { id, text, icon: opts.icon || "check" }]);
    setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), 2400);
  }, []);

  const tenantById       = useCallback(id => tenants.find(t => t.id === id), [tenants]);
  const propertyById     = useCallback(id => properties.find(p => p.id === id), [properties]);
  const paymentForTenant = useCallback((tid, per) => payments.find(p => p.tenant_id === tid && p.period === per), [payments]);
  const paymentsForPeriod = useCallback(per => payments.filter(p => p.period === per).sort((a, b) => a.tenant_id - b.tenant_id), [payments]);
  const rowsForPeriod    = useCallback(per => tenants.map(t => ({
    tenant: t, property: t.property_id ? propertyById(t.property_id) : null, payment: paymentForTenant(t.id, per) || null,
  })).filter(r => r.property), [tenants, propertyById, paymentForTenant]);

  const metrics = useCallback(per => {
    const rows = rowsForPeriod(per);
    let expected = 0, collected = 0, sent = 0, archived = 0, todoCount = 0, maxLot = 1;
    rows.forEach(r => {
      const lot = r.property.rent_amount + r.property.charges_amount + (r.property.services_amount || 0);
      expected += lot; maxLot = Math.max(maxLot, lot);
      if (r.payment) collected += r.payment.rent_amount + r.payment.charges_amount + (r.payment.services_amount || 0);
      const rc = r.payment && r.payment.receipt;
      if (rc && rc.sent_at) sent++;
      if (rc && rc.archived_at) archived++;
      const k = receiptStatus(r.payment).kind;
      if (k === "todo" || k === "warn" || k === "err") todoCount++;
    });
    return { lots: rows.length, expected, collected, sent, archived, todoCount, maxLot };
  }, [rowsForPeriod]);

  const recentActivity = useCallback(per => {
    const tone = { olive: { bg: "var(--olive-soft)", color: "oklch(0.45 0.10 140)" }, terra: { bg: "var(--terra-soft)", color: "var(--terra-deep)" }, clay: { bg: "var(--clay-soft)", color: "var(--clay)" }, slate: { bg: "var(--slate-soft)", color: "oklch(0.46 0.07 245)" } };
    const ev = [];
    rowsForPeriod(per).forEach(r => {
      const t = r.tenant, p = r.payment; if (!p) return; const rc = p.receipt;
      if (rc && rc.archive_error) ev.push({ icon: "alert", text: `Échec d'archivage — ${t.full_name}`, when: RENT.fmtDate(rc.sent_at || p.paid_at), key: rc.sent_at || p.paid_at, ...tone.clay });
      else if (rc && rc.archived_at) ev.push({ icon: "cloud", text: `Quittance archivée — ${t.full_name}`, when: RENT.fmtDate(rc.archived_at), key: rc.archived_at, ...tone.slate });
      if (rc && rc.sent_at && !rc.archive_error) ev.push({ icon: "mail", text: `Quittance envoyée — ${t.full_name}`, when: RENT.fmtDate(rc.sent_at), key: rc.sent_at, ...tone.olive });
      if (!rc) ev.push({ icon: "euro", text: `Loyer encaissé — ${t.full_name}`, when: RENT.fmtDate(p.paid_at), key: p.paid_at, ...tone.terra });
    });
    ev.sort((a, b) => (b.key || "").localeCompare(a.key || ""));
    return ev.slice(0, 6);
  }, [rowsForPeriod]);

  const refreshTenants    = useCallback(() => fetch("/api/tenants").then(r => r.json()).then(d => setTenants(Array.isArray(d) ? d : [])), []);
  const refreshProperties = useCallback(() => fetch("/api/properties").then(r => r.json()).then(d => setProperties(Array.isArray(d) ? d : [])), []);
  const refreshPayments   = useCallback(() => fetch(`/api/payments?period=${period}`).then(r => r.json()).then(d => setPayments(Array.isArray(d) ? d : [])), [period]);

  const store = {
    route, setRoute, period, setPeriod, owner, tenants, properties, loading,
    tenantById, propertyById, paymentForTenant, paymentsForPeriod, rowsForPeriod, metrics, recentActivity, toast,

    openProcess:  (tenant, property, mode) => setSheet({ type: "process",  ctx: { tenant, property, period, mode } }),
    openPayment:  (tenant)  => setSheet({ type: "payment",  ctx: tenant ? { tenant } : null }),
    openTenant:   (item)    => setSheet({ type: "tenant",   ctx: { item } }),
    openProperty: (item)    => setSheet({ type: "property", ctx: { item } }),
    closeSheet:   ()        => setSheet(null),
    completeProcess: ()     => refreshPayments(),

    processAll: async () => {
      const rows = rowsForPeriod(period).filter(r => ["todo", "warn", "err"].includes(receiptStatus(r.payment).kind));
      for (const r of rows) {
        await fetch("/api/process", {
          method: "POST", headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ tenant_id: r.tenant.id, property_id: r.property.id, period, paid_at: new Date().toISOString().slice(0, 10), rearchive: receiptStatus(r.payment).kind === "warn" }),
        }).catch(() => {});
      }
      await refreshPayments();
      if (rows.length) toast(`${rows.length} quittance${rows.length > 1 ? "s émises" : " émise"}`, { icon: "checkCircle" });
    },

    saveTenant: (f) => {
      const body = { full_name: f.full_name, email: f.email, address: f.address };
      if (f.id) body.id = f.id;
      fetch("/api/tenants", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) })
        .then(() => { refreshTenants(); setSheet(null); toast(f.id ? "Locataire mis à jour" : "Locataire créé"); })
        .catch(e => toast(`Erreur : ${e.message}`, { icon: "alert" }));
    },

    saveProperty: (property, data) => {
      const body = { label: data.label, address: data.address, rent_amount: data.rent_amount, charges_amount: data.charges_amount, services_amount: data.services_amount || 0 };
      if (property) body.id = property.id;
      fetch("/api/properties", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(body) })
        .then(() => { refreshProperties(); setSheet(null); toast(property ? "Bien mis à jour" : "Bien créé"); })
        .catch(e => toast(`Erreur : ${e.message}`, { icon: "alert" }));
    },

    savePayment: (data) => {
      fetch("/api/payments", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) })
        .then(() => { refreshPayments(); setSheet(null); toast("Paiement enregistré"); })
        .catch(e => toast(`Erreur : ${e.message}`, { icon: "alert" }));
    },

    removeReceipt: (rid) => {
      if (!window.confirm(`Supprimer la quittance #${rid} ? Le paiement sera conservé.`)) return;
      fetch(`/api/receipts/${rid}`, { method: "DELETE" })
        .then(() => { refreshPayments(); toast("Quittance supprimée", { icon: "trash" }); })
        .catch(e => toast(`Erreur : ${e.message}`, { icon: "alert" }));
    },
  };

  if (loading) {
    return (
      <div className="m-app" style={{ display: "grid", placeItems: "center" }}>
        <div style={{ textAlign: "center", color: "var(--ink-3)" }}>
          <Icon name="refresh" size={28} className="spin" style={{ opacity: 0.4 }} />
          <div style={{ marginTop: 12, fontSize: 13 }}>Chargement…</div>
        </div>
      </div>
    );
  }

  const Screen = { bord: MBord, quittances: MQuittances, paiements: MPaiements, gestion: MGestion, reglages: MReglages }[route];
  const todoCount = metrics(period).todoCount;

  return (
    <div className="m-app">
      <Screen store={store} />
      <nav className="m-tabs">
        {M_TABS.map(t => (
          <button key={t.id} className={"m-tab" + (route === t.id ? " on" : "")} onClick={() => setRoute(t.id)}>
            {t.id === "quittances" && todoCount > 0 && <span className="badge">{todoCount}</span>}
            <Icon name={t.icon} size={22} strokeWidth={route === t.id ? 2.1 : 1.8} />
            <span className="lab">{t.label}</span>
          </button>
        ))}
      </nav>
      {sheet && sheet.type === "process"  && <ProcessSheet  ctx={sheet.ctx} store={store} />}
      {sheet && sheet.type === "payment"  && <PaymentSheet  ctx={sheet.ctx} store={store} />}
      {sheet && sheet.type === "tenant"   && <TenantSheet   ctx={sheet.ctx} store={store} />}
      {sheet && sheet.type === "property" && <PropertySheet ctx={sheet.ctx} store={store} />}
      <div className="m-toasts">
        {toasts.map(t => <div className="m-toast" key={t.id}><Icon name={t.icon} size={15} />{t.text}</div>)}
      </div>
    </div>
  );
}

/* ---- responsive root -------------------------------------------------- */
function Root() {
  const [isMobile, setIsMobile] = useState(() => window.innerWidth < 768);
  useEffect(() => {
    const mq = window.matchMedia("(max-width: 767px)");
    const handler = e => setIsMobile(e.matches);
    mq.addEventListener("change", handler);
    return () => mq.removeEventListener("change", handler);
  }, []);
  return isMobile ? <MobileApp /> : <App />;
}

ReactDOM.createRoot(document.getElementById("root")).render(<Root />);
