/* ============================================================
   Shared UI components + icon set
   ============================================================ */
const { useState, useEffect, useRef, useMemo, useCallback } = React;

/* ---- Icon set (simple stroke icons) ----------------------------------- */
const ICONS = {
  dashboard: "M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z",
  tenants:   "M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75",
  building:  "M3 21h18 M5 21V7l7-4 7 4v14 M9 9h0 M9 13h0 M9 17h0 M15 9h0 M15 13h0 M15 17h0",
  payments:  "M2 7h20v12H2z M2 11h20 M6 15h4",
  receipt:   "M6 2h12v20l-3-2-3 2-3-2-3 2V2z M9 7h6 M9 11h6 M9 15h3",
  settings:  "M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z",
  bolt:      "M13 2 3 14h7l-1 8 10-12h-7l1-8z",
  plus:      "M12 5v14 M5 12h14",
  chevL:     "M15 18l-6-6 6-6",
  chevR:     "M9 18l6-6-6-6",
  chevDown:  "M6 9l6 6 6-6",
  check:     "M20 6 9 17l-5-5",
  checkCircle:"M22 11.08V12a10 10 0 1 1-5.93-9.14 M22 4 12 14.01l-3-3",
  mail:      "M2 5h20v14H2z M2 6l10 7 10-7",
  archive:   "M3 8h18v13H3z M1 4h22v4H1z M10 12h4",
  cloud:     "M18 10a4 4 0 0 0-7.6-1.3A3.5 3.5 0 1 0 6 16h12a3 3 0 0 0 0-6h0z",
  download:  "M12 3v12 M7 11l5 5 5-5 M5 21h14",
  send:      "M22 2 11 13 M22 2l-7 20-4-9-9-4 20-7z",
  alert:     "M12 9v4 M12 17h0 M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z",
  x:         "M18 6 6 18 M6 6l12 12",
  edit:      "M12 20h9 M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z",
  trash:     "M3 6h18 M8 6V4h8v2 M19 6l-1 14H6L5 6 M10 11v6 M14 11v6",
  search:    "M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z M21 21l-4.3-4.3",
  filter:    "M22 3H2l8 9.5V19l4 2v-8.5L22 3z",
  clock:     "M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z M12 6v6l4 2",
  euro:      "M18 7a6 6 0 1 0 0 10 M4 10h9 M4 14h7",
  calendar:  "M3 5h18v17H3z M3 9h18 M8 2v5 M16 2v5",
  home:      "M3 11l9-8 9 8 M5 9v12h14V9",
  refresh:   "M21 12a9 9 0 1 1-3-6.7L21 8 M21 3v5h-5",
  dot:       "",
  external:  "M15 3h6v6 M10 14 21 3 M21 14v7H3V3h7",
  file:      "M14 2H6v20h12V8l-4-6z M14 2v6h4",
  eye:       "M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z",
  spark:     "M5 12h4l2-7 4 14 2-7h2",
  user:      "M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z",
};

function Icon({ name, size = 18, className = "", style, strokeWidth = 1.8 }) {
  const d = ICONS[name];
  if (name === "dot") {
    return <svg width={size} height={size} viewBox="0 0 24 24" className={className} style={style}><circle cx="12" cy="12" r="5" fill="currentColor" /></svg>;
  }
  const paths = (d || "").split(" M").map((seg, i) => (i === 0 ? seg : "M" + seg));
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none"
      stroke="currentColor" strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round"
      className={className} style={style} aria-hidden="true">
      {paths.map((p, i) => <path key={i} d={p} />)}
    </svg>
  );
}

/* ---- Avatar ----------------------------------------------------------- */
function Avatar({ name, terra, size = 34 }) {
  return (
    <div className={"avatar" + (terra ? " terra" : "")} style={{ width: size, height: size, fontSize: size * 0.37 }}>
      {RENT.initials(name)}
    </div>
  );
}

