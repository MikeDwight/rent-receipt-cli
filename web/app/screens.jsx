/* ============================================================
   Screens — Pilotage : Dashboard · Quittances · Paiements
   ============================================================ */

/* ---- shared row action menu ------------------------------------------- */
function RowActions({ row, store, onPreview }) {
  const { payment, tenant, property } = row;
  const st = receiptStatus(payment);
  if (!payment) {
    return <button className="btn sm ghost" onClick={() => store.openPayment(row)}><Icon name="plus" size={15} />Encaisser</button>;
  }
  if (!payment.receipt) {
    return <button className="btn sm primary" onClick={() => store.openProcess(tenant, property, "process")}><Icon name="bolt" size={15} />Quittancer</button>;
  }
  if (st.kind === "warn") {
    return <button className="btn sm" onClick={() => store.openProcess(tenant, property, "rearchive")}><Icon name="cloud" size={15} />Ré-archiver</button>;
  }
  if (st.kind === "info") {
    // PDF généré mais pas encore envoyé
    const receiptId = payment.receipt.id;
    return (
      <div className="row" style={{ gap: 6, justifyContent: "flex-end" }}>
        {receiptId && onPreview && (
          <button className="btn sm ghost icon" title="Aperçu PDF" onClick={() => onPreview(receiptId)}>
            <Icon name="eye" size={15} />
          </button>
        )}
        {receiptId && (
          <a className="btn sm ghost icon" href={`/api/receipts/${receiptId}/pdf`} download title="Télécharger" style={{ textDecoration: "none" }}>
            <Icon name="download" size={15} />
          </a>
        )}
        <button className="btn sm primary" onClick={() => store.openProcess(tenant, property, "send")}>
          <Icon name="mail" size={15} />Envoyer
        </button>
      </div>
    );
  }
  const receiptId = payment.receipt.id;
  return (
    <div className="row" style={{ gap: 6, justifyContent: "flex-end" }}>
      {receiptId && onPreview && (
        <button className="btn sm ghost icon" title="Aperçu PDF" onClick={() => onPreview(receiptId)}>
          <Icon name="eye" size={15} />
        </button>
      )}
      {receiptId && (
        <a
          className="btn sm ghost icon"
          href={`/api/receipts/${receiptId}/pdf`}
          download
          title="Télécharger la quittance PDF"
          style={{ textDecoration: "none" }}
        >
          <Icon name="download" size={15} />
        </a>
      )}
      <button className="btn sm ghost icon" title="Renvoyer au locataire" onClick={() => store.openProcess(tenant, property, "resend")}>
        <Icon name="send" size={15} />
      </button>
    </div>
  );
}

/* =======================================================================
   DASHBOARD
   ======================================================================= */
