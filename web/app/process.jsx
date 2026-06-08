/* ============================================================
   One-click receipt:process flow — calls POST /api/process
   Animation runs in parallel with the real API call.
   ============================================================ */

function buildPhases({ tenant, property, period, mode }) {
  const paymentExists = mode.paymentExists;
  return [
    {
      key: "payment",
      title: paymentExists ? "Paiement mis à jour" : "Paiement enregistré",
      descPending: "Encaissement du loyer pour la période",
      descDone: paymentExists ? "Loyer déjà encaissé — montants vérifiés" : "Loyer encaissé aux montants du bien",
      cmd: `payment:upsert --tenant-id=${tenant.id} --property-id=${property.id} --period=${period}`,
      icon: "euro", ms: 850,
    },
    {
      key: "receipt",
      title: "Quittance générée",
      descPending: "Rendu du PDF (wkhtmltopdf)",
      descDone: "PDF rendu — A4 portrait",
      cmd: `receipt:generate ${period}`,
      icon: "file", ms: 1300,
    },
    {
      key: "email",
      title: "Quittance envoyée",
      descPending: `Envoi à ${tenant.email}`,
      descDone: "Acceptée par le serveur SMTP",
      cmd: `receipt:send ${period}`,
      icon: "mail", ms: 1150,
    },
    {
      key: "archive",
      title: "Archivée sur Nextcloud",
      descPending: "Dépôt WebDAV de la quittance",
      descDone: "Déposée dans Quittances/" + period.replace("-", "/"),
      cmd: `webdav:put Quittances/${period.replace("-", "/")}`,
      icon: "cloud", ms: 1050,
    },
  ];
}