/* ---- Status pill ------------------------------------------------------- */
// receipt -> {kind, label}
function receiptStatus(payment) {
  if (!payment) return { kind: "muted", label: "En attente de paiement", short: "à venir" };
  const r = payment.receipt;
  if (!r) return { kind: "todo", label: "À quittancer", short: "à traiter" };
  if (r.archive_error || (r.sent_at && !r.archived_at)) return { kind: "warn", label: "Archivage à reprendre", short: "archivage" };
  if (r.send_error) return { kind: "err", label: "Échec d'envoi", short: "erreur" };
  if (r.archived_at && r.sent_at) return { kind: "ok", label: "Envoyée & archivée", short: "complète" };
  if (r.sent_at) return { kind: "info", label: "Envoyée", short: "envoyée" };
  return { kind: "info", label: "Quittance générée", short: "générée" };
}

function Pill({ kind = "muted", children, dot = true }) {
  return (
    <span className={"pill " + kind}>
      {dot && <span className="dot" />}
      {children}
    </span>
  );
}

/* ---- Sidebar ----------------------------------------------------------- */
const NAV = [
  { id: "dashboard", label: "Tableau de bord", icon: "dashboard", group: "Pilotage" },
  { id: "quittances", label: "Quittances", icon: "receipt", group: "Pilotage" },
  { id: "paiements", label: "Paiements", icon: "payments", group: "Pilotage" },
  { id: "locataires", label: "Locataires", icon: "tenants", group: "Référentiel" },
  { id: "biens", label: "Biens", icon: "building", group: "Référentiel" },
  { id: "reglages", label: "Réglages", icon: "settings", group: "Système" },
];

function Sidebar({ route, setRoute, todoCount, owner }) {
  const groups = [];
  NAV.forEach(n => {
    let g = groups.find(x => x.name === n.group);
    if (!g) { g = { name: n.group, items: [] }; groups.push(g); }
    g.items.push(n);
  });
  return (
    <aside className="side">
      <div className="brand">
        <div className="brand-mark" />
        <div>
          <div className="brand-name">Quittance</div>
          <div className="brand-sub">Régie locative</div>
        </div>
      </div>
      {groups.map(g => (
        <div key={g.name}>
          <div className="nav-group-label">{g.name}</div>
          {g.items.map(n => (
            <button key={n.id} className={"nav-item" + (route === n.id ? " active" : "")} onClick={() => setRoute(n.id)}>
              <Icon name={n.icon} className="ico" />
              <span>{n.label}</span>
              {n.id === "quittances" && todoCount > 0 && <span className="nav-badge">{todoCount}</span>}
            </button>
          ))}
        </div>
      ))}
      <div className="side-foot">
        <div className="owner-chip">
          <Avatar name={owner.full_name} terra />
          <div style={{ minWidth: 0 }}>
            <div className="owner-name">Bailleur</div>
            <div className="owner-mail" style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{owner.email}</div>
          </div>
        </div>
      </div>
    </aside>
  );
}

/* ---- Month stepper ----------------------------------------------------- */
function MonthStepper({ period, setPeriod }) {
  return (
    <div className="month-step">
      <button onClick={() => setPeriod(RENT.addMonth(period, -1))} aria-label="Mois précédent"><Icon name="chevL" size={16} /></button>
      <span className="lbl">{RENT.fmtPeriod(period)}</span>
      <button onClick={() => setPeriod(RENT.addMonth(period, 1))} aria-label="Mois suivant"><Icon name="chevR" size={16} /></button>
    </div>
  );
}

/* ---- Theme switcher (segmented) --------------------------------------- */
const THEMES = [
  { id: "releve", label: "Relevé" },
  { id: "ledger", label: "Ledger" },
  { id: "atelier", label: "Atelier" },
];
function ThemeSwitch({ theme, setTheme }) {
  return (
    <div className="seg" role="tablist" aria-label="Direction visuelle">
      {THEMES.map(t => (
        <button key={t.id} className={theme === t.id ? "on" : ""} onClick={() => setTheme(t.id)}>{t.label}</button>
      ))}
    </div>
  );
}

