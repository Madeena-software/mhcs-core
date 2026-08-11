// Admin screens — Radiographers, Exports
const { useState: useStateRX } = React;

// ============================================================
//  RADIOGRAPHERS
// ============================================================
function RadiographersScreen({ showSheet }) {
  const { RADIOGRAPHERS, projectById, orgById } = window.MMSSAdminData;
  const [search, setSearch] = useStateRX("");
  const [statusFilter, setStatusFilter] = useStateRX("all");

  const filtered = RADIOGRAPHERS.filter(r => {
    if (statusFilter !== "all" && r.status !== statusFilter) return false;
    if (search && !r.name.toLowerCase().includes(search.toLowerCase()) && !r.email.includes(search.toLowerCase())) return false;
    return true;
  });

  return (
    <div className="main-body">
      <div className="summary-row">
        <SummaryPill label="All accounts" value={RADIOGRAPHERS.length} />
        <SummaryPill label="Active" value={RADIOGRAPHERS.filter(r => r.status === "active").length} />
        <SummaryPill label="Invited" value={RADIOGRAPHERS.filter(r => r.status === "pending").length} />
        <SummaryPill label="Disabled" value={RADIOGRAPHERS.filter(r => r.status === "disabled").length} />
      </div>

      <div className="section-card">
        <div className="section-card-header">
          <div>
            <div className="section-card-title">Radiographer accounts</div>
            <div className="section-card-sub">Created by admin · onboarded via signed set-password link</div>
          </div>
          <div className="section-card-actions" style={{gap: 8}}>
            <div className="search-input" style={{padding: "6px 10px"}}>
              {Icons.search}
              <input type="text" placeholder="Search by name or email…" value={search} onChange={e => setSearch(e.target.value)} />
            </div>
            <select className="select" value={statusFilter} onChange={e => setStatusFilter(e.target.value)} style={{padding: "7px 10px"}}>
              <option value="all">All</option>
              <option value="active">Active</option>
              <option value="pending">Invited</option>
              <option value="disabled">Disabled</option>
            </select>
            <button className="btn btn-primary btn-sm" onClick={() => showSheet({kind: "rad-new"})}>
              {Icons.plus} Invite radiographer
            </button>
          </div>
        </div>

        <table className="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Assigned to</th>
              <th>Total scans</th>
              <th>Last seen</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {filtered.map(r => (
              <tr key={r.id}>
                <td>
                  <div style={{display: "flex", gap: 10, alignItems: "center"}}>
                    <Avatar name={r.name} size={28} />
                    <div>
                      <div className="cell-strong">{r.name}</div>
                      <div className="cell-mono" style={{fontSize: 11}}>{r.id}</div>
                    </div>
                  </div>
                </td>
                <td className="cell-mono" style={{fontSize: 12}}>{r.email}</td>
                <td className="cell-mono" style={{fontSize: 12}}>{r.phone}</td>
                <td>
                  {r.assignedProjects.length === 0 ? (
                    <span style={{color: "var(--fg-4)", fontSize: 12}}>—</span>
                  ) : (
                    r.assignedProjects.map(pid => {
                      const p = projectById(pid);
                      const o = p ? orgById(p.orgId) : null;
                      return p ? (
                        <div key={pid} style={{fontSize: 12}}>
                          <div>{p.name}</div>
                          <div style={{fontSize: 11, color: "var(--fg-3)"}}>{o?.name}</div>
                        </div>
                      ) : null;
                    })
                  )}
                </td>
                <td className="cell-mono">{r.scans.toLocaleString("id-ID")}</td>
                <td className="cell-mono" style={{fontSize: 12, color: "var(--fg-3)"}}>{r.lastSeen}</td>
                <td><AccountStatusBadge status={r.status} /></td>
                <td className="cell-right">
                  <button className="btn btn-ghost btn-sm" onClick={() => showSheet({kind: "rad-detail", id: r.id})}>
                    Open →
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {filtered.length === 0 && <div className="table-empty">No accounts match your filter.</div>}
      </div>
    </div>
  );
}

function RadiographerDetailSheet({ radId, onClose }) {
  const { radiographerById, projectById, orgById } = window.MMSSAdminData;
  const r = radiographerById(radId);
  if (!r) return null;
  return (
    <>
      <div style={{display: "flex", alignItems: "center", gap: 14}}>
        <Avatar name={r.name} size={48} />
        <div>
          <div style={{fontSize: 18, fontWeight: 600}}>{r.name}</div>
          <div className="cell-mono" style={{fontSize: 11, color: "var(--fg-3)"}}>{r.id}</div>
        </div>
        <div style={{marginLeft: "auto"}}>
          <AccountStatusBadge status={r.status} />
        </div>
      </div>

      <div className="field-row">
        <div className="field">
          <label className="field-label">Email</label>
          <input className="input" defaultValue={r.email} style={{fontFamily: "var(--font-mono)", fontSize: 12}} />
        </div>
        <div className="field">
          <label className="field-label">Phone</label>
          <input className="input" defaultValue={r.phone} style={{fontFamily: "var(--font-mono)", fontSize: 12}} />
        </div>
      </div>

      <div className="field-row-3">
        <DetailField label="Created" value={r.createdAt} mono />
        <DetailField label="Last seen" value={r.lastSeen} />
        <DetailField label="Total scans" value={r.scans.toLocaleString("id-ID")} mono />
      </div>

      <div>
        <div className="field-label" style={{marginBottom: 8}}>Assigned programs ({r.assignedProjects.length})</div>
        {r.assignedProjects.length === 0 ? (
          <div className="table-empty" style={{padding: 16}}>No programs assigned</div>
        ) : (
          <div style={{display: "flex", flexDirection: "column", gap: 6}}>
            {r.assignedProjects.map(pid => {
              const p = projectById(pid);
              const o = p ? orgById(p.orgId) : null;
              return p ? (
                <div key={pid} style={{
                  display: "grid", gridTemplateColumns: "1fr auto",
                  gap: 10, alignItems: "center",
                  padding: "8px 12px",
                  background: "var(--surface-2)", borderRadius: 7,
                }}>
                  <div>
                    <div style={{fontSize: 13, fontWeight: 500}}>{p.name}</div>
                    <div style={{fontSize: 11, color: "var(--fg-3)"}}>{o?.name}</div>
                  </div>
                  <ProjectStatusBadge status={p.status} />
                </div>
              ) : null;
            })}
            <button className="btn btn-sm" style={{justifyContent: "center"}}>
              {Icons.plus} Assign to another program
            </button>
          </div>
        )}
      </div>

      <div style={{padding: "12px 14px", background: "var(--surface-2)", borderRadius: 8, fontSize: 12, color: "var(--fg-3)"}}>
        <strong style={{color: "var(--fg)", fontWeight: 500}}>Instant revocation</strong>
        <div style={{marginTop: 4}}>
          Disabling this account invalidates all active sessions immediately, including the booth workstation if the radiographer is currently signed in.
        </div>
      </div>
    </>
  );
}

function RadiographerNewSheet({ onClose }) {
  const { PROJECTS } = window.MMSSAdminData;
  const [form, setForm] = useStateRX({ name: "", email: "", phone: "", projectIds: [] });
  function set(k, v) { setForm(f => ({...f, [k]: v})); }
  function toggleProject(id) {
    setForm(f => ({
      ...f,
      projectIds: f.projectIds.includes(id) ? f.projectIds.filter(x => x !== id) : [...f.projectIds, id]
    }));
  }
  return (
    <>
      <div className="field">
        <label className="field-label">Full name</label>
        <input className="input" value={form.name} onChange={e => set("name", e.target.value)} placeholder="Dr. Putri Andini" />
      </div>
      <div className="field">
        <label className="field-label">Email</label>
        <input className="input" type="email" value={form.email} onChange={e => set("email", e.target.value)} placeholder="putri.andini@madeena.id" />
        <div className="field-help">A signed set-password link will be sent here. You won't see or set the password.</div>
      </div>
      <div className="field">
        <label className="field-label">Phone (optional)</label>
        <input className="input" value={form.phone} onChange={e => set("phone", e.target.value)} placeholder="+62…" />
      </div>
      <div className="field">
        <label className="field-label">Assign to programs</label>
        <div style={{display: "flex", flexDirection: "column", gap: 6, marginTop: 4}}>
          {PROJECTS.filter(p => p.status === "active" || p.status === "scheduled").map(p => (
            <label key={p.id} style={{
              display: "grid", gridTemplateColumns: "20px 1fr auto",
              gap: 10, alignItems: "center",
              padding: "8px 10px",
              background: form.projectIds.includes(p.id) ? "var(--accent-soft)" : "var(--surface-2)",
              border: form.projectIds.includes(p.id) ? "1px solid var(--accent)" : "1px solid var(--border-subtle)",
              borderRadius: 7,
              cursor: "pointer",
            }}>
              <input type="checkbox" checked={form.projectIds.includes(p.id)} onChange={() => toggleProject(p.id)} />
              <div>
                <div style={{fontSize: 13}}>{p.name}</div>
                <div style={{fontSize: 11, color: "var(--fg-3)", fontFamily: "var(--font-mono)"}}>{p.code}</div>
              </div>
              <ProjectStatusBadge status={p.status} />
            </label>
          ))}
        </div>
      </div>
    </>
  );
}

// ============================================================
//  EXPORTS / ZIP BUILDER
// ============================================================
function ExportsScreen() {
  const { PROJECTS, orgById } = window.MMSSAdminData;
  const [projectId, setProjectId] = useStateRX(PROJECTS[0].id);
  const [groupBy, setGroupBy] = useStateRX("group");
  const [includeJpeg, setIncludeJpeg] = useStateRX(true);
  const [includePdf, setIncludePdf] = useStateRX(true);
  const [includeDicom, setIncludeDicom] = useStateRX(false);
  const [failedOnly, setFailedOnly] = useStateRX(false);
  const [building, setBuilding] = useStateRX(false);
  const [done, setDone] = useStateRX(null);

  const project = PROJECTS.find(p => p.id === projectId);
  const org = orgById(project.orgId);

  // Mock the estimated archive size
  const baseRecords = failedOnly ? project.emailFailed : project.completed;
  const sizeMb = (
    (includeJpeg ? 0.4 : 0) +
    (includePdf  ? 0.15 : 0) +
    (includeDicom ? 4.0 : 0)
  ) * baseRecords;

  function build() {
    setBuilding(true);
    setDone(null);
    setTimeout(() => {
      setBuilding(false);
      setDone({
        filename: `${project.code}-results-${todayStamp()}.zip`,
        records: baseRecords,
        size: sizeMb.toFixed(1),
      });
    }, 1800);
  }

  return (
    <div className="main-body">
      <div style={{display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16}}>
        {/* Configurator */}
        <div className="section-card">
          <div className="section-card-header">
            <div>
              <div className="section-card-title">Build a results archive</div>
              <div className="section-card-sub">Bulk export per program, for offline distribution by the organization</div>
            </div>
          </div>
          <div style={{padding: 20, display: "flex", flexDirection: "column", gap: 16}}>
            <div className="field">
              <label className="field-label">Program</label>
              <select className="select" value={projectId} onChange={e => setProjectId(e.target.value)}>
                {PROJECTS.map(p => <option key={p.id} value={p.id}>{p.name} — {orgById(p.orgId)?.name}</option>)}
              </select>
            </div>

            <div className="field">
              <label className="field-label">Group folders by</label>
              <div style={{display: "grid", gridTemplateColumns: "1fr 1fr 1fr", gap: 6}}>
                {[
                  {k: "group", label: "Class / group"},
                  {k: "date",  label: "Day of screening"},
                  {k: "flat",  label: "Flat (no folders)"},
                ].map(o => (
                  <button key={o.k}
                    className={`tool-btn ${groupBy === o.k ? "active" : ""}`}
                    onClick={() => setGroupBy(o.k)}
                    style={{justifyContent: "center"}}>
                    <span className="tool-btn-label" style={{textAlign: "center"}}>{o.label}</span>
                  </button>
                ))}
              </div>
            </div>

            <div className="field">
              <label className="field-label">Include</label>
              <div style={{display: "flex", flexDirection: "column", gap: 6}}>
                <CheckRow checked={includeJpeg} onChange={setIncludeJpeg} label="High-quality JPEG rontgen" sub="~400 KB per record" />
                <CheckRow checked={includePdf}  onChange={setIncludePdf}  label="AI summary PDF" sub="Plain-language report for guardian" />
                <CheckRow checked={includeDicom} onChange={setIncludeDicom} label="Edited DICOM (raw)" sub="~4 MB per record — for medical archive" />
              </div>
            </div>

            <div className="field">
              <label className="field-label">Scope</label>
              <div style={{display: "flex", flexDirection: "column", gap: 6}}>
                <CheckRow checked={!failedOnly} onChange={v => setFailedOnly(!v)} label="All completed participants" sub={`${project.completed} records`} mode="radio" />
                <CheckRow checked={failedOnly}  onChange={v => setFailedOnly(v)}  label="Only email-failed participants" sub={`${project.emailFailed} records · for manual hand-off`} mode="radio" />
              </div>
            </div>

            <button className="btn btn-primary btn-lg" style={{justifyContent: "center"}} onClick={build} disabled={building}>
              {building ? <><div className="spinner" style={{width: 14, height: 14, borderWidth: 1.5, borderTopColor: "white"}}></div> Building archive…</> : <>{AdminIcons.download} Build ZIP archive</>}
            </button>
          </div>
        </div>

        {/* Preview */}
        <div className="section-card">
          <div className="section-card-header">
            <div>
              <div className="section-card-title">Archive preview</div>
              <div className="section-card-sub">Folder structure inside the ZIP</div>
            </div>
          </div>
          <div style={{padding: 20}}>
            <div className="summary-row" style={{marginBottom: 16}}>
              <SummaryPill label="Records" value={baseRecords} />
              <SummaryPill label="Est. size" value={`${sizeMb.toFixed(1)} MB`} />
              <SummaryPill label="Files" value={baseRecords * ([includeJpeg, includePdf, includeDicom].filter(Boolean).length || 1)} />
            </div>

            <div style={{
              background: "var(--fg)", color: "#bdbdbd",
              fontFamily: "var(--font-mono)", fontSize: 12,
              padding: 16, borderRadius: 8,
              minHeight: 280, lineHeight: 1.7,
            }}>
              <div style={{color: "#e0e0e0", marginBottom: 8}}>{project.code}-results-{todayStamp()}.zip</div>
              {groupBy === "group" && (
                <>
                  <div>├── Group A/</div>
                  {includeJpeg && <div>│   ├── Ahmad_Nur_Siregar.jpg</div>}
                  {includePdf && <div>│   ├── Ahmad_Nur_Siregar.pdf</div>}
                  {includeDicom && <div>│   ├── Ahmad_Nur_Siregar.dcm</div>}
                  <div>│   ├── …</div>
                  <div>├── Group B/</div>
                  <div>│   ├── Aisyah_Az-Zahra_Lubis.jpg</div>
                  <div>│   ├── …</div>
                  <div>├── Group C/</div>
                  <div>│   └── …</div>
                </>
              )}
              {groupBy === "date" && (
                <>
                  <div>├── 2026-05-20/</div>
                  <div>│   ├── Ahmad_Nur_Siregar.jpg</div>
                  <div>│   ├── …</div>
                  <div>├── 2026-05-21/</div>
                  <div>│   └── …</div>
                </>
              )}
              {groupBy === "flat" && (
                <>
                  <div>├── Ahmad_Nur_Siregar.jpg</div>
                  <div>├── Ahmad_Nur_Siregar.pdf</div>
                  <div>├── Aisyah_Az-Zahra_Lubis.jpg</div>
                  <div>├── …</div>
                </>
              )}
              <div>└── _manifest.csv  <span style={{color: "#888"}}># index of every record</span></div>
            </div>

            {done && (
              <div style={{
                marginTop: 16,
                padding: 14,
                background: "var(--green-soft)",
                borderRadius: 8,
                display: "flex", alignItems: "center", gap: 12,
              }}>
                <div style={{
                  width: 36, height: 36, borderRadius: 8,
                  background: "var(--green)", color: "white",
                  display: "grid", placeItems: "center",
                }}>{Icons.check}</div>
                <div style={{flex: 1}}>
                  <div style={{fontSize: 13, fontWeight: 500, color: "oklch(0.32 0.1 150)"}}>Archive built</div>
                  <div style={{fontSize: 11, color: "oklch(0.45 0.08 150)", fontFamily: "var(--font-mono)"}}>
                    {done.filename} · {done.records} records · {done.size} MB
                  </div>
                </div>
                <button className="btn btn-success btn-sm">{AdminIcons.download} Download</button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Past exports */}
      <div className="section-card">
        <div className="section-card-header">
          <div>
            <div className="section-card-title">Recent exports</div>
            <div className="section-card-sub">Last 30 days</div>
          </div>
        </div>
        <table className="table">
          <thead>
            <tr>
              <th>Filename</th>
              <th>Program</th>
              <th>Records</th>
              <th>Size</th>
              <th>Built by</th>
              <th>Built at</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {PAST_EXPORTS.map((e, i) => (
              <tr key={i}>
                <td className="cell-mono" style={{fontSize: 12}}>{e.filename}</td>
                <td>{e.program}</td>
                <td className="cell-mono">{e.records}</td>
                <td className="cell-mono">{e.size}</td>
                <td>{e.by}</td>
                <td className="cell-mono" style={{fontSize: 12, color: "var(--fg-3)"}}>{e.at}</td>
                <td className="cell-right"><button className="btn btn-ghost btn-sm">{AdminIcons.download} Download</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function CheckRow({ checked, onChange, label, sub, mode = "check" }) {
  return (
    <label style={{
      display: "grid", gridTemplateColumns: "18px 1fr",
      gap: 10, alignItems: "center",
      padding: "10px 12px",
      background: checked ? "var(--accent-soft)" : "var(--surface-2)",
      border: checked ? "1px solid var(--accent)" : "1px solid var(--border-subtle)",
      borderRadius: 7,
      cursor: "pointer",
    }}>
      <input
        type={mode === "radio" ? "radio" : "checkbox"}
        checked={checked}
        onChange={e => onChange(e.target.checked)}
      />
      <div>
        <div style={{fontSize: 13, fontWeight: 500}}>{label}</div>
        <div style={{fontSize: 11, color: "var(--fg-3)"}}>{sub}</div>
      </div>
    </label>
  );
}

const PAST_EXPORTS = [
  { filename: "ALHID-2026-1-results-20260524.zip",  program: "Skrining Tahunan Al-Hidayah", records: 153, size: "84.2 MB",  by: "Admin Madeena", at: "Today 09:14" },
  { filename: "SMA3YOG-2026-1-results-20260523.zip",program: "Skrining Awal Tahun SMAN 3",  records: 432, size: "192.4 MB", by: "Admin Madeena", at: "Yesterday 17:08" },
  { filename: "ALHID-2026-1-failed-20260522.zip",   program: "Skrining Tahunan Al-Hidayah", records: 5,   size: "2.4 MB",   by: "Admin Madeena", at: "May 22 16:40" },
  { filename: "GARUT-2026-1-results-final.zip",    program: "Program Skrining Massal Garut", records: 320, size: "138.6 MB",by: "Admin Madeena", at: "Mar 11 11:22" },
];

function todayStamp() {
  return "20260524";
}

window.RadiographersScreen = RadiographersScreen;
window.RadiographerDetailSheet = RadiographerDetailSheet;
window.RadiographerNewSheet = RadiographerNewSheet;
window.ExportsScreen = ExportsScreen;
