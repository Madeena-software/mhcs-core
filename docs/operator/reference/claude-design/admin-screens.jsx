// Admin screens — Organizations, Projects, Participants
const { useState: useStateOP, useMemo: useMemoOP, useEffect: useEffectOP } = React;

// ============================================================
//  ORGANIZATIONS
// ============================================================
function OrganizationsScreen({ showSheet }) {
  const { ORGS, projectsByOrg } = window.MMSSAdminData;
  const [search, setSearch] = useStateOP("");
  const [typeFilter, setTypeFilter] = useStateOP("all");

  const filtered = ORGS.filter(o => {
    if (typeFilter !== "all" && o.type !== typeFilter) return false;
    if (search && !o.name.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  return (
    <div className="main-body">
      <div className="summary-row">
        <SummaryPill label="Total" value={ORGS.length} />
        <SummaryPill label="Active" value={ORGS.filter(o => o.status === "active").length} />
        <SummaryPill label="Pending MoU" value={ORGS.filter(o => o.status === "pending").length} />
        <SummaryPill label="Completed" value={ORGS.filter(o => o.status === "completed").length} />
      </div>

      <div className="section-card">
        <div className="section-card-header">
          <div>
            <div className="section-card-title">Partner organizations</div>
            <div className="section-card-sub">Each organization may run one or more screening programs</div>
          </div>
          <div className="section-card-actions" style={{gap: 8}}>
            <div className="search-input" style={{padding: "6px 10px"}}>
              {Icons.search}
              <input type="text" placeholder="Search organizations…" value={search} onChange={e => setSearch(e.target.value)} />
            </div>
            <select className="select" value={typeFilter} onChange={e => setTypeFilter(e.target.value)} style={{padding: "7px 10px"}}>
              <option value="all">All types</option>
              <option value="pesantren">Pesantren</option>
              <option value="school">School</option>
              <option value="corporate">Corporate</option>
              <option value="government">Government</option>
            </select>
            <button className="btn btn-primary btn-sm" onClick={() => showSheet({kind: "org-new"})}>
              {Icons.plus} New organization
            </button>
          </div>
        </div>
        <table className="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Type</th>
              <th>Identity</th>
              <th>City</th>
              <th>Participants</th>
              <th>Programs</th>
              <th>MoU date</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(o => {
              const projects = projectsByOrg(o.id);
              return (
                <tr key={o.id}>
                  <td>
                    <div className="cell-strong">{o.name}</div>
                    <div className="cell-mono" style={{fontSize: 11}}>{o.id}</div>
                  </td>
                  <td><OrgTypeChip type={o.type} /></td>
                  <td className="cell-mono">{o.identityType}</td>
                  <td>{o.city}</td>
                  <td className="cell-mono">{o.totalParticipants.toLocaleString("id-ID")}</td>
                  <td className="cell-mono">{projects.length}</td>
                  <td className="cell-mono">{o.mou}</td>
                  <td><OrgStatusBadge status={o.status} /></td>
                  <td className="cell-right">
                    <button className="btn btn-ghost btn-sm" onClick={() => showSheet({kind: "org-detail", id: o.id})}>
                      Open →
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="table-empty">No organizations match your filter.</div>}
      </div>
    </div>
  );
}

function OrganizationDetailSheet({ orgId, onClose, showSheet }) {
  const { orgById, projectsByOrg } = window.MMSSAdminData;
  const o = orgById(orgId);
  if (!o) return null;
  const projects = projectsByOrg(orgId);
  return (
    <>
      <div className="field">
        <div style={{display: "flex", alignItems: "center", gap: 10}}>
          <OrgTypeChip type={o.type} />
          <OrgStatusBadge status={o.status} />
          <span className="cell-mono" style={{fontSize: 11, color: "var(--fg-3)"}}>{o.id}</span>
        </div>
        <div style={{fontSize: 18, fontWeight: 600, marginTop: 4}}>{o.name}</div>
      </div>

      <div className="field-row">
        <DetailField label="City" value={o.city} />
        <DetailField label="MoU signed" value={o.mou} mono />
      </div>
      <div className="field-row">
        <DetailField label="Identity type" value={o.identityType} mono />
        <DetailField label="Total participants" value={o.totalParticipants.toLocaleString("id-ID")} mono />
      </div>

      <div>
        <div className="field-label" style={{marginBottom: 6}}>Primary contact</div>
        <div style={{display: "flex", flexDirection: "column", gap: 4, fontSize: 13}}>
          <div>{o.contact}</div>
          <div style={{color: "var(--fg-3)", fontFamily: "var(--font-mono)", fontSize: 12}}>{o.contactEmail}</div>
          <div style={{color: "var(--fg-3)", fontFamily: "var(--font-mono)", fontSize: 12}}>{o.contactPhone}</div>
        </div>
      </div>

      <div>
        <div className="field-label" style={{marginBottom: 8}}>Screening programs ({projects.length})</div>
        {projects.length === 0 ? (
          <div className="table-empty" style={{padding: 20}}>No programs yet</div>
        ) : (
          <div style={{display: "flex", flexDirection: "column", gap: 6}}>
            {projects.map(p => (
              <div key={p.id} style={{
                display: "grid", gridTemplateColumns: "1fr auto auto",
                gap: 10, alignItems: "center",
                padding: "10px 12px",
                background: "var(--surface-2)",
                borderRadius: 8,
              }}>
                <div>
                  <div style={{fontSize: 13, fontWeight: 500}}>{p.name}</div>
                  <div className="cell-mono" style={{fontSize: 11, color: "var(--fg-3)"}}>{p.code} · {p.startDate} → {p.endDate}</div>
                </div>
                <span className="cell-mono" style={{fontSize: 11, color: "var(--fg-3)"}}>{p.completed}/{p.totalParticipants}</span>
                <ProjectStatusBadge status={p.status} />
              </div>
            ))}
          </div>
        )}
      </div>
    </>
  );
}

function OrganizationNewSheet({ onClose }) {
  const { ORG_TYPES } = window.MMSSAdminData;
  const [form, setForm] = useStateOP({
    name: "", type: "pesantren", identityType: "NISN", city: "",
    contact: "", contactEmail: "", contactPhone: "", totalParticipants: 0,
  });
  function set(k, v) { setForm(f => ({...f, [k]: v})); }

  // Auto-derive identity type from organization type
  useEffectOP(() => {
    const t = ORG_TYPES.find(t => t.key === form.type);
    if (t) set("identityType", t.identityType);
  }, [form.type]);

  return (
    <>
      <div className="field">
        <label className="field-label">Organization name</label>
        <input className="input" placeholder="e.g. Pesantren Darul Hikmah" value={form.name} onChange={e => set("name", e.target.value)} />
      </div>
      <div className="field-row">
        <div className="field">
          <label className="field-label">Type</label>
          <select className="select" value={form.type} onChange={e => set("type", e.target.value)}>
            {ORG_TYPES.map(t => <option key={t.key} value={t.key}>{t.label}</option>)}
          </select>
        </div>
        <div className="field">
          <label className="field-label">Identity type</label>
          <select className="select" value={form.identityType} onChange={e => set("identityType", e.target.value)}>
            <option>NISN</option>
            <option>NIK</option>
            <option>Employee ID</option>
            <option>Member ID</option>
          </select>
          <div className="field-help">Governs validation when importing participants.</div>
        </div>
      </div>
      <div className="field-row">
        <div className="field">
          <label className="field-label">City</label>
          <input className="input" placeholder="Jakarta" value={form.city} onChange={e => set("city", e.target.value)} />
        </div>
        <div className="field">
          <label className="field-label">Expected participants</label>
          <input className="input" type="number" placeholder="0" value={form.totalParticipants || ""} onChange={e => set("totalParticipants", Number(e.target.value))} />
        </div>
      </div>
      <div className="field">
        <label className="field-label">Primary contact</label>
        <input className="input" placeholder="Full name" value={form.contact} onChange={e => set("contact", e.target.value)} />
      </div>
      <div className="field-row">
        <div className="field">
          <label className="field-label">Email</label>
          <input className="input" type="email" placeholder="contact@org.id" value={form.contactEmail} onChange={e => set("contactEmail", e.target.value)} />
        </div>
        <div className="field">
          <label className="field-label">Phone</label>
          <input className="input" placeholder="+62…" value={form.contactPhone} onChange={e => set("contactPhone", e.target.value)} />
        </div>
      </div>
      <div className="field">
        <label className="field-label" style={{display: "flex", alignItems: "center", gap: 8}}>
          <span style={{flex: 1}}>Auto-create draft program after save</span>
          <span className="switch on"></span>
        </label>
        <div className="field-help">A draft program will be linked and ready for scheduling.</div>
      </div>
    </>
  );
}

function DetailField({ label, value, mono }) {
  return (
    <div className="field">
      <span className="field-label" style={{fontSize: 10, textTransform: "uppercase", letterSpacing: "0.04em"}}>{label}</span>
      <span style={{fontSize: 13, fontFamily: mono ? "var(--font-mono)" : undefined}}>{value}</span>
    </div>
  );
}

function SummaryPill({ label, value }) {
  return (
    <div className="summary-pill">
      <span className="summary-pill-label">{label}</span>
      <span className="summary-pill-value">{value}</span>
    </div>
  );
}

// ============================================================
//  PROJECTS
// ============================================================
function ProjectsScreen({ showSheet }) {
  const { PROJECTS, orgById, radiographerById } = window.MMSSAdminData;
  const [search, setSearch] = useStateOP("");
  const [statusFilter, setStatusFilter] = useStateOP("all");

  const filtered = PROJECTS.filter(p => {
    if (statusFilter !== "all" && p.status !== statusFilter) return false;
    if (search && !p.name.toLowerCase().includes(search.toLowerCase()) && !p.code.toLowerCase().includes(search.toLowerCase())) return false;
    return true;
  });

  return (
    <div className="main-body">
      <div className="summary-row">
        <SummaryPill label="All" value={PROJECTS.length} />
        <SummaryPill label="Active" value={PROJECTS.filter(p => p.status === "active").length} />
        <SummaryPill label="Scheduled" value={PROJECTS.filter(p => p.status === "scheduled").length} />
        <SummaryPill label="Completed" value={PROJECTS.filter(p => p.status === "completed").length} />
      </div>

      <div className="section-card">
        <div className="section-card-header">
          <div>
            <div className="section-card-title">Screening programs</div>
            <div className="section-card-sub">Each program runs against one organization</div>
          </div>
          <div className="section-card-actions" style={{gap: 8}}>
            <div className="search-input" style={{padding: "6px 10px"}}>
              {Icons.search}
              <input type="text" placeholder="Search programs…" value={search} onChange={e => setSearch(e.target.value)} />
            </div>
            <select className="select" value={statusFilter} onChange={e => setStatusFilter(e.target.value)} style={{padding: "7px 10px"}}>
              <option value="all">All statuses</option>
              <option value="active">Active</option>
              <option value="scheduled">Scheduled</option>
              <option value="completed">Completed</option>
              <option value="draft">Draft</option>
            </select>
            <button className="btn btn-primary btn-sm" onClick={() => showSheet({kind: "project-new"})}>
              {Icons.plus} New program
            </button>
          </div>
        </div>
        <table className="table">
          <thead>
            <tr>
              <th>Program</th>
              <th>Organization</th>
              <th>Dates</th>
              <th>Days</th>
              <th>Quota/day</th>
              <th>Progress</th>
              <th>Radiographers</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(p => {
              const org = orgById(p.orgId);
              return (
                <tr key={p.id}>
                  <td>
                    <div className="cell-strong">{p.name}</div>
                    <div className="cell-mono" style={{fontSize: 11}}>{p.code}</div>
                  </td>
                  <td>{org?.name}</td>
                  <td className="cell-mono" style={{fontSize: 12}}>{p.startDate} → {p.endDate}</td>
                  <td className="cell-mono">{p.workingDays}</td>
                  <td className="cell-mono">{p.dailyQuota}</td>
                  <td><ProgressCell done={p.completed} total={p.totalParticipants} /></td>
                  <td>
                    <div style={{display: "flex", marginLeft: 4}}>
                      {p.assignedRadiographers.slice(0, 3).map((rid, i) => {
                        const r = radiographerById(rid);
                        return r ? (
                          <div key={rid} style={{marginLeft: i ? -8 : 0, border: "2px solid var(--surface)", borderRadius: "50%"}}>
                            <Avatar name={r.name} size={24} />
                          </div>
                        ) : null;
                      })}
                    </div>
                  </td>
                  <td><ProjectStatusBadge status={p.status} /></td>
                  <td className="cell-right">
                    <button className="btn btn-ghost btn-sm" onClick={() => showSheet({kind: "project-detail", id: p.id})}>
                      Open →
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="table-empty">No programs match your filter.</div>}
      </div>
    </div>
  );
}

function ProjectDetailSheet({ projectId, onClose }) {
  const { projectById, orgById, radiographerById, RADIOGRAPHERS } = window.MMSSAdminData;
  const p = projectById(projectId);
  if (!p) return null;
  const org = orgById(p.orgId);
  const assigned = p.assignedRadiographers.map(radiographerById).filter(Boolean);
  return (
    <>
      <div>
        <div style={{display: "flex", alignItems: "center", gap: 10}}>
          <ProjectStatusBadge status={p.status} />
          <span className="cell-mono" style={{fontSize: 11, color: "var(--fg-3)"}}>{p.code}</span>
        </div>
        <div style={{fontSize: 18, fontWeight: 600, marginTop: 4}}>{p.name}</div>
        <div style={{fontSize: 12, color: "var(--fg-3)", marginTop: 2}}>{org?.name}</div>
      </div>

      <div className="field-row">
        <DetailField label="Start" value={p.startDate} mono />
        <DetailField label="End" value={p.endDate} mono />
      </div>
      <div className="field-row-3">
        <DetailField label="Working days" value={p.workingDays} mono />
        <DetailField label="Daily quota" value={p.dailyQuota} mono />
        <DetailField label="Total" value={p.totalParticipants.toLocaleString("id-ID")} mono />
      </div>

      <div>
        <div className="field-label" style={{marginBottom: 8}}>Schedule preview</div>
        <ScheduleStrip days={p.workingDays} completed={Math.floor(p.completed / p.dailyQuota)} />
        <div style={{display: "flex", justifyContent: "space-between", marginTop: 4, fontSize: 11, color: "var(--fg-3)", fontFamily: "var(--font-mono)"}}>
          <span>Day 1</span><span>Day {p.workingDays}</span>
        </div>
      </div>

      <div>
        <div className="field-label" style={{marginBottom: 8}}>
          Assigned radiographers ({assigned.length})
        </div>
        <div style={{display: "flex", flexDirection: "column", gap: 6}}>
          {assigned.map(r => (
            <div key={r.id} style={{
              display: "grid", gridTemplateColumns: "32px 1fr auto",
              gap: 10, alignItems: "center",
              padding: "8px 10px",
              background: "var(--surface-2)",
              borderRadius: 7,
            }}>
              <Avatar name={r.name} size={28} />
              <div>
                <div style={{fontSize: 13, fontWeight: 500}}>{r.name}</div>
                <div style={{fontSize: 11, color: "var(--fg-3)", fontFamily: "var(--font-mono)"}}>{r.email}</div>
              </div>
              <AccountStatusBadge status={r.status} />
            </div>
          ))}
          <button className="btn btn-sm" style={{justifyContent: "center"}}>
            {Icons.plus} Assign radiographer
          </button>
        </div>
      </div>

      <div className="field-row">
        <DetailField label="Completed" value={`${p.completed} / ${p.totalParticipants}`} mono />
        <DetailField label="Email delivery" value={`${p.emailSent} sent · ${p.emailFailed} failed`} mono />
      </div>
    </>
  );
}

function ProjectNewSheet({ onClose }) {
  const { ORGS } = window.MMSSAdminData;
  const [form, setForm] = useStateOP({
    orgId: ORGS[0].id, name: "", code: "",
    startDate: "2026-06-01", endDate: "2026-06-20",
    workingDays: 15, totalParticipants: 500,
    autoDistribute: true,
  });
  function set(k, v) { setForm(f => ({...f, [k]: v})); }
  const dailyQuota = form.autoDistribute && form.workingDays > 0
    ? Math.ceil(form.totalParticipants / form.workingDays) : null;
  return (
    <>
      <div className="field">
        <label className="field-label">Organization</label>
        <select className="select" value={form.orgId} onChange={e => set("orgId", e.target.value)}>
          {ORGS.map(o => <option key={o.id} value={o.id}>{o.name}</option>)}
        </select>
      </div>
      <div className="field-row">
        <div className="field">
          <label className="field-label">Program name</label>
          <input className="input" value={form.name} onChange={e => set("name", e.target.value)} placeholder="e.g. Skrining Tahunan 2026" />
        </div>
        <div className="field">
          <label className="field-label">Program code</label>
          <input className="input" value={form.code} onChange={e => set("code", e.target.value)} placeholder="ABCDE-2026-1" style={{fontFamily: "var(--font-mono)"}} />
        </div>
      </div>
      <div className="field-row">
        <div className="field">
          <label className="field-label">Start date</label>
          <input className="input" type="date" value={form.startDate} onChange={e => set("startDate", e.target.value)} />
        </div>
        <div className="field">
          <label className="field-label">End date</label>
          <input className="input" type="date" value={form.endDate} onChange={e => set("endDate", e.target.value)} />
        </div>
      </div>
      <div className="field-row">
        <div className="field">
          <label className="field-label">Working days</label>
          <input className="input" type="number" value={form.workingDays} onChange={e => set("workingDays", Number(e.target.value))} />
        </div>
        <div className="field">
          <label className="field-label">Total participants</label>
          <input className="input" type="number" value={form.totalParticipants} onChange={e => set("totalParticipants", Number(e.target.value))} />
        </div>
      </div>

      <div className="field">
        <label className="field-label" style={{display: "flex", alignItems: "center", gap: 8}}>
          <span
            className={`switch ${form.autoDistribute ? "on" : ""}`}
            onClick={() => set("autoDistribute", !form.autoDistribute)}
          ></span>
          <span style={{flex: 1}}>Auto-distribute daily quota</span>
        </label>
        {form.autoDistribute && dailyQuota && (
          <div style={{
            padding: 10, background: "var(--surface-2)", borderRadius: 7,
            display: "flex", alignItems: "center", gap: 8, marginTop: 4,
          }}>
            <span style={{fontSize: 12, color: "var(--fg-3)"}}>Suggested:</span>
            <span style={{fontFamily: "var(--font-mono)", fontSize: 14, fontWeight: 500}}>{dailyQuota}</span>
            <span style={{fontSize: 12, color: "var(--fg-3)"}}>participants/day × {form.workingDays} days</span>
          </div>
        )}
      </div>
    </>
  );
}

function ScheduleStrip({ days, completed }) {
  return (
    <div style={{display: "grid", gridTemplateColumns: `repeat(${days}, 1fr)`, gap: 3}}>
      {Array.from({length: days}).map((_, i) => (
        <div key={i} style={{
          height: 24, borderRadius: 3,
          background: i < completed ? "var(--accent)" :
                       i === completed ? "var(--accent-soft)" :
                       "var(--surface-3)",
          border: i === completed ? "1px solid var(--accent)" : "1px solid transparent",
        }}></div>
      ))}
    </div>
  );
}

// ============================================================
//  PARTICIPANTS (per-project)
// ============================================================
function ParticipantsScreen({ showSheet }) {
  const { PROJECTS, orgById } = window.MMSSAdminData;
  const [projectId, setProjectId] = useStateOP(PROJECTS[0].id);
  const [page, setPage] = useStateOP(1);
  const [search, setSearch] = useStateOP("");

  // Use a deterministic sample list per project
  const allParticipants = useMemoOP(() => {
    let seed = 0;
    for (let i = 0; i < projectId.length; i++) seed = (seed * 31 + projectId.charCodeAt(i)) >>> 0;
    return window.MMSSData.generateStudents(120, seed % 9999);
  }, [projectId]);

  const filtered = search
    ? allParticipants.filter(p => p.name.toLowerCase().includes(search.toLowerCase()) || p.nisn.includes(search))
    : allParticipants;

  const PAGE = 12;
  const pages = Math.max(1, Math.ceil(filtered.length / PAGE));
  const safePage = Math.min(page, pages);
  const items = filtered.slice((safePage - 1) * PAGE, safePage * PAGE);
  useEffectOP(() => setPage(1), [search, projectId]);

  const project = PROJECTS.find(p => p.id === projectId);
  const org = orgById(project.orgId);

  return (
    <div className="main-body">
      <div className="section-card">
        <div className="section-card-header">
          <div>
            <div className="section-card-title">Participants</div>
            <div className="section-card-sub">Imported from the partner organization's database</div>
          </div>
          <div className="section-card-actions" style={{gap: 8}}>
            <select className="select" value={projectId} onChange={e => setProjectId(e.target.value)} style={{padding: "7px 10px"}}>
              {PROJECTS.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
            <button className="btn btn-sm" onClick={() => showSheet({kind: "import", projectId})}>
              {AdminIcons.download} Import Excel/CSV
            </button>
          </div>
        </div>

        <div style={{padding: "12px 20px", borderBottom: "1px solid var(--border-subtle)", display: "flex", gap: 12, alignItems: "center"}}>
          <div className="search-input" style={{flex: 1}}>
            {Icons.search}
            <input type="text" placeholder={`Search by name or ${org.identityType}…`} value={search} onChange={e => setSearch(e.target.value)} />
          </div>
          <span className="cell-mono" style={{fontSize: 11, color: "var(--fg-3)"}}>
            Identity type: <strong style={{color: "var(--fg)"}}>{org.identityType}</strong>
          </span>
        </div>

        <table className="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>{org.identityType}</th>
              <th>Gender</th>
              <th>Date of birth</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {items.map(s => (
              <tr key={s.id}>
                <td>
                  <div style={{display: "flex", gap: 10, alignItems: "center"}}>
                    <Avatar name={s.name} size={26} />
                    <span className="cell-strong">{s.name}</span>
                  </div>
                </td>
                <td className="cell-mono">{s.nisn}</td>
                <td className="cell-mono">{s.gender === "L" ? "M" : "F"}</td>
                <td className="cell-mono">{s.dob}</td>
                <td className="cell-mono" style={{fontSize: 11}}>{s.email}</td>
                <td className="cell-mono" style={{fontSize: 11}}>{s.phone}</td>
                <td><StatusBadge status={s.screeningStatus} /></td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="table-empty">No participants match your search.</div>}

        <div className="pagination">
          <div className="pagination-info">
            Showing <strong>{(safePage - 1) * PAGE + 1}</strong>–<strong>{Math.min(safePage * PAGE, filtered.length)}</strong> of <strong>{filtered.length}</strong>
          </div>
          <div className="pagination-controls">
            <button className="page-btn" disabled={safePage <= 1} onClick={() => setPage(safePage - 1)}>{Icons.arrowLeft}</button>
            <span className="page-ellipsis cell-mono" style={{padding: "0 8px"}}>{safePage} / {pages}</span>
            <button className="page-btn" disabled={safePage >= pages} onClick={() => setPage(safePage + 1)}>{Icons.arrowRight}</button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ============================================================
//  IMPORT WIZARD (inside sidesheet)
// ============================================================
function ImportWizardSheet({ projectId, onClose }) {
  const [step, setStep] = useStateOP(1); // 1=upload, 2=preview, 3=done
  const [file, setFile] = useStateOP(null);

  function pickFile() {
    setFile({ name: "santri-al-hidayah-2026.xlsx", rows: 1003, size: "187 KB" });
    setStep(2);
  }
  function commit() { setStep(3); }

  const samplePreview = [
    { ok: "ok",      name: "Ahmad Nur Siregar",       nisn: "0072304881", dob: "2009-03-14", email: "wali.ahmad.nur.siregar@gmail.com" },
    { ok: "ok",      name: "Aisyah Az-Zahra Lubis",   nisn: "0073142299", dob: "2010-11-02", email: "wali.aisyah.az.zahra.lubis@gmail.com" },
    { ok: "warning", name: "Muhammad Rizki Saputra",  nisn: "0074410187", dob: "2008-07-18", email: "" },
    { ok: "ok",      name: "Siti Maryam Pratama",     nisn: "0072891133", dob: "2010-01-25", email: "wali.siti.maryam.pratama@gmail.com" },
    { ok: "error",   name: "Hasan Hafidz Ginting",    nisn: "00724",       dob: "2009-05-09", email: "wali.hasan.hafidz@gmail.com" },
    { ok: "ok",      name: "Khadijah Aulia Manurung", nisn: "0073007711", dob: "2011-02-08", email: "wali.khadijah.aulia@gmail.com" },
    { ok: "ok",      name: "Umar Faisal Pasaribu",    nisn: "0072554420", dob: "2009-09-30", email: "wali.umar.faisal@gmail.com" },
    { ok: "warning", name: "Aisyah Aulia Sembiring",  nisn: "0073220988", dob: "", email: "wali.aisyah.aulia@gmail.com" },
  ];

  return (
    <>
      <div className="import-step-list">
        <div className={`import-step ${step >= 1 ? "done" : ""} ${step === 1 ? "active" : ""}`}>
          <span className="import-step-num">1</span>
          <div>
            <div className="import-step-label">Upload spreadsheet</div>
            {file && <div className="import-step-meta">{file.name} · {file.rows} rows · {file.size}</div>}
          </div>
          {step > 1 && <span style={{color: "var(--green)", fontSize: 12}}>{Icons.check}</span>}
        </div>
        <div className={`import-step ${step >= 2 ? "done" : ""} ${step === 2 ? "active" : ""}`}>
          <span className="import-step-num">2</span>
          <div>
            <div className="import-step-label">Validate &amp; preview</div>
            <div className="import-step-meta">Check identity type, deduplicate, flag missing fields</div>
          </div>
          {step > 2 && <span style={{color: "var(--green)", fontSize: 12}}>{Icons.check}</span>}
        </div>
        <div className={`import-step ${step === 3 ? "done" : ""}`}>
          <span className="import-step-num">3</span>
          <div>
            <div className="import-step-label">Commit to database</div>
            <div className="import-step-meta">Marks all as <code style={{fontFamily: "var(--font-mono)"}}>not_arrived</code></div>
          </div>
        </div>
      </div>

      {step === 1 && (
        <div className="import-dropzone" onClick={pickFile}>
          <div className="import-dropzone-icon">{Icons.upload}</div>
          <div style={{fontSize: 14, fontWeight: 600}}>Drop your Excel or CSV file</div>
          <div style={{fontSize: 12, color: "var(--fg-3)", marginTop: 6}}>
            Required: <code style={{fontFamily: "var(--font-mono)"}}>name</code>, <code style={{fontFamily: "var(--font-mono)"}}>identity_number</code>, <code style={{fontFamily: "var(--font-mono)"}}>identity_type</code>, <code style={{fontFamily: "var(--font-mono)"}}>date_of_birth</code>, <code style={{fontFamily: "var(--font-mono)"}}>email</code>, <code style={{fontFamily: "var(--font-mono)"}}>phone</code>
          </div>
          <div className="upload-zone-meta">.xlsx · .csv · up to 10 MB</div>
        </div>
      )}

      {step === 2 && (
        <>
          <div className="summary-row" style={{marginBottom: 0}}>
            <SummaryPill label="Total rows" value={file?.rows || 0} />
            <SummaryPill label="Valid" value={(file?.rows || 0) - 8} />
            <SummaryPill label="Warnings" value={6} />
            <SummaryPill label="Errors" value={2} />
          </div>
          <div>
            <div className="field-label" style={{marginBottom: 6}}>First 8 rows (preview)</div>
            <div className="preview-table">
              <table>
                <thead>
                  <tr>
                    <th></th>
                    <th>name</th>
                    <th>identity_number</th>
                    <th>date_of_birth</th>
                    <th>email</th>
                  </tr>
                </thead>
                <tbody>
                  {samplePreview.map((r, i) => (
                    <tr key={i} className={r.ok === "ok" ? "" : r.ok}>
                      <td><span className={`preview-tag ${r.ok}`}>{r.ok === "ok" ? "ok" : r.ok}</span></td>
                      <td style={{fontFamily: "var(--font-sans)"}}>{r.name}</td>
                      <td>{r.nisn || <em style={{color: "var(--red)"}}>missing</em>}</td>
                      <td>{r.dob || <em style={{color: "var(--amber)"}}>missing</em>}</td>
                      <td>{r.email || <em style={{color: "var(--amber)"}}>missing</em>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="field-help" style={{marginTop: 6}}>
              <strong style={{color: "var(--red)"}}>2 errors</strong> will be skipped on commit; warnings will import as <code style={{fontFamily: "var(--font-mono)"}}>not_arrived</code> with incomplete contact info.
            </div>
          </div>
          <div style={{display: "flex", gap: 8}}>
            <button className="btn" onClick={() => setStep(1)}>{Icons.arrowLeft} Back</button>
            <button className="btn btn-primary" style={{flex: 1, justifyContent: "center"}} onClick={commit}>
              Commit {(file?.rows || 0) - 2} valid rows
            </button>
          </div>
        </>
      )}

      {step === 3 && (
        <div style={{textAlign: "center", padding: "20px 0"}}>
          <div style={{
            width: 64, height: 64, borderRadius: "50%",
            background: "var(--green-soft)", color: "oklch(0.4 0.12 150)",
            display: "grid", placeItems: "center",
            margin: "0 auto 16px",
          }}>
            {Icons.check}
          </div>
          <div style={{fontSize: 16, fontWeight: 600}}>Import complete</div>
          <div style={{fontSize: 13, color: "var(--fg-3)", marginTop: 4}}>
            {(file?.rows || 0) - 2} participants added · 2 errors skipped
          </div>
          <div style={{marginTop: 20, display: "flex", gap: 8, justifyContent: "center"}}>
            <button className="btn btn-fg" onClick={onClose}>Done</button>
            <button className="btn">Download error log</button>
          </div>
        </div>
      )}
    </>
  );
}

window.OrganizationsScreen = OrganizationsScreen;
window.OrganizationDetailSheet = OrganizationDetailSheet;
window.OrganizationNewSheet = OrganizationNewSheet;
window.ProjectsScreen = ProjectsScreen;
window.ProjectDetailSheet = ProjectDetailSheet;
window.ProjectNewSheet = ProjectNewSheet;
window.ParticipantsScreen = ParticipantsScreen;
window.ImportWizardSheet = ImportWizardSheet;
