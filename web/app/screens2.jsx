/* ============================================================
   Screens — Référentiel : Locataires · Biens · Réglages
   + edit drawers / payment modal
   ============================================================ */

/* =======================================================================
   LOCATAIRES
   ======================================================================= */
function Locataires({ store }) {
  return (
    <div className="content-inner fade-enter">
      <div className="between" style={{ marginBottom: 20 }}>
        <span className="small muted">{store.tenants.length} locataires actifs</span>
        <button className="btn primary" onClick={() => store.openTenant(null)}>
          <Icon name="plus" size={16} />Nouveau locataire
        </button>
      </div>
      <div className="card" style={{ overflow: "hidden" }}>
        <table className="tbl">
          <thead>
            <tr>
              <th style={{ width: 40 }}>#</th>
              <th>Locataire</th>
              <th>Email</th>
              <th>Bien occupé</th>
              <th>Depuis</th>
              <th style={{ width: 90, textAlign: "right" }}></th>
            </tr>
          </thead>
          <tbody>
            {store.tenants.map(t => {
              const pr = t.property_id ? store.propertyById(t.property_id) : null;
              return (
                <tr key={t.id}>
                  <td className="idcol">{t.id}</td>
                  <td>
                    <div className="cellname">
                      <Avatar name={t.full_name} size={32} terra />
                      <div>
                        <div className="nm">{t.full_name}</div>
                        <div className="sub">{(t.address || "").split(",")[0]}</div>
                      </div>
                    </div>
                  </td>
                  <td className="mono small" style={{ color: "var(--ink-2)" }}>{t.email}</td>
                  <td>{pr ? pr.label : <span className="muted">—</span>}</td>
                  <td className="num small muted">{RENT.fmtDateShort(t.created_at)}</td>
                  <td>
                    <div className="row" style={{ gap: 4, justifyContent: "flex-end" }}>
                      <button className="btn sm ghost icon" title="Modifier" onClick={() => store.openTenant(t)}>
                        <Icon name="edit" size={15} />
                      </button>
                      <button className="btn sm ghost icon" title="Supprimer" onClick={() => store.removeTenant(t)}>
                        <Icon name="trash" size={15} />
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {store.tenants.length === 0 && (
          <div className="center muted" style={{ padding: 50 }}>Aucun locataire enregistré.</div>
        )}
      </div>
    </div>
  );
}

/* =======================================================================
   BIENS  (card grid)
   ======================================================================= */
function Biens({ store }) {
  return (
    <div className="content-inner fade-enter">
      <div className="between" style={{ marginBottom: 20 }}>
        <span className="small muted">{store.properties.length} biens en gestion</span>
        <button className="btn primary" onClick={() => store.openProperty(null)}>
          <Icon name="plus" size={16} />Nouveau bien
        </button>
      </div>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(2, 1fr)", gap: 18 }}>
        {store.properties.map(p => {
          const tenant = store.tenants.find(t => t.property_id === p.id);
          const total  = p.rent_amount + p.charges_amount;
          return (
            <div className="card" key={p.id} style={{ overflow: "hidden" }}>
              <ImgSlot label={`photo · ${p.label}`} h={108} />
              <div className="card-pad">
                <div className="between" style={{ alignItems: "flex-start" }}>
                  <div>
                    <div style={{ fontFamily: "var(--font-head)", fontSize: 17, fontWeight: 600, letterSpacing: "-0.01em" }}>{p.label}</div>
                    <div className="small muted" style={{ marginTop: 3 }}>{p.address}</div>
                  </div>
                  <button className="btn sm ghost icon" onClick={() => store.openProperty(p)} title="Modifier">
                    <Icon name="edit" size={15} />
                  </button>
                </div>
                <div className="row" style={{ gap: 8, marginTop: 16, paddingTop: 16, borderTop: "1px solid var(--line)" }}>
                  <div style={{ flex: 1 }}>
                    <div className="eyebrow">Loyer</div>
                    <div className="figure" style={{ fontSize: 18, marginTop: 4 }}>{RENT.fmtEUR(p.rent_amount)}</div>
                  </div>
                  <div style={{ flex: 1 }}>
                    <div className="eyebrow">Charges</div>
                    <div className="figure" style={{ fontSize: 18, marginTop: 4 }}>{RENT.fmtEUR(p.charges_amount)}</div>
                  </div>
                  <div style={{ flex: 1 }}>
                    <div className="eyebrow">Total TTC</div>
                    <div className="figure" style={{ fontSize: 18, marginTop: 4, color: "var(--terra-deep)" }}>{RENT.fmtEUR(total)}</div>
                  </div>
                </div>
                <div className="row" style={{ gap: 9, marginTop: 14 }}>
                  {tenant
                    ? <><Avatar name={tenant.full_name} size={26} /><span className="small">{tenant.full_name}</span><Pill kind="ok" dot={false}>occupé</Pill></>
                    : <Pill kind="muted">vacant</Pill>}
                </div>
              </div>
            </div>
          );
        })}
      </div>
      {store.properties.length === 0 && (
        <div className="card center muted" style={{ padding: 60 }}>Aucun bien enregistré.</div>
      )}
    </div>
  );
}

/* =======================================================================
   RÉGLAGES  — câblé sur GET /api/env-check
   ======================================================================= */
function Reglages({ store }) {
  const owner = store.owner;
  const [services, setServices]       = useState([]);
  const [checkLoading, setCheckLoading] = useState(true);

  useEffect(() => {
    fetch("/api/env-check")
      .then(r => r.json())
      .then(data => { setServices(Array.isArray(data) ? data : []); setCheckLoading(false); })
      .catch(() => setCheckLoading(false));
  }, []);

  const stateMap = {
    ok:   { kind: "ok",   label: "OK" },
    warn: { kind: "warn", label: "Attention" },
    err:  { kind: "err",  label: "Erreur" },
  };

  return (
    <div className="content-inner fade-enter">
      <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 20, alignItems: "start" }}>
        {/* env check */}
        <div className="card" style={{ overflow: "hidden" }}>
          <div className="card-head">
            <h3>État de l'environnement</h3>
            <div className="spacer" />
            <span className="code">receipt:env:check</span>
          </div>
          {checkLoading && (
            <div className="center muted" style={{ padding: 40 }}>
              <Icon name="refresh" size={20} className="spin" style={{ opacity: 0.4 }} />
            </div>
          )}
          {!checkLoading && services.map((s, i) => (
            <div className="task" key={i}>
              <div className="brand-mark" style={{ width: 34, height: 34, background: "var(--paper-2)", color: "var(--ink-2)", boxShadow: "none" }}>
                <Icon name={s.icon} size={16} />
              </div>
              <div className="t-main">
                <div className="t-name" style={{ fontSize: 14 }}>{s.name}</div>
                <div className="t-sub mono" style={{ fontSize: 11.5 }}>{s.detail}</div>
              </div>
              <div style={{ textAlign: "right" }}>
                <Pill kind={(stateMap[s.state] || stateMap.warn).kind}>
                  {(stateMap[s.state] || stateMap.warn).label}
                </Pill>
                <div className="small muted" style={{ marginTop: 5 }}>{s.value}</div>
              </div>
            </div>
          ))}
        </div>

        {/* owner + template cards */}
        <div className="col" style={{ gap: 20 }}>
          <div className="card card-pad">
            <div className="between">
              <div className="eyebrow">Bailleur</div>
            </div>
            <div className="row" style={{ gap: 14, marginTop: 16 }}>
              <Avatar name={owner.full_name || "?"} terra size={48} />
              <div>
                <div style={{ fontWeight: 700, fontSize: 15 }}>{owner.full_name || "—"}</div>
                <div className="small muted">{owner.address || "—"}</div>
              </div>
            </div>
            <dl className="dl" style={{ marginTop: 20 }}>
              <dt>Email</dt><dd className="mono small">{owner.email || "—"}</dd>
              <dt>Commune</dt><dd>{owner.city || "—"}</dd>
              <dt>Devise</dt><dd>Euro (EUR) · format fr-FR</dd>
              <dt>Fuseau</dt><dd>Europe/Paris</dd>
            </dl>
          </div>
          <div className="card card-pad">
            <div className="eyebrow">Modèle de quittance</div>
            <div className="receipt-mini" style={{ marginTop: 14 }}>
              <h4>Quittance de loyer</h4>
              <div style={{ color: "#8a7d70", fontSize: 10 }}>templates/receipt.html · A4 portrait</div>
              <div style={{ marginTop: 10, display: "grid", gridTemplateColumns: "1fr 1fr", gap: 4 }}>
                <span>Loyer</span>    <span style={{ textAlign: "right" }}>490,00 €</span>
                <span>Charges</span>  <span style={{ textAlign: "right" }}>50,00 €</span>
                <span style={{ fontWeight: 700 }}>Total</span>
                <span style={{ textAlign: "right", fontWeight: 700 }}>540,00 €</span>
              </div>
            </div>
            <div style={{ marginTop: 14 }}>
              <a href="/receipts/preview" target="_blank" rel="noopener noreferrer" className="btn sm ghost">
                <Icon name="eye" size={14} />Aperçu
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* =======================================================================
   EDIT FORMS  (drawers + payment modal)
   ======================================================================= */
function TenantDrawer({ tenant, store }) {
  const isNew = !tenant;
  const [f, setF] = useState(() => tenant
    ? { ...tenant }
    : { full_name: "", email: "", address: "", property_id: store.properties[0] ? store.properties[0].id : null }
  );
  const set   = (k, v) => setF(s => ({ ...s, [k]: v }));
  const valid = f.full_name.trim() && /\S+@\S+\.\S+/.test(f.email) && f.address.trim();

  return (
    <Drawer
      icon="tenants"
      title={isNew ? "Nouveau locataire" : "Modifier le locataire"}
      sub={isNew ? "tenant:upsert" : `tenant #${tenant.id}`}
      onClose={store.closeDrawer}
      footer={<>
        <button className="btn ghost" onClick={store.closeDrawer}>Annuler</button>
        <button className="btn primary" disabled={!valid} onClick={() => store.saveTenant(f)}>
          <Icon name="check" size={15} />{isNew ? "Créer" : "Enregistrer"}
        </button>
      </>}
    >
      <Field label="Nom complet">
        <input className="input" value={f.full_name} onChange={e => set("full_name", e.target.value)} placeholder="Jean Dupont" />
      </Field>
      <Field label="Email" hint="Adresse à laquelle la quittance sera envoyée.">
        <input className="input" value={f.email} onChange={e => set("email", e.target.value)} placeholder="jean.dupont@example.com" />
      </Field>
      <Field label="Adresse">
        <textarea className="textarea" value={f.address} onChange={e => set("address", e.target.value)} placeholder="401 rue Costa de Beauregard, 73000 Chambéry" />
      </Field>
      {store.properties.length > 0 && (
        <Field label="Bien occupé (informatif)">
          <select className="select" value={f.property_id || ""} onChange={e => set("property_id", Number(e.target.value))}>
            <option value="">— aucun —</option>
            {store.properties.map(p => <option key={p.id} value={p.id}>{p.label}</option>)}
          </select>
        </Field>
      )}
    </Drawer>
  );
}

function PropertyDrawer({ property, store }) {
  const isNew = !property;
  const toE   = (c) => c ? (c / 100).toString().replace(".", ",") : "";
  const [f, setF] = useState(() => property
    ? { label: property.label, address: property.address, rent: toE(property.rent_amount), charges: toE(property.charges_amount) }
    : { label: "", address: "", rent: "", charges: "" }
  );
  const set    = (k, v) => setF(s => ({ ...s, [k]: v }));
  const cents  = (s) => Math.round(parseFloat(String(s).replace(",", ".") || "0") * 100);
  const total  = cents(f.rent) + cents(f.charges);
  const valid  = f.label.trim() && f.address.trim() && cents(f.rent) > 0;

  return (
    <Drawer
      icon="building"
      title={isNew ? "Nouveau bien" : "Modifier le bien"}
      sub={isNew ? "property:upsert" : `property #${property.id}`}
      onClose={store.closeDrawer}
      footer={<>
        <button className="btn ghost" onClick={store.closeDrawer}>Annuler</button>
        <button className="btn primary" disabled={!valid}
          onClick={() => store.saveProperty(property, { label: f.label, address: f.address, rent_amount: cents(f.rent), charges_amount: cents(f.charges) })}>
          <Icon name="check" size={15} />{isNew ? "Créer" : "Enregistrer"}
        </button>
      </>}
    >
      <Field label="Libellé">
        <input className="input" value={f.label} onChange={e => set("label", e.target.value)} placeholder="Studio JJ. Rousseau" />
      </Field>
      <Field label="Adresse">
        <textarea className="textarea" value={f.address} onChange={e => set("address", e.target.value)} placeholder="401 rue Costa de Beauregard, 73000 Chambéry" />
      </Field>
      <div className="field-2">
        <Field label="Loyer (hors charges)"><MoneyInput value={f.rent}    onChange={v => set("rent",    v)} /></Field>
        <Field label="Charges">             <MoneyInput value={f.charges} onChange={v => set("charges", v)} /></Field>
      </div>
      <div className="card card-pad" style={{ background: "var(--surface-2)", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <span className="eyebrow">Total mensuel TTC</span>
        <span className="figure" style={{ fontSize: 22, color: "var(--terra-deep)" }}>{RENT.fmtEUR(total)}</span>
      </div>
    </Drawer>
  );
}

function PaymentModal({ row, store }) {
  const tenantsWithProp = store.tenants.filter(t => t.property_id);
  if (!tenantsWithProp.length) {
    return (
      <Modal onClose={store.closeModal} width={520}>
        <div className="modal-head"><div className="row" style={{ gap: 12 }}><Icon name="alert" size={20} /><div>Aucun locataire avec un bien assigné.</div></div></div>
        <div className="modal-foot"><button className="btn ghost" onClick={store.closeModal}>Fermer</button></div>
      </Modal>
    );
  }

  const existingPayment = row && row.payment ? row.payment : null;
  const isEdit          = !!existingPayment;
  const presetTenant    = row && row.tenant ? row.tenant : tenantsWithProp[0];
  const [tenantId, setTenantId] = useState(presetTenant.id);
  const tenant   = store.tenantById(tenantId) || tenantsWithProp[0];
  const property = store.propertyById(tenant.property_id) || store.properties[0];

  const toE = (c) => c != null ? (c / 100).toString().replace(".", ",") : "0";
  const [rent,     setRent]     = useState(toE(isEdit ? existingPayment.rent_amount     : (property ? property.rent_amount     : 0)));
  const [charges,  setCharges]  = useState(toE(isEdit ? existingPayment.charges_amount  : (property ? property.charges_amount  : 0)));
  const [services, setServices] = useState(toE(isEdit ? existingPayment.services_amount : 0));
  const [paidAt,   setPaidAt]   = useState(
    isEdit ? existingPayment.paid_at : (store.period + "-" + String(new Date().getDate()).padStart(2, "0"))
  );

  useEffect(() => {
    if (isEdit) return;
    const p = store.propertyById(tenant.property_id) || store.properties[0];
    if (p) { setRent(toE(p.rent_amount)); setCharges(toE(p.charges_amount)); setServices("0"); }
  }, [tenantId]);

  const cents = (s) => Math.round(parseFloat(String(s).replace(",", ".") || "0") * 100);
  const total = cents(rent) + cents(charges) + cents(services);

  return (
    <Modal onClose={store.closeModal} width={520}>
      <div className="modal-head">
        <div className="row" style={{ gap: 12 }}>
          <div className="brand-mark" style={{ background: "var(--terra-soft)", color: "var(--terra-deep)" }}>
            <Icon name="euro" size={18} />
          </div>
          <div>
            <div style={{ fontFamily: "var(--font-head)", fontSize: 19, fontWeight: 600 }}>
              {isEdit ? "Modifier le paiement" : "Saisir un paiement"}
            </div>
            <div className="small muted">payment:upsert · {RENT.fmtPeriod(store.period)}</div>
          </div>
        </div>
      </div>
      <div className="modal-body">
        <Field label="Locataire">
          <select className="select" value={tenantId} onChange={e => setTenantId(Number(e.target.value))} disabled={isEdit}>
            {tenantsWithProp.map(t => {
              const p = store.propertyById(t.property_id);
              return <option key={t.id} value={t.id}>{t.full_name}{p ? ` — ${p.label}` : ""}</option>;
            })}
          </select>
        </Field>
        {property && (
          <div className="card card-pad" style={{ background: "var(--surface-2)", marginBottom: 18 }}>
            <div className="row" style={{ gap: 12 }}>
              <Icon name="building" size={16} style={{ color: "var(--ink-3)", flexShrink: 0 }} />
              <div>
                <div style={{ fontWeight: 600, fontSize: 13.5 }}>{property.label}</div>
                <div className="small muted">{property.address}</div>
              </div>
            </div>
          </div>
        )}
        <div className="field-2">
          <Field label="Loyer">  <MoneyInput value={rent}     onChange={setRent}     /></Field>
          <Field label="Charges"><MoneyInput value={charges}  onChange={setCharges}  /></Field>
        </div>
        <Field label="Services résidence" hint="Charges de résidence variables (optionnel).">
          <MoneyInput value={services} onChange={setServices} />
        </Field>
        <Field label="Encaissé le">
          <input className="input" type="date" value={paidAt} onChange={e => setPaidAt(e.target.value)} />
        </Field>
        <div className="card card-pad" style={{ background: "var(--terra-soft)", display: "flex", justifyContent: "space-between", alignItems: "center", border: "none" }}>
          <span className="eyebrow" style={{ color: "var(--terra-deep)" }}>Total encaissé</span>
          <span className="figure" style={{ fontSize: 22, color: "var(--terra-deep)" }}>{RENT.fmtEUR(total)}</span>
        </div>
      </div>
      <div className="modal-foot">
        <button className="btn ghost" onClick={store.closeModal}>Annuler</button>
        <button className="btn primary" onClick={() => store.savePayment({
          ...(isEdit ? { id: existingPayment.id } : {}),
          tenant_id:      tenantId,
          property_id:    property ? property.id : null,
          period:         store.period,
          rent_amount:    cents(rent),
          charges_amount: cents(charges),
          services_amount: cents(services),
          paid_at:        paidAt,
        })}>
          <Icon name="check" size={15} />{isEdit ? "Enregistrer les modifications" : "Enregistrer le paiement"}
        </button>
      </div>
    </Modal>
  );
}

Object.assign(window, { Locataires, Biens, Reglages, TenantDrawer, PropertyDrawer, PaymentModal });
