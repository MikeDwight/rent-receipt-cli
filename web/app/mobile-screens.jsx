/* ============================================================
   Mobile screens — Bord · Quittances · Paiements · Gestion · Réglages
   ============================================================ */
const { useState, useEffect } = React;

function MTop({ title, right, below }) {
  return (
    <header className="m-top">
      <div className="m-top-row">
        <h1>{title}</h1>
        <div className="grow" />
        {right}
      </div>
      {below && <div style={{ marginTop: 12 }}>{below}</div>}
    </header>
  );
}

function MMonth({ store, center }) {
  return (
    <div style={center ? { display: "flex", justifyContent: "center" } : undefined}>
      <div className="m-month">
        <button onClick={() => store.setPeriod(RENT.addMonth(store.period, -1))} aria-label="Mois précédent"><Icon name="chevL" size={16} /></button>
        <span className="lbl">{RENT.fmtPeriod(store.period)}</span>
        <button onClick={() => store.setPeriod(RENT.addMonth(store.period, 1))} aria-label="Mois suivant"><Icon name="chevR" size={16} /></button>
      </div>
    </div>
  );
}

function mRowAction(row, store) {
  const st = receiptStatus(row.payment);
  if (!row.payment) return { label: "Encaisser", icon: "plus", primary: false, fn: () => store.openPayment(row.tenant) };
  if (!row.payment.receipt) return { label: "Quittancer", icon: "bolt", primary: true, fn: () => store.openProcess(row.tenant, row.property, "process") };
  if (st.kind === "warn") return { label: "Ré-archiver", icon: "cloud", primary: false, fn: () => store.openProcess(row.tenant, row.property, "rearchive") };
  if (st.kind === "info") return { label: "Envoyer", icon: "mail", primary: true, fn: () => store.openProcess(row.tenant, row.property, "send") };
  return null;
}

/* =================== BORD =================== */
function MBord({ store }) {
  const period = store.period;
  const m = store.metrics(period);
  const rows = store.rowsForPeriod(period);
  const todo = rows.filter(r => ["todo", "warn", "err", "muted"].includes(receiptStatus(r.payment).kind));
  const activity = store.recentActivity(period);
  const pct = m.expected ? Math.round((m.collected / m.expected) * 100) : 0;

  return (
    <>
      <MTop title="Tableau de bord" below={<MMonth store={store} center />} />
      <main className="m-scroll fade" key={"bord" + period}>
        {m.todoCount > 0 && (
          <div className="m-cta" style={{ marginBottom: 16 }}>
            <div className="m-eyebrow" style={{ color: "var(--terra-deep)" }}>À traiter · {RENT.fmtPeriod(period)}</div>
            <div className="big" style={{ marginTop: 6 }}>{m.todoCount} quittance{m.todoCount > 1 ? "s" : ""} en attente</div>
            <button className="m-btn" style={{ marginTop: 14 }} onClick={store.processAll}><Icon name="bolt" size={17} />Tout quittancer</button>
          </div>
        )}
        <div className="m-kpis">
          <div className="m-kpi">
            <div className="l"><Icon name="euro" size={14} />Attendus</div>
            <div className="v">{RENT.fmtEUR0(m.expected)}</div>
            <div className="s">{m.lots} lots ce mois</div>
          </div>
          <div className="m-kpi">
            <div className="l"><Icon name="check" size={14} />Encaissé</div>
            <div className="v">{RENT.fmtEUR0(m.collected)}</div>
            <div className="m-bar"><i style={{ width: Math.max(4, pct) + "%" }} /></div>
          </div>
          <div className="m-kpi">
            <div className="l"><Icon name="mail" size={14} />Envoyées</div>
            <div className="v">{m.sent}/{m.lots}</div>
            <div className="s">{m.archived} archivées</div>
          </div>
          <div className="m-kpi">
            <div className="l"><Icon name="bolt" size={14} />À traiter</div>
            <div className="v">{m.todoCount}</div>
            <div className="s">{m.todoCount === 0 ? "Mois à jour ✓" : "à finaliser"}</div>
          </div>
        </div>

        <div className="m-sectitle">À traiter <span className="c">{todo.length} / {rows.length}</span></div>
        {todo.length === 0 ? (
          <div className="m-card pad" style={{ textAlign: "center", color: "var(--ink-3)" }}>
            <Icon name="checkCircle" size={26} style={{ color: "oklch(0.55 0.10 140)" }} />
            <div style={{ fontWeight: 600, color: "var(--ink)", marginTop: 8 }}>Tout est à jour</div>
            <div className="small" style={{ marginTop: 3 }}>Quittances de {RENT.fmtPeriod(period)} émises.</div>
          </div>
        ) : (
          <div style={{ display: "flex", flexDirection: "column", gap: 10 }}>
            {todo.map(r => {
              const st = receiptStatus(r.payment);
              const act = mRowAction(r, store);
              const amt = r.property.rent_amount + r.property.charges_amount + (r.property.services_amount || 0);
              return (
                <div className="m-card pad" key={r.tenant.id}>
                  <div style={{ display: "flex", alignItems: "center", gap: 12 }}>
                    <Avatar name={r.tenant.full_name} size={38} />
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div style={{ fontWeight: 600, fontSize: 14.5 }}>{r.tenant.full_name}</div>
                      <div className="small muted" style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{r.property.label}</div>
                    </div>
                    <div style={{ textAlign: "right" }}>
                      <div className="num" style={{ fontWeight: 700, fontSize: 14.5 }}>{RENT.fmtEUR(amt)}</div>
                      <div style={{ marginTop: 5 }}><Pill kind={st.kind}>{st.label}</Pill></div>
                    </div>
                  </div>
                  {act && (
                    <button className={"m-btn" + (act.primary ? "" : " sec")} style={{ marginTop: 14, height: 44 }} onClick={act.fn}>
                      <Icon name={act.icon} size={16} />{act.label}
                    </button>
                  )}
                </div>
              );
            })}
          </div>
        )}

        <div className="m-sectitle">Activité récente</div>
        <div className="m-card">
          {activity.length === 0 && (
            <div className="m-act" style={{ color: "var(--ink-3)", justifyContent: "center", padding: 28 }}>Aucune activité.</div>
          )}
          {activity.map((a, i) => (
            <div className="m-act" key={i}>
              <div className="ic" style={{ background: a.bg, color: a.color }}><Icon name={a.icon} size={15} /></div>
              <div style={{ flex: 1 }}>
                <div className="tx">{a.text}</div>
                <div className="wh mono">{a.when}</div>
              </div>
            </div>
          ))}
        </div>
      </main>
    </>
  );
}