/* ---- Topbar ------------------------------------------------------------ */
function Topbar({ title, crumb, theme, setTheme, right }) {
  return (
    <header className="topbar">
      <div>
        <h1>{title}</h1>
        {crumb && <div className="crumb">{crumb}</div>}
      </div>
      <div className="topbar-spacer" />
      {right}
      <ThemeSwitch theme={theme} setTheme={setTheme} />
    </header>
  );
}

/* ---- KPI card ---------------------------------------------------------- */
function Kpi({ icon, label, value, sub, pct, delta }) {
  return (
    <div className="card kpi">
      <div className="k-label"><Icon name={icon} className="k-ico" />{label}</div>
      <div className="k-val">{value}</div>
      {sub && <div className="k-sub">{sub}</div>}
      {delta && <div className={"delta " + delta.kind} style={{ marginTop: 8 }}>{delta.text}</div>}
      {typeof pct === "number" && (
        <div className="k-bar"><i style={{ width: Math.max(3, Math.min(100, pct)) + "%" }} /></div>
      )}
    </div>
  );
}

/* ---- Modal / Drawer shells -------------------------------------------- */
function Scrim({ onClose }) { return <div className="scrim" onClick={onClose} />; }

function Drawer({ title, sub, onClose, children, footer, icon }) {
  useEffect(() => {
    const h = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [onClose]);
  return (
    <>
      <Scrim onClose={onClose} />
      <div className="drawer" role="dialog" aria-modal="true">
        <div className="drawer-head">
          {icon && <div className="brand-mark" style={{ background: "var(--terra-soft)", color: "var(--terra-deep)" }}><Icon name={icon} size={18} /></div>}
          <div style={{ flex: 1 }}>
            <h2>{title}</h2>
            {sub && <div className="small muted" style={{ marginTop: 2 }}>{sub}</div>}
          </div>
          <button className="btn icon ghost" onClick={onClose} aria-label="Fermer"><Icon name="x" /></button>
        </div>
        <div className="drawer-body">{children}</div>
        {footer && <div className="drawer-foot">{footer}</div>}
      </div>
    </>
  );
}

function Modal({ children, onClose, width }) {
  useEffect(() => {
    const h = (e) => { if (e.key === "Escape" && onClose) onClose(); };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, [onClose]);
  return (
    <>
      <Scrim onClose={onClose} />
      <div className="modal" style={width ? { width } : undefined} role="dialog" aria-modal="true">{children}</div>
    </>
  );
}

/* ---- Field helpers ----------------------------------------------------- */
function Field({ label, hint, children }) {
  return (
    <div className="field">
      {label && <label>{label}</label>}
      {children}
      {hint && <div className="hint">{hint}</div>}
    </div>
  );
}
// money input in euros (string) <-> displayed
function MoneyInput({ value, onChange }) {
  return (
    <div className="input-affix">
      <input className="input money" value={value} inputMode="decimal"
        onChange={e => onChange(e.target.value.replace(/[^0-9.,]/g, ""))} />
      <span className="affix">€</span>
    </div>
  );
}

/* ---- Toasts ------------------------------------------------------------ */
function Toasts({ items }) {
  return (
    <div className="toasts">
      {items.map(t => (
        <div key={t.id} className={"toast " + (t.kind || "")}>
          <Icon name={t.icon || "check"} className="ico" />
          {t.text}
        </div>
      ))}
    </div>
  );
}

/* ---- Image placeholder ------------------------------------------------- */
function ImgSlot({ label, h = 90 }) {
  return <div className="imgslot" style={{ height: h }}>{label}</div>;
}

/* expose */
Object.assign(window, {
  Icon, Avatar, Pill, receiptStatus, Sidebar, MonthStepper, ThemeSwitch, THEMES,
  Topbar, Kpi, Drawer, Modal, Scrim, Field, MoneyInput, Toasts, ImgSlot, NAV,
});