function Dashboard({ store }) {
  const period = store.period;
  const rows   = store.rowsForPeriod(period);
  const m      = store.metrics(period);
  const todo   = rows.filter(r => {
    const k = receiptStatus(r.payment).kind;
    return k === "todo" || k === "warn" || k === "err" || k === "muted";
  });
  const activity = store.recentActivity(period);

  return (
    <div className="content-inner fade-enter">
      {/* hero header */}
      <div className="between" style={{ marginBottom: 24, alignItems: "flex-end" }}>
        <div>
          <div className="eyebrow">Période en cours</div>
          <div className="row" style={{ gap: 14, marginTop: 8 }}>
            <h2 className="section-title" style={{ fontSize: 26 }}>{RENT.fmtPeriod(period)}</h2>
            <MonthStepper period={period} setPeriod={store.setPeriod} />
          </div>
        </div>
        <button className="btn primary" disabled={m.todoCount === 0} onClick={store.processAll}>
          <Icon name="bolt" size={16} />Tout quittancer{m.todoCount > 0 ? ` (${m.todoCount})` : ""}
        </button>
      </div>

      {/* KPIs */}
      <div className="kpi-grid">
        <Kpi icon="euro"  label="Loyers attendus"      value={RENT.fmtEUR0(m.expected)}
          sub={`${m.lots} lots · ${RENT.fmtEUR0(m.expected)} TTC`} />
        <Kpi icon="check" label="Encaissé"              value={RENT.fmtEUR0(m.collected)}
          pct={m.expected ? (m.collected / m.expected) * 100 : 0}
          sub={`${Math.round(m.expected ? (m.collected / m.expected) * 100 : 0)} % du loyer attendu`} />
        <Kpi icon="mail"  label="Quittances envoyées"  value={`${m.sent}/${m.lots}`}
          pct={m.lots ? (m.sent / m.lots) * 100 : 0}
          sub={m.archived === m.sent ? `${m.archived} archivées` : `${m.archived} archivées · ${m.sent - m.archived} à reprendre`} />
        <Kpi icon="bolt"  label="À traiter"             value={String(m.todoCount)}
          sub={m.todoCount === 0 ? "Tout est à jour ce mois" : "Actions en attente"}
          delta={m.todoCount === 0 ? { kind: "up", text: "✓ Mois clôturé" } : { kind: "flat", text: "Nécessite votre attention" }} />
      </div>

      <div style={{ display: "grid", gridTemplateColumns: "1.6fr 1fr", gap: 20, marginTop: 22, alignItems: "start" }}>
        {/* à traiter */}
        <div className="card">
          <div className="card-head">
            <h3>À traiter ce mois</h3>
            <div className="spacer" />
            <span className="small muted">{todo.length} sur {rows.length}</span>
          </div>
          {todo.length === 0 && (
            <div className="card-pad center" style={{ padding: "46px 24px", flexDirection: "column", gap: 12 }}>
              <div className="brand-mark" style={{ background: "var(--olive-soft)", color: "oklch(0.45 0.10 140)", width: 44, height: 44 }}>
                <Icon name="checkCircle" size={22} />
              </div>
              <div style={{ fontWeight: 600 }}>Aucune action en attente</div>
              <div className="small muted">Toutes les quittances de {RENT.fmtPeriod(period)} sont émises et archivées.</div>
            </div>
          )}
          {todo.map(r => {
            const st = receiptStatus(r.payment);
            return (
              <div className="task" key={r.tenant.id}>
                <Avatar name={r.tenant.full_name} />
                <div className="t-main">
                  <div className="t-name">{r.tenant.full_name}</div>
                  <div className="t-sub">{r.property.label} · {(r.property.address || "").split(",")[0]}</div>
                </div>
                <div style={{ textAlign: "right" }}>
                  <div className="t-amt num">{RENT.fmtEUR(r.property.rent_amount + r.property.charges_amount + (r.property.services_amount || 0))}</div>
                  <div style={{ marginTop: 5 }}><Pill kind={st.kind}>{st.label}</Pill></div>
                </div>
                <div style={{ marginLeft: 8 }}><RowActions row={r} store={store} /></div>
              </div>
            );
          })}
        </div>

        {/* activity + encaissement */}
        <div className="col" style={{ gap: 20 }}>
          <div className="card">
            <div className="card-head"><h3>Activité récente</h3></div>
            <div style={{ padding: "6px 0" }}>
              {activity.length === 0 && (
                <div className="center muted" style={{ padding: "30px 24px", fontSize: 13 }}>Aucune activité pour cette période.</div>
              )}
              {activity.map((a, i) => (
                <div className="task" key={i} style={{ padding: "12px 24px", gap: 12 }}>
                  <div className="brand-mark" style={{ width: 30, height: 30, background: a.bg, color: a.color, boxShadow: "none" }}>
                    <Icon name={a.icon} size={15} />
                  </div>
                  <div className="t-main">
                    <div style={{ fontSize: 13.5, fontWeight: 500 }}>{a.text}</div>
                    <div className="t-sub mono" style={{ fontSize: 11.5 }}>{a.when}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="card card-pad">
            <div className="between">
              <div className="eyebrow">Encaissement</div>
              <span className="small muted num">{RENT.fmtEUR0(m.collected)} / {RENT.fmtEUR0(m.expected)}</span>
            </div>
            <div className="col" style={{ gap: 11, marginTop: 16 }}>
              {store.rowsForPeriod(period).map(r => {
                const paid = !!r.payment;
                const amt  = r.property.rent_amount + r.property.charges_amount + (r.property.services_amount || 0);
                const pct  = m.maxLot ? (amt / m.maxLot) * 100 : 0;
                return (
                  <div key={r.tenant.id} className="row" style={{ gap: 12 }}>
                    <span className="small" style={{ width: 116, color: "var(--ink-2)", overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>
                      {r.property.label}
                    </span>
                    <div style={{ flex: 1, height: 8, background: "var(--paper-2)", borderRadius: 999, overflow: "hidden" }}>
                      <i style={{ display: "block", height: "100%", width: pct + "%", borderRadius: 999, background: paid ? "var(--terra)" : "var(--sand)" }} />
                    </div>
                    <span className="small num" style={{ width: 64, textAlign: "right", color: paid ? "var(--ink)" : "var(--ink-3)" }}>
                      {RENT.fmtEUR0(amt)}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* =======================================================================
   QUITTANCES
   ======================================================================= */
function Quittances({ store }) {
  const period = store.period;
  const [filter, setFilter] = useState("all");
  const [previewId, setPreviewId] = useState(null);
  const rows = store.rowsForPeriod(period);
  const counts = {
    all:     rows.length,
    todo:    rows.filter(r => ["todo", "warn", "err"].includes(receiptStatus(r.payment).kind)).length,
    done:    rows.filter(r => receiptStatus(r.payment).kind === "ok").length,
    pending: rows.filter(r => receiptStatus(r.payment).kind === "muted").length,
  };
  const shown = rows.filter(r => {
    const k = receiptStatus(r.payment).kind;
    if (filter === "todo")    return ["todo", "warn", "err"].includes(k);
    if (filter === "done")    return k === "ok";
    if (filter === "pending") return k === "muted";
    return true;
  });

  const FILTERS = [
    { id: "all",     label: "Toutes" },
    { id: "todo",    label: "À traiter" },
    { id: "done",    label: "Complètes" },
    { id: "pending", label: "En attente" },
  ];

  return (
    <div className="content-inner fade-enter">
      <div className="between" style={{ marginBottom: 20 }}>
        <MonthStepper period={period} setPeriod={store.setPeriod} />
        <div className="row" style={{ gap: 10 }}>
          <div className="seg">
            {FILTERS.map(f => (
              <button key={f.id} className={filter === f.id ? "on" : ""} onClick={() => setFilter(f.id)}>
                {f.label}
                {counts[f.id] > 0 && f.id !== "all" ? ` (${counts[f.id]})` : ""}
              </button>
            ))}
          </div>
          <button className="btn primary" disabled={counts.todo === 0} onClick={store.processAll}>
            <Icon name="bolt" size={16} />Tout quittancer
          </button>
        </div>
      </div>

      <div className="card" style={{ overflow: "hidden" }}>
        <table className="tbl">
          <thead>
            <tr>
              <th style={{ width: 30 }}>#</th>
              <th>Locataire</th>
              <th>Bien</th>
              <th className="num">Loyer + charges</th>
              <th>Statut</th>
              <th style={{ width: 160 }}>Quittance</th>
              <th style={{ width: 160, textAlign: "right" }}>Action</th>
            </tr>
          </thead>
          <tbody>
            {shown.map(r => {
              const st  = receiptStatus(r.payment);
              const rid = r.payment && r.payment.receipt ? r.payment.receipt.id : null;
              const pdfName = r.payment && r.payment.receipt && r.payment.receipt.pdf_path
                ? RENT.basename(r.payment.receipt.pdf_path)
                : null;
              return (
                <tr key={r.tenant.id}>
                  <td className="idcol">{rid || "—"}</td>
                  <td>
                    <div className="cellname">
                      <Avatar name={r.tenant.full_name} size={32} />
                      <div>
                        <div className="nm">{r.tenant.full_name}</div>
                        <div className="sub">{r.tenant.email}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div style={{ fontWeight: 500 }}>{r.property.label}</div>
                    <div className="sub small muted">{(r.property.address || "").split(",")[0]}</div>
                  </td>
                  <td className="num">{RENT.fmtEUR(r.property.rent_amount + r.property.charges_amount + (r.property.services_amount || 0))}</td>
                  <td><Pill kind={st.kind}>{st.label}</Pill></td>
                  <td>
                    {pdfName
                      ? <span className="code">{pdfName}</span>
                      : <span className="muted small">—</span>}
                  </td>
                  <td>
                    <div style={{ display: "flex", justifyContent: "flex-end" }}>
                      <RowActions row={r} store={store} onPreview={setPreviewId} />
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {shown.length === 0 && (
          <div className="center muted" style={{ padding: 50 }}>Aucune quittance pour ce filtre.</div>
        )}
      </div>

      {previewId && (
        <Modal onClose={() => setPreviewId(null)} width={860}>
          <div className="modal-head">
            <div className="row" style={{ gap: 12 }}>
              <div className="brand-mark" style={{ background: "var(--terra-soft)", color: "var(--terra-deep)" }}>
                <Icon name="receipt" size={18} />
              </div>
              <div>
                <div style={{ fontFamily: "var(--font-head)", fontSize: 18, fontWeight: 600 }}>Aperçu de la quittance</div>
                <div className="small muted">receipt #{previewId}</div>
              </div>
            </div>
          </div>
          <div style={{ padding: 0, overflow: "hidden", borderRadius: "0 0 12px 12px" }}>
            <iframe
              src={`/api/receipts/${previewId}/pdf`}
              style={{ display: "block", width: "100%", height: "72vh", border: "none" }}
              title="Aperçu quittance"
            />
          </div>
        </Modal>
      )}
    </div>
  );
}

/* =======================================================================
   PAIEMENTS
   ======================================================================= */
function Paiements({ store }) {
  const period   = store.period;
  const payments = store.paymentsForPeriod(period);
  return (
    <div className="content-inner fade-enter">
      <div className="between" style={{ marginBottom: 20 }}>
        <MonthStepper period={period} setPeriod={store.setPeriod} />
        <button className="btn primary" onClick={() => store.openPayment(null)}>
          <Icon name="plus" size={16} />Saisir un paiement
        </button>
      </div>

      <div className="card" style={{ overflow: "hidden" }}>
        <table className="tbl">
          <thead>
            <tr>
              <th style={{ width: 40 }}>#</th>
              <th>Locataire</th>
              <th>Bien</th>
              <th>Période</th>
              <th className="num">Loyer</th>
              <th className="num">Charges</th>
              <th className="num">Services</th>
              <th className="num">Total</th>
              <th>Encaissé le</th>
              <th style={{ width: 110, textAlign: "right" }}></th>
            </tr>
          </thead>
          <tbody>
            {payments.map(p => {
              const t  = store.tenantById(p.tenant_id);
              const pr = store.propertyById(p.property_id);
              if (!t || !pr) return null;
              const services = p.services_amount || 0;
              const total    = p.rent_amount + p.charges_amount + services;
              const row      = { tenant: t, property: pr, payment: p };
              return (
                <tr key={p.id}>
                  <td className="idcol">{p.id}</td>
                  <td>
                    <div className="cellname">
                      <Avatar name={t.full_name} size={30} />
                      <span className="nm">{t.full_name}</span>
                    </div>
                  </td>
                  <td>{pr.label}</td>
                  <td><span className="code">{p.period}</span></td>
                  <td className="num">{RENT.fmtEUR(p.rent_amount)}</td>
                  <td className="num">{RENT.fmtEUR(p.charges_amount)}</td>
                  <td className="num">{services > 0 ? RENT.fmtEUR(services) : <span className="muted">—</span>}</td>
                  <td className="num" style={{ fontWeight: 700 }}>{RENT.fmtEUR(total)}</td>
                  <td className="num">{RENT.fmtDateShort(p.paid_at)}</td>
                  <td>
                    <div className="row" style={{ gap: 4, justifyContent: "flex-end" }}>
                      {!p.receipt && (
                        <button className="btn sm ghost icon" title="Quittancer" onClick={() => store.openProcess(t, pr, "process")}>
                          <Icon name="bolt" size={15} />
                        </button>
                      )}
                      <button className="btn sm ghost icon" title="Modifier" onClick={() => store.openPayment(row)}>
                        <Icon name="edit" size={15} />
                      </button>
                      <button className="btn sm ghost icon" title="Supprimer" onClick={() => store.removePayment(p)}>
                        <Icon name="trash" size={15} />
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {payments.length === 0 && (
          <div className="center muted" style={{ padding: 50, flexDirection: "column", gap: 10 }}>
            <Icon name="payments" size={26} style={{ opacity: .4 }} />
            <div>Aucun paiement encaissé pour {RENT.fmtPeriod(period)}.</div>
            <button className="btn sm" onClick={() => store.openPayment(null)}>
              <Icon name="plus" size={14} />Saisir un paiement
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

window.Dashboard  = Dashboard;
window.Quittances = Quittances;
window.Paiements  = Paiements;
window.RowActions = RowActions;
