// Admin Dashboard — real-time monitoring per the spec
const { useState: useStateDB, useMemo: useMemoDB } = React;

function DashboardScreen() {
  const { ORGS, PROJECTS, RADIOGRAPHERS, KPI_SERIES, orgById } = window.MMSSAdminData;

  // Aggregate across active projects
  const stats = useMemoDB(() => {
    const active = PROJECTS.filter(p => p.status === "active");
    const todayCompleted = active.reduce((s, p) => s + p.todayCompleted, 0);
    const todayWaiting = active.reduce((s, p) => s + p.todayWaiting, 0);
    const todayInBooth = active.reduce((s, p) => s + p.todayInBooth, 0);
    const totalToday = active.reduce((s, p) => s + p.dailyQuota, 0);
    const emailSent = PROJECTS.reduce((s, p) => s + p.emailSent, 0);
    const emailFailed = PROJECTS.reduce((s, p) => s + p.emailFailed, 0);
    const totalCompleted = PROJECTS.reduce((s, p) => s + p.completed, 0);
    const totalParticipants = PROJECTS.reduce((s, p) => s + p.totalParticipants, 0);
    return {
      activeProjects: active.length,
      activeRadiographers: RADIOGRAPHERS.filter(r => r.status === "active").length,
      todayCompleted, todayWaiting, todayInBooth, totalToday,
      attendancePct: totalToday ? Math.round((todayCompleted / totalToday) * 100) : 0,
      emailSent, emailFailed,
      totalCompleted, totalParticipants,
    };
  }, []);

  return (
    <div className="main-body">
      {/* KPI tiles */}
      <div className="kpi-grid">
        <KpiTile
          label="Today's completed scans"
          value={stats.todayCompleted}
          suffix={`/ ${stats.totalToday}`}
          delta="+12% vs yesterday"
          deltaDir="up"
          series={KPI_SERIES.scansPerDay}
        />
        <KpiTile
          label="Daily attendance"
          value={`${stats.attendancePct}%`}
          delta="3.2 pts"
          deltaDir="up"
          series={KPI_SERIES.attendancePct}
        />
        <KpiTile
          label="Emails sent"
          value={stats.emailSent}
          delta={`${stats.emailFailed} failed`}
          deltaDir={stats.emailFailed > 0 ? "down" : "flat"}
          series={KPI_SERIES.emailsPerDay}
        />
        <KpiTile
          label="Active radiographers"
          value={stats.activeRadiographers}
          suffix={`of ${RADIOGRAPHERS.length}`}
          delta="all online"
          deltaDir="flat"
        />
      </div>

      {/* Active projects table */}
      <div className="section-card">
        <div className="section-card-header">
          <div>
            <div className="section-card-title">Active screening programs</div>
            <div className="section-card-sub">Live progress across all running deployments</div>
          </div>
          <div className="section-card-actions">
            <button className="btn btn-sm">{AdminIcons.filter} Filter</button>
            <button className="btn btn-sm btn-primary">{Icons.plus} New project</button>
          </div>
        </div>
        <table className="table">
          <thead>
            <tr>
              <th>Program</th>
              <th>Organization</th>
              <th>Day</th>
              <th>Overall progress</th>
              <th>Today</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            {PROJECTS.filter(p => p.status === "active" || p.status === "scheduled").map(p => {
              const org = orgById(p.orgId);
              const dayIdx = computeDay(p.startDate);
              return (
                <tr key={p.id}>
                  <td>
                    <div className="cell-strong">{p.name}</div>
                    <div className="cell-mono" style={{fontSize: 11}}>{p.code}</div>
                  </td>
                  <td>
                    <div>{org?.name}</div>
                    <OrgTypeChip type={org?.type} />
                  </td>
                  <td className="cell-mono">{dayIdx}/{p.workingDays}</td>
                  <td><ProgressCell done={p.completed} total={p.totalParticipants} /></td>
                  <td>
                    <div style={{display: "flex", gap: 12, alignItems: "center"}}>
                      <span className="cell-mono">{p.todayCompleted}/{p.dailyQuota}</span>
                      <MiniBars data={fakeDailyDist(p.id, p.dailyQuota, p.todayCompleted)} />
                    </div>
                  </td>
                  <td><ProjectStatusBadge status={p.status} /></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Two-up: live booth + delivery */}
      <div style={{display: "grid", gridTemplateColumns: "1.4fr 1fr", gap: 16}}>
        <LiveBoothsCard />
        <DeliveryStatusCard stats={stats} />
      </div>

      {/* Recent activity */}
      <RecentActivityCard />
    </div>
  );
}

// ---------------- KPI tile ----------------
function KpiTile({ label, value, suffix, delta, deltaDir, series }) {
  return (
    <div className="kpi-tile">
      <div className="kpi-tile-head">
        <span>{label}</span>
        {delta && <span className={`kpi-tile-delta ${deltaDir || "flat"}`}>{deltaDir === "up" ? "↑" : deltaDir === "down" ? "↓" : "·"} {delta}</span>}
      </div>
      <div className="kpi-tile-value">
        {value}
        {suffix && <small>{suffix}</small>}
      </div>
      {series && <div className="kpi-sparkline"><Sparkline data={series} /></div>}
    </div>
  );
}

// ---------------- Live booths ----------------
function LiveBoothsCard() {
  const { PROJECTS, RADIOGRAPHERS, orgById, radiographerById } = window.MMSSAdminData;
  const active = PROJECTS.filter(p => p.status === "active");
  return (
    <div className="section-card">
      <div className="section-card-header">
        <div>
          <div className="section-card-title">Live booths</div>
          <div className="section-card-sub">{active.length} booth{active.length !== 1 ? "s" : ""} running right now</div>
        </div>
        <span className="live-indicator">
          <span className="live-dot"></span>
          Live · 12s ago
        </span>
      </div>
      <div style={{padding: "8px 0"}}>
        {active.map(p => {
          const org = orgById(p.orgId);
          const rad = radiographerById(p.assignedRadiographers[0]);
          return (
            <div key={p.id} style={{
              display: "grid", gridTemplateColumns: "auto 1fr auto", gap: 14,
              alignItems: "center", padding: "12px 20px",
              borderBottom: "1px solid var(--border-subtle)"
            }}>
              <div style={{
                width: 36, height: 36, borderRadius: 8,
                background: "var(--surface-2)",
                display: "grid", placeItems: "center",
                fontFamily: "var(--font-mono)", fontSize: 11, color: "var(--fg-2)"
              }}>
                BTH<br/>{p.id.split("-")[1]}
              </div>
              <div>
                <div style={{fontSize: 13, fontWeight: 500}}>{org?.name}</div>
                <div style={{fontSize: 11, color: "var(--fg-3)", display: "flex", gap: 10, marginTop: 2}}>
                  <span>{rad?.name}</span>
                  <span className="cell-mono">{p.todayCompleted}/{p.dailyQuota} today</span>
                </div>
              </div>
              <div style={{display: "flex", alignItems: "center", gap: 10}}>
                {p.todayInBooth > 0 ? (
                  <span className="badge badge-teal">In booth</span>
                ) : p.todayWaiting > 0 ? (
                  <span className="badge badge-amber">{p.todayWaiting} waiting</span>
                ) : (
                  <span className="badge badge-neutral">Idle</span>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

// ---------------- Delivery status ----------------
function DeliveryStatusCard({ stats }) {
  return (
    <div className="section-card">
      <div className="section-card-header">
        <div>
          <div className="section-card-title">AI & Delivery</div>
          <div className="section-card-sub">Last 24 hours</div>
        </div>
      </div>
      <div style={{padding: 20, display: "flex", flexDirection: "column", gap: 14}}>
        <DeliveryRow label="AI Diagnostic completed" value={stats.totalCompleted - 4} sub="avg 1.8s" />
        <DeliveryRow label="Pending AI" value={4} sub="in queue" tone="amber" />
        <DeliveryRow label="Email sent" value={stats.emailSent} sub="auto-dispatched" tone="green" />
        <DeliveryRow label="Email failed" value={stats.emailFailed} sub="needs ZIP export" tone="red" />
      </div>
    </div>
  );
}
function DeliveryRow({ label, value, sub, tone }) {
  return (
    <div style={{display: "flex", alignItems: "center", justifyContent: "space-between"}}>
      <div style={{display: "flex", alignItems: "center", gap: 10}}>
        <span style={{
          width: 8, height: 8, borderRadius: 2,
          background:
            tone === "amber" ? "var(--amber)" :
            tone === "green" ? "var(--green)" :
            tone === "red"   ? "var(--red)"   : "var(--accent)"
        }}></span>
        <div>
          <div style={{fontSize: 13}}>{label}</div>
          <div style={{fontSize: 11, color: "var(--fg-3)"}}>{sub}</div>
        </div>
      </div>
      <div style={{fontFamily: "var(--font-mono)", fontSize: 18, fontWeight: 500}}>{value}</div>
    </div>
  );
}

// ---------------- Recent activity ----------------
function RecentActivityCard() {
  const items = [
    { at: "09:42", icon: Icons.check, text: "AI Resume completed for Ahmad Nur Siregar (Pesantren Al-Hidayah)", tone: "green" },
    { at: "09:38", icon: AdminIcons.mail, text: "12 result emails sent (Pesantren Al-Hidayah)", tone: "blue" },
    { at: "09:31", icon: AdminIcons.alert, text: "Email bounce: wali.rizki.firdaus.lubis@…  — flagged for ZIP export", tone: "red" },
    { at: "09:20", icon: AdminIcons.badge, text: "Bp. Hafidz Maulana accepted invitation", tone: "neutral" },
    { at: "08:55", icon: AdminIcons.briefcase, text: "Program ALHID-2026-1 entered Day 4 of 20", tone: "neutral" },
    { at: "08:12", icon: AdminIcons.building, text: "MoU received: Pesantren Darul Ulum Jombang (1,400 participants)", tone: "neutral" },
  ];
  return (
    <div className="section-card">
      <div className="section-card-header">
        <div>
          <div className="section-card-title">Recent activity</div>
          <div className="section-card-sub">Across all programs today</div>
        </div>
      </div>
      <div>
        {items.map((it, i) => (
          <div key={i} style={{
            display: "grid", gridTemplateColumns: "60px 24px 1fr",
            gap: 12, alignItems: "center",
            padding: "10px 20px",
            borderBottom: i < items.length - 1 ? "1px solid var(--border-subtle)" : 0,
            fontSize: 13,
          }}>
            <span className="cell-mono" style={{fontSize: 11, color: "var(--fg-3)"}}>{it.at}</span>
            <span style={{
              display: "grid", placeItems: "center",
              width: 24, height: 24, borderRadius: 6,
              background:
                it.tone === "green" ? "var(--green-soft)" :
                it.tone === "red" ? "var(--red-soft)" :
                it.tone === "blue" ? "var(--blue-soft)" :
                "var(--surface-2)",
              color:
                it.tone === "green" ? "oklch(0.4 0.12 150)" :
                it.tone === "red" ? "var(--red)" :
                it.tone === "blue" ? "oklch(0.4 0.1 240)" :
                "var(--fg-2)",
            }}>
              {it.icon}
            </span>
            <span>{it.text}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

// ---------------- helpers ----------------
function computeDay(startDate) {
  // Fake: assume "today" is +4..+10 days into project depending on hash
  let h = 0;
  for (let i = 0; i < startDate.length; i++) h = (h * 31 + startDate.charCodeAt(i)) >>> 0;
  return 1 + (h % 12);
}
function fakeDailyDist(seed, max, todayDone) {
  let h = 0;
  for (let i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) >>> 0;
  const out = [];
  for (let i = 0; i < 10; i++) {
    const v = (h + i * 13) % max;
    out.push(Math.max(8, v));
  }
  out.push(todayDone);
  return out;
}

window.DashboardScreen = DashboardScreen;