/* =================== QUITTANCES =================== */
function MQuittances({ store }) {
  const period = store.period;
  const [filter, setFilter] = useState("all");
  const rows = store.rowsForPeriod(period);
  const counts = {
    all: rows.length,
    todo: rows.filter(r => ["todo", "warn", "err"].includes(receiptStatus(r.payment).kind)).length,
    done: rows.filter(r => receiptStatus(r.payment).kind === "ok").length,
    pending: rows.filter(r => receiptStatus(r.payment).kind === "muted").length,
  };
  const FILTERS = [{ id: "all", label: "Toutes" }, { id: "todo", label: "À traiter" }, { id: "done", label: "Complètes" }, { id: "pending", label: "En attente" }];
  const shown = rows.filter(r => {
    const k = receiptStatus(r.payment).kind;
    if (filter === "todo") return ["todo", "warn", "err"].includes(k);
    if (filter === "done") return k === "ok";
    if (filter === "pending") return k === "muted";
    return true;
  });

  return (
    <>
      <MTop title="Quittances" below={<MMonth store={store} center />} />
      <main className="m-scroll fade" key={"q" + period}>
        <div className="m-chips">
          {FILTERS.map(f => (
            <button key={f.id} className={"m-chip" + (filter === f.id ? " on" : "")} onClick={() => setFilter(f.id)}>
              {f.label}<span className="cnt">{counts[f.id]}</span>
            </button>
          ))}
        </div>
        <div className="m-card" style={{ marginTop: 14 }}>
          {shown.map((r, i) => {
            const st = receiptStatus(r.payment);
            const act = mRowAction(r, store);
            const rid = r.payment && r.payment.receipt ? r.payment.receipt.id : null;
            const amt = r.payment
              ? r.payment.rent_amount + r.payment.charges_amount + (r.payment.services_amount || 0)
              : r.property.rent_amount + r.property.charges_amount + (r.property.services_amount || 0);
            return (
              <div key={r.tenant.id} style={{ borderTop: i ? "1px solid var(--line-soft)" : "none" }}>
                <div className="m-row">
                  <Avatar name={r.tenant.full_name} size={38} />
                  <div className="main">
                    <div className="nm">{r.tenant.full_name}</div>
                    <div className="sub">{r.property.label}</div>
                  </div>
                  <div className="trail">
                    <div className="amt num">{RENT.fmtEUR(amt)}</div>
                    <Pill kind={st.kind}>{st.short}</Pill>
                  </div>
                </div>
                {(act || rid) && (
                  <div style={{ display: "flex", gap: 8, padding: "0 16px 14px" }}>
                    {rid && (
                      <a className="m-btn sec sm" href={`/api/receipts/${rid}/pdf`} target="_blank" style={{ textDecoration: "none", flex: "none" }}>
                        <Icon name="eye" size={15} />PDF
                      </a>
                    )}
                    {rid && (
                      <button className="m-btn sec sm" style={{ flex: "none" }} onClick={() => store.removeReceipt(rid)}>
                        <Icon name="trash" size={15} />
                      </button>
                    )}
                    {act && (
                      <button className={"m-btn sm" + (act.primary ? "" : " sec")} onClick={act.fn}>
                        <Icon name={act.icon} size={15} />{act.label}
                      </button>
                    )}
                  </div>
                )}
              </div>
            );
          })}
          {shown.length === 0 && <div className="m-act" style={{ color: "var(--ink-3)", justifyContent: "center", padding: 28 }}>Aucune quittance.</div>}
        </div>
      </main>
    </>
  );
}