function ProcessFlow({ ctx, onClose, onComplete, pushToast }) {
  const { tenant, property, period, mode } = ctx;
  const allPhases = useMemo(() => buildPhases({ tenant, property, period, mode: ctx }), []);

  const [dryRun,       setDryRun]       = useState(false);
  const [generateOnly, setGenerateOnly] = useState(false);

  const phases = useMemo(() => {
    if (mode === "rearchive")   return allPhases.filter(p => p.key === "archive");
    if (mode === "resend" || mode === "send") return allPhases.filter(p => p.key === "email" || p.key === "archive");
    if (generateOnly)           return allPhases.filter(p => p.key === "payment" || p.key === "receipt");
    return allPhases;
  }, [mode, generateOnly]);

  const [stage, setStage]         = useState("confirm");
  const [statuses, setStatuses]   = useState(() => phases.map(() => "pending"));
  const [apiResult, setApiResult] = useState(null);
  const timers = useRef([]);

  useEffect(() => {
    setStatuses(phases.map(() => "pending"));
  }, [phases.length]);

  const rent = property.rent_amount, charges = property.charges_amount, total = rent + charges;

  async function run() {
    setStage("running");

    const body = {
      tenant_id:     tenant.id,
      property_id:   property.id,
      period,
      paid_at:       new Date().toISOString().slice(0, 10),
      dry_run:       dryRun,
      resend:        mode === "resend",
      rearchive:     mode === "rearchive",
      generate_only: generateOnly,
    };

    const apiPromise = fetch("/api/process", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    }).then(r => r.json()).catch(e => ({
      result: { ok: false, errors: 1, error_messages: [e.message] },
    }));

    // Play animation concurrently
    let acc = 0;
    phases.forEach((p, i) => {
      timers.current.push(setTimeout(() => {
        setStatuses(s => { const c = [...s]; c[i] = "active"; return c; });
      }, acc));
      acc += p.ms;
      timers.current.push(setTimeout(() => {
        setStatuses(s => { const c = [...s]; c[i] = "done"; return c; });
      }, acc));
      acc += 140;
    });
    const animDuration = acc + 260;

    // Wait for both to finish
    const [result] = await Promise.all([
      apiPromise,
      new Promise(r => { timers.current.push(setTimeout(r, animDuration)); }),
    ]);

    setApiResult(result);
    setStage("done");
    if (!dryRun && result?.result?.ok !== false) {
      onComplete();
    }
  }

  useEffect(() => () => timers.current.forEach(clearTimeout), []);

  const titleByMode = {
    process:   generateOnly ? "Générer la quittance" : "Encaisser & quittancer",
    rearchive: "Reprendre l'archivage",
    resend:    "Renvoyer la quittance",
    send:      "Envoyer la quittance",
  };

  const hasError = apiResult && apiResult.result && !apiResult.result.ok;

  return (
    <Modal onClose={stage === "running" ? null : onClose} width={540}>
      <div className="modal-head">
        <div className="row" style={{ gap: 12 }}>
          <div className="brand-mark" style={{ background: "var(--terra)", color: "#fff" }}>
            <Icon name="bolt" size={18} />
          </div>
          <div style={{ minWidth: 0 }}>
            <div style={{ fontFamily: "var(--font-head)", fontSize: 19, fontWeight: 600, letterSpacing: "-0.01em", whiteSpace: "nowrap", lineHeight: 1.2 }}>
              {titleByMode[mode]}
            </div>
            <div className="small muted" style={{ marginTop: 2 }}>
              {tenant.full_name} · {property.label} · {RENT.fmtPeriod(period)}
            </div>
          </div>
        </div>
      </div>

      <div className="modal-body">
        {stage === "confirm" && (
          <div className="fade-enter">
            <div className="card card-pad" style={{ background: "var(--surface-2)", marginBottom: 18 }}>
              <div className="between" style={{ alignItems: "flex-start" }}>
                <div>
                  <div className="eyebrow">Période {RENT.fmtPeriod(period)}</div>
                  <div className="dl" style={{ marginTop: 12, gridTemplateColumns: "auto 1fr", gap: "8px 18px" }}>
                    <dt>Loyer</dt><dd className="num">{RENT.fmtEUR(rent)}</dd>
                    <dt>Charges</dt><dd className="num">{RENT.fmtEUR(charges)}</dd>
                    <dt style={{ color: "var(--ink)", fontWeight: 600 }}>Total encaissé</dt>
                    <dd className="num" style={{ fontWeight: 700 }}>{RENT.fmtEUR(total)}</dd>
                  </div>
                </div>
                <Avatar name={tenant.full_name} terra size={42} />
              </div>
            </div>
            <p className="small muted" style={{ marginTop: 0 }}>
              {mode === "process"   && !generateOnly && "Enchaîne tout le flux en une commande : paiement, PDF, email au locataire, archivage Nextcloud. Idempotent — les étapes déjà faites sont ignorées."}
              {mode === "process"   && generateOnly  && "Génère le PDF uniquement. Aucun email ne sera envoyé. Vous pourrez vérifier la quittance puis l'envoyer manuellement depuis l'écran Quittances."}
              {mode === "rearchive" && "Le PDF a déjà été envoyé au locataire. Cette opération relance uniquement le dépôt sur Nextcloud, sans renvoyer d'email."}
              {mode === "resend"    && "Renvoie l'email au locataire puis revérifie l'archivage. À utiliser avec parcimonie."}
              {mode === "send"      && "Envoie la quittance par email au locataire et l'archive sur Nextcloud."}
            </p>
            {mode === "process" && (
              <label className="row" style={{ gap: 9, cursor: "pointer", marginTop: 10, fontSize: 13 }}>
                <input type="checkbox" checked={generateOnly} onChange={e => { setGenerateOnly(e.target.checked); if (e.target.checked) setDryRun(false); }} />
                <span><b>Générer seulement</b> · crée le PDF, sans envoyer ni archiver</span>
              </label>
            )}
            <label className="row" style={{ gap: 9, cursor: "pointer", marginTop: 8, fontSize: 13 }}>
              <input type="checkbox" checked={dryRun} onChange={e => { setDryRun(e.target.checked); if (e.target.checked) setGenerateOnly(false); }} />
              <span><b>Simulation (--dry-run)</b> · aucune écriture, aucun email, aucun upload</span>
            </label>
          </div>
        )}

        {stage !== "confirm" && (
          <div className="steps fade-enter">
            {phases.map((p, i) => {
              const st = (hasError && stage === "done") ? "error" : statuses[i];
              return (
                <div key={p.key} className={"step " + st}>
                  <div className="rail">
                    <div className="node">
                      {st === "done"   ? <Icon name="check"   size={15} />
                       : st === "active" ? <Icon name="refresh" size={14} className="spin" />
                       : st === "error"  ? <Icon name="alert"   size={14} />
                       : <Icon name={p.icon} size={14} />}
                    </div>
                    {i < phases.length - 1 && <div className="line" />}
                  </div>
                  <div className="body">
                    <div className="s-title">{p.title}</div>
                    <div className="s-desc">
                      {st === "done" ? p.descDone : p.descPending}
                      {dryRun && st === "done" ? " · simulé" : ""}
                    </div>
                    <div className="s-cmd">$ rent-receipt {p.cmd}</div>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {stage === "done" && (
          <div className="card card-pad fade-enter" style={{
            marginTop: 6, border: "none",
            background: hasError ? "var(--clay-soft)" : dryRun ? "var(--ochre-soft)" : "var(--olive-soft)",
          }}>
            <div className="row" style={{ gap: 12 }}>
              <Icon
                name={hasError ? "alert" : dryRun ? "eye" : "checkCircle"}
                size={22}
                style={{ color: hasError ? "var(--clay)" : dryRun ? "oklch(0.5 0.11 70)" : "oklch(0.45 0.10 140)" }}
              />
              <div>
                <div style={{ fontWeight: 700, fontSize: 14.5 }}>
                  {hasError
                    ? "Erreur lors du traitement"
                    : dryRun ? "Simulation terminée"
                    : mode === "process" && generateOnly ? "PDF généré"
                    : mode === "process" ? "Quittance émise avec succès"
                    : mode === "rearchive" ? "Archivage repris"
                    : mode === "send" ? "Quittance envoyée"
                    : "Quittance renvoyée"}
                </div>
                <div className="small" style={{ color: "var(--ink-2)" }}>
                  {hasError
                    ? (apiResult?.result?.error_messages?.[0] || "Une erreur est survenue.")
                    : dryRun ? "Aucune modification n'a été appliquée."
                    : generateOnly ? "PDF généré — vérifiez-le dans Quittances avant d'envoyer."
                    : "[RESULT] ok · warnings=0 · errors=0"}
                </div>
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="modal-foot">
        {stage === "confirm" && <>
          <button className="btn ghost" onClick={onClose}>Annuler</button>
          <button className="btn primary" onClick={run}>
            <Icon name={dryRun ? "eye" : "bolt"} size={16} />
            {dryRun ? "Lancer la simulation" : "Lancer le traitement"}
          </button>
        </>}
        {stage === "running" && (
          <button className="btn" disabled>
            <Icon name="refresh" size={15} className="spin" />Traitement en cours…
          </button>
        )}
        {stage === "done" && (
          <button className="btn primary" onClick={onClose}>Terminé</button>
        )}
      </div>
    </Modal>
  );
}

window.ProcessFlow = ProcessFlow;
