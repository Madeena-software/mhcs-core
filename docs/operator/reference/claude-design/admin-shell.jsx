// Admin shell — sidebar, topbar, sidesheet primitive
const { useState: useStateSh, useEffect: useEffectSh } = React;

// Admin-specific icons (in addition to Icons from shared.jsx)
const AdminIcons = {
  dashboard: <Icon d="M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z" />,
  building:  <Icon d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9v.01M9 13v.01M9 17v.01" />,
  briefcase: <Icon d="M4 7h16a1 1 0 0 1 1 1v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a1 1 0 0 1 1-1ZM8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18" />,
  list:      <Icon d="M8 6h13M8 12h13M8 18h13M3 6v.01M3 12v.01M3 18v.01" />,
  badge:     <Icon d="M12 14a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM8.21 13.89 7 23l5-3 5 3-1.21-9.11" />,
  download:  <Icon d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3" />,
  logout:    <Icon d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />,
  settings:  <Icon d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.36.36.84.59 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" />,
  calendar:  <Icon d="M3 6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6ZM3 10h18M8 2v4M16 2v4" />,
  filter:    <Icon d="M3 6h18M6 12h12M10 18h4" />,
  mail:      <Icon d="M4 6h16a1 1 0 0 1 1 1v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a1 1 0 0 1 1-1ZM3 7l9 7 9-7" />,
  alert:     <Icon d="M12 9v4M12 17v.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />,
};

function Sidebar({ active, onNavigate, counts }) {
  const main = [
    { key: "dashboard", label: "Dashboard", icon: AdminIcons.dashboard },
  ];
  const data = [
    { key: "organizations", label: "Organizations", icon: AdminIcons.building, count: counts.organizations },
    { key: "projects",      label: "Projects",      icon: AdminIcons.briefcase, count: counts.projects },
    { key: "participants",  label: "Participants",  icon: AdminIcons.list,      count: counts.participants },
  ];
  const team = [
    { key: "radiographers", label: "Radiographers", icon: AdminIcons.badge, count: counts.radiographers },
  ];
  const tools = [
    { key: "exports", label: "Exports", icon: AdminIcons.download },
  ];

  function renderLink(item) {
    return (
      <button key={item.key}
        className={`sidebar-link ${active === item.key ? "active" : ""}`}
        onClick={() => onNavigate(item.key)}>
        {item.icon}
        <span>{item.label}</span>
        {item.count != null && <span className="sidebar-link-count">{item.count}</span>}
      </button>
    );
  }

  return (
    <aside className="sidebar">
      <div className="sidebar-section">
        <div className="sidebar-nav">{main.map(renderLink)}</div>
      </div>
      <div className="sidebar-section">
        <div className="sidebar-section-title">Programs</div>
        <div className="sidebar-nav">{data.map(renderLink)}</div>
      </div>
      <div className="sidebar-section">
        <div className="sidebar-section-title">People</div>
        <div className="sidebar-nav">{team.map(renderLink)}</div>
      </div>
      <div className="sidebar-section">
        <div className="sidebar-section-title">Tools</div>
        <div className="sidebar-nav">{tools.map(renderLink)}</div>
      </div>
      <div className="sidebar-footer">
        <div className="sidebar-status">
          <span className="sidebar-status-dot"></span>
          <div style={{flex: 1}}>
            <div><strong>All services online</strong></div>
            <div style={{fontSize: 10, color: "var(--fg-4)"}}>Conversion · AI · S3 · SMTP</div>
          </div>
        </div>
      </div>
    </aside>
  );
}