/* =================== PAIEMENTS =================== */
function MPaiements({ store }) {
  const period = store.period;
  const payments = store.paymentsForPeriod(period);
  return (
    <>
      <MTop title="Paiements"
        right={<button className="m-iconbtn terra" onClick={() => store.openPayment(null)} aria-label="Saisir un paiement"><Icon name="plus" size={20} /></button>}
        below={<MMonth store={store} center />} />
      <main className="m-scroll fade" key={"p" + period}>
        {payments.length === 0 ? (
          <div className="m-card pad" style={{ textAlign: "center", color: "var(--ink-3)", marginTop: 8 }}>
            <Icon name="payments" size={26} style={{ opacity: .4 }} />
            <div style={{ marginTop: 8 }}>Aucun paiement encaissé</div>
            <button className="m-btn" style={{ marginTop: 14 }} onClick={() => store.openPayment(null)}><Icon name="plus" size={16} />Saisir un paiement</button>
          </div>
        ) : (
          <div className="m-card">
            {payments.map((p, i) => {
              const t = store.tenantById(p.tenant_id), pr = store.propertyById(p.property_id);
              if (!t || !pr) return null;
              const total = p.rent_amount + p.charges_amount + (p.services_amount || 0);
              return (
                <div className="m-row" key={p.id} style={{ borderTop: i ? "1px solid var(--line-soft)" : "none" }}>
                  <Avatar name={t.full_name} size={38} />
                  <div className="main">
                    <div className="nm">{t.full_name}</div>
                    <div className="sub">{pr.label} · {RENT.fmtPeriodShort(p.period)}</div>
                  </div>
                  <div className="trail">
                    <div className="amt num">{RENT.fmtEUR(total)}</div>
                    <div className="small muted num">{RENT.fmtDateShort(p.paid_at)}</div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </main>
    </>
  );
}

/* =================== GESTION =================== */
function MGestion({ store }) {
  const [seg, setSeg] = useState("locataires");
  const addAction = seg === "locataires"
    ? <button className="m-iconbtn terra" onClick={() => store.openTenant(null)} aria-label="Nouveau locataire"><Icon name="plus" size={20} /></button>
    : <button className="m-iconbtn terra" onClick={() => store.openProperty(null)} aria-label="Nouveau bien"><Icon name="plus" size={20} /></button>;
  const segCtl = (
    <div className="m-seg">
      <button className={seg === "locataires" ? "on" : ""} onClick={() => setSeg("locataires")}>Locataires</button>
      <button className={seg === "biens" ? "on" : ""} onClick={() => setSeg("biens")}>Biens</button>
    </div>
  );
  return (
    <>
      <MTop title="Gestion" right={addAction} below={segCtl} />
      <main className="m-scroll fade" key={"g" + seg}>
        {seg === "locataires" && (
          <div className="m-card">
            {store.tenants.map((t, i) => {
              const pr = t.property_id ? store.propertyById(t.property_id) : null;
              return (
                <button className="m-row" key={t.id} onClick={() => store.openTenant(t)} style={{ width: "100%", background: "none", textAlign: "left", border: "none", borderTop: i ? "1px solid var(--line-soft)" : "none" }}>
                  <Avatar name={t.full_name} size={38} />
                  <div className="main">
                    <div className="nm">{t.full_name}</div>
                    <div className="sub mono">{t.email}</div>
                  </div>
                  <div className="trail"><div className="small muted">{pr ? pr.label : "—"}</div></div>
                  <Icon name="chevR" size={16} className="chev" />
                </button>
              );
            })}
            {store.tenants.length === 0 && <div className="m-act" style={{ color: "var(--ink-3)", justifyContent: "center", padding: 28 }}>Aucun locataire.</div>}
          </div>
        )}
        {seg === "biens" && (
          <div style={{ display: "flex", flexDirection: "column", gap: 14 }}>
            {store.properties.map(p => {
              const tenant = store.tenants.find(t => t.property_id === p.id);
              const total = p.rent_amount + p.charges_amount + (p.services_amount || 0);
              return (
                <div className="m-card" key={p.id} onClick={() => store.openProperty(p)} style={{ cursor: "pointer" }}>
                  <div style={{ padding: 16 }}>
                    <div style={{ display: "flex", alignItems: "flex-start", gap: 10 }}>
                      <div style={{ flex: 1 }}>
                        <div style={{ fontFamily: "var(--serif)", fontSize: 16.5, fontWeight: 600, letterSpacing: "-0.01em" }}>{p.label}</div>
                        <div className="small muted" style={{ marginTop: 2 }}>{p.address}</div>
                      </div>
                      <Icon name="edit" size={16} className="muted" />
                    </div>
                    <div className="m-trio">
                      <div><div className="m-eyebrow">Loyer</div><div className="figure">{RENT.fmtEUR(p.rent_amount)}</div></div>
                      <div><div className="m-eyebrow">Charges</div><div className="figure">{RENT.fmtEUR(p.charges_amount)}</div></div>
                      <div><div className="m-eyebrow">Total</div><div className="figure" style={{ color: "var(--terra-deep)" }}>{RENT.fmtEUR(total)}</div></div>
                    </div>
                    <div style={{ display: "flex", alignItems: "center", gap: 9, marginTop: 13 }}>
                      {tenant ? <><Avatar name={tenant.full_name} size={24} /><span className="small">{tenant.full_name}</span><Pill kind="ok" dot={false}>occupé</Pill></> : <Pill kind="muted">vacant</Pill>}
                    </div>
                  </div>
                </div>
              );
            })}
            {store.properties.length === 0 && <div className="m-card pad" style={{ textAlign: "center", color: "var(--ink-3)" }}>Aucun bien.</div>}
          </div>
        )}
      </main>
    </>
  );
}

/* =================== RÉGLAGES =================== */
function MReglages({ store }) {
  const [checks, setChecks] = useState([]);
  useEffect(() => {
    fetch("/api/env-check").then(r => r.json()).then(data => setChecks(Array.isArray(data) ? data : [])).catch(() => {});
  }, []);

  const owner = store.owner;
  const stateMap = { ok: "ok", warn: "warn", err: "err" };
  const stateLabel = { ok: "OK", warn: "Attention", err: "Erreur" };

  return (
    <>
      <MTop title="Réglages" />
      <main className="m-scroll fade" key="r">
        <div className="m-sectitle" style={{ marginTop: 4 }}>Environnement <span className="c mono">env-check</span></div>
        <div className="m-card">
          {checks.length === 0 && <div className="m-act" style={{ color: "var(--ink-3)", justifyContent: "center", padding: 28 }}><Icon name="refresh" size={16} className="spin" /></div>}
          {checks.map((s, i) => (
            <div className="m-env" key={i}>
              <div className="ic"><Icon name={s.icon} size={16} /></div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 14 }}>{s.name}</div>
                <div className="small muted mono" style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{s.detail}</div>
              </div>
              <div style={{ textAlign: "right" }}>
                <Pill kind={stateMap[s.state] || "muted"}>{stateLabel[s.state] || s.state}</Pill>
                <div className="small muted" style={{ marginTop: 4 }}>{s.value}</div>
              </div>
            </div>
          ))}
        </div>

        <div className="m-sectitle">Bailleur</div>
        <div className="m-card pad">
          <div style={{ display: "flex", alignItems: "center", gap: 13 }}>
            <Avatar name={owner.full_name || "—"} size={46} />
            <div style={{ minWidth: 0 }}>
              <div style={{ fontWeight: 700, fontSize: 14.5 }}>{owner.full_name || "—"}</div>
              <div className="small muted">{owner.city || ""}</div>
            </div>
          </div>
          <dl className="m-dl" style={{ marginTop: 16 }}>
            <dt>Email</dt><dd className="mono small">{owner.email || "—"}</dd>
            <dt>Adresse</dt><dd style={{ textAlign: "right" }}>{owner.address || "—"}</dd>
            <dt>Devise</dt><dd>EUR · fr-FR</dd>
            <dt>Fuseau</dt><dd>Europe/Paris</dd>
          </dl>
        </div>
      </main>
    </>
  );
}

Object.assign(window, { MBord, MQuittances, MPaiements, MGestion, MReglages, MTop, MMonth });