// ---------------- Sidesheet (slide-in panel) ----------------
function Sidesheet({ open, title, subtitle, onClose, footer, children }) {
  // Lock body scroll while open
  useEffectSh(() => {
    if (open) {
      const prev = document.body.style.overflow;
      document.body.style.overflow = "hidden";
      return () => { document.body.style.overflow = prev; };
    }
  }, [open]);
  // ESC to close
  useEffectSh(() => {
    if (!open) return;
    function onKey(e) { if (e.key === "Escape") onClose(); }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [open, onClose]);
  return (
    <>
      <div className={`sidesheet-scrim ${open ? "show" : ""}`} onClick={onClose}></div>
      <div className={`sidesheet ${open ? "show" : ""}`} role="dialog" aria-modal="true">
        <div className="sidesheet-header">
          <div>
            <div className="sidesheet-title">{title}</div>
            {subtitle && <div className="sidesheet-sub">{subtitle}</div>}
          </div>
          <button className="btn btn-ghost btn-icon" onClick={onClose} aria-label="Close">
            {Icons.x}
          </button>
        </div>
        <div className="sidesheet-body">{children}</div>
        {footer && <div className="sidesheet-footer">{footer}</div>}
      </div>
    </>
  );
}

// ---------------- Sparkline ----------------
function Sparkline({ data, accent = "var(--accent)", height = 36 }) {
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const range = Math.max(1, max - min);
  const w = 120;
  const h = height;
  const step = w / Math.max(1, data.length - 1);
  const points = data.map((v, i) => `${i * step},${h - ((v - min) / range) * (h - 4) - 2}`).join(" ");
  // Area fill
  const area = `0,${h} ${points} ${w},${h}`;
  return (
    <svg viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" width="100%" height={h}>
      <polygon points={area} fill={accent} opacity="0.10" />
      <polyline points={points} fill="none" stroke={accent} strokeWidth="1.5" strokeLinejoin="round" strokeLinecap="round" />
    </svg>
  );
}

// ---------------- Mini bars (per-booth throughput etc.) ----------------
function MiniBars({ data, highlightIndex = data.length - 1 }) {
  const max = Math.max(...data, 1);
  return (
    <div className="mini-bars">
      {data.map((v, i) => (
        <div key={i}
          className={`mini-bars-bar ${i === highlightIndex ? "accent" : ""}`}
          style={{ height: `${(v / max) * 100}%` }}
          title={`${v}`}
        ></div>
      ))}
    </div>
  );
}

// ---------------- Org type chip ----------------
function OrgTypeChip({ type }) {
  const labels = { pesantren: "Pesantren", school: "School", corporate: "Corporate", government: "Government" };
  return <span className={`org-type ${type}`}>{labels[type] || type}</span>;
}

// ---------------- Status badges (admin variants) ----------------
function ProjectStatusBadge({ status }) {
  const m = {
    active:    { label: "Active",    tone: "teal" },
    scheduled: { label: "Scheduled", tone: "blue" },
    completed: { label: "Completed", tone: "green" },
    draft:     { label: "Draft",     tone: "neutral" },
    paused:    { label: "Paused",    tone: "amber" },
  };
  const v = m[status] || { label: status, tone: "neutral" };
  return <span className={`badge badge-${v.tone}`}>{v.label}</span>;
}
function OrgStatusBadge({ status }) {
  const m = {
    active:    { label: "Active",    tone: "teal" },
    pending:   { label: "Pending MoU", tone: "amber" },
    completed: { label: "Completed", tone: "green" },
    draft:     { label: "Draft",     tone: "neutral" },
  };
  const v = m[status] || { label: status, tone: "neutral" };
  return <span className={`badge badge-${v.tone}`}>{v.label}</span>;
}
function AccountStatusBadge({ status }) {
  const m = {
    active:   { label: "Active",   tone: "green" },
    pending:  { label: "Invited",  tone: "amber" },
    disabled: { label: "Disabled", tone: "neutral" },
  };
  const v = m[status] || { label: status, tone: "neutral" };
  return <span className={`badge badge-${v.tone}`}>{v.label}</span>;
}

function ProgressCell({ done, total }) {
  const pct = total ? Math.round((done / total) * 100) : 0;
  return (
    <div className="table-progress">
      <span className="table-progress-text" style={{minWidth: 50, textAlign: "left"}}>{done}/{total}</span>
      <div className="table-progress-bar">
        <div className="table-progress-fill" style={{width: `${pct}%`}}></div>
      </div>
      <span className="table-progress-text">{pct}%</span>
    </div>
  );
}

Object.assign(window, {
  Sidebar, Sidesheet, Sparkline, MiniBars, OrgTypeChip,
  ProjectStatusBadge, OrgStatusBadge, AccountStatusBadge, ProgressCell,
  AdminIcons,
});
