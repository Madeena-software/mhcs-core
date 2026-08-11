// Main admin app — shell, routing, sheet management, tweaks
const { useState: useStateAA, useEffect: useEffectAA, useMemo: useMemoAA } = React;

const ADMIN_TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "density": "comfortable"
}/*EDITMODE-END*/;

function AdminApp() {
  const [tweaks, setTweak] = window.useTweaks(ADMIN_TWEAK_DEFAULTS);
  const { ORGS, PROJECTS, RADIOGRAPHERS } = window.MMSSAdminData;
  const [screen, setScreen] = useStateAA("dashboard");
  const [sheet, setSheet] = useStateAA(null); // {kind, id?}

  useEffectAA(() => {
    document.documentElement.dataset.density = tweaks.density;
  }, [tweaks.density]);

  const counts = {
    organizations: ORGS.length,
    projects: PROJECTS.length,
    participants: PROJECTS.reduce((s, p) => s + p.totalParticipants, 0),
    radiographers: RADIOGRAPHERS.length,
  };

  // ---------------- Header + breadcrumbs per screen ----------------
  const header = useMemoAA(() => ({
    dashboard:     { title: "Dashboard",        subtitle: "Real-time monitoring across all active screening programs" },
    organizations: { title: "Organizations",    subtitle: "Partner organizations Madeena runs screening programs for" },
    projects:      { title: "Screening programs", subtitle: "Time-bound screening deployments under each organization" },
    participants:  { title: "Participants",      subtitle: "Per-program participant database — imported from the partner" },
    radiographers: { title: "Radiographers",     subtitle: "Field radiographer accounts — admin-created, per-person login" },
    exports:       { title: "Exports",           subtitle: "Build downloadable archives of results for offline distribution" },
  }), []);

  function showSheet(s) { setSheet(s); }
  function closeSheet() { setSheet(null); }

  return (
    <div className="app">
      <header className="topbar">
        <div className="topbar-left">
          <div className="brand">
            <div className="brand-mark">M</div>
            <span>Madeena</span>
            <span className="brand-sep">/</span>
            <span className="brand-sub">MMSS Admin</span>
          </div>
        </div>

        <nav className="topbar-center" role="tablist">
          <a href="index.html" className="app-switch">
            <span className="app-switch-mark">R</span>
            <span>Radiographer →</span>
          </a>
        </nav>

        <div className="topbar-right">
          <button className="btn btn-ghost btn-icon" title="Notifications">{AdminIcons.alert}</button>
          <span style={{fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--fg-3)"}}>
            <Clock />
          </span>
          <div className="user-chip">
            <Avatar name="Admin Madeena" size={28} />
            <div>
              <div className="user-chip-name">Admin Madeena</div>
              <div className="user-chip-role">Super Admin</div>
            </div>
          </div>
        </div>
      </header>

      <div className="admin">
        <Sidebar active={screen} onNavigate={setScreen} counts={counts} />

        <main className="main">
          <div className="main-header">
            <div>
              <div className="main-title">{header[screen].title}</div>
              <div className="main-subtitle">{header[screen].subtitle}</div>
            </div>
            <div className="main-actions">
              {screen === "dashboard" && (
                <select className="select" defaultValue="today" style={{padding: "7px 10px"}}>
                  <option value="today">Today</option>
                  <option value="week">Last 7 days</option>
                  <option value="month">Last 30 days</option>
                </select>
              )}
            </div>
          </div>

          {screen === "dashboard"     && <DashboardScreen />}
          {screen === "organizations" && <OrganizationsScreen showSheet={showSheet} />}
          {screen === "projects"      && <ProjectsScreen showSheet={showSheet} />}
          {screen === "participants"  && <ParticipantsScreen showSheet={showSheet} />}
          {screen === "radiographers" && <RadiographersScreen showSheet={showSheet} />}
          {screen === "exports"       && <ExportsScreen />}
        </main>
      </div>

      {/* Sheets */}
      <Sidesheet
        open={sheet?.kind === "org-detail"}
        title="Organization detail"
        subtitle="View partner information and linked programs"
        onClose={closeSheet}
        footer={<>
          <button className="btn" onClick={closeSheet}>Close</button>
          <button className="btn btn-primary">Edit</button>
        </>}
      >
        {sheet?.kind === "org-detail" && <OrganizationDetailSheet orgId={sheet.id} onClose={closeSheet} />}
      </Sidesheet>

      <Sidesheet
        open={sheet?.kind === "org-new"}
        title="New organization"
        subtitle="Add a new partner organization to the system"
        onClose={closeSheet}
        footer={<>
          <button className="btn" onClick={closeSheet}>Cancel</button>
          <button className="btn btn-primary" onClick={closeSheet}>Save organization</button>
        </>}
      >
        {sheet?.kind === "org-new" && <OrganizationNewSheet onClose={closeSheet} />}
      </Sidesheet>

      <Sidesheet
        open={sheet?.kind === "project-detail"}
        title="Program detail"
        subtitle="Schedule, quota, and assigned radiographers"
        onClose={closeSheet}
        footer={<>
          <button className="btn" onClick={closeSheet}>Close</button>
          <button className="btn btn-primary">Edit schedule</button>
        </>}
      >
        {sheet?.kind === "project-detail" && <ProjectDetailSheet projectId={sheet.id} onClose={closeSheet} />}
      </Sidesheet>

      <Sidesheet
        open={sheet?.kind === "project-new"}
        title="New screening program"
        subtitle="Distribute participants evenly across working days"
        onClose={closeSheet}
        footer={<>
          <button className="btn" onClick={closeSheet}>Cancel</button>
          <button className="btn btn-primary" onClick={closeSheet}>Create program</button>
        </>}
      >
        {sheet?.kind === "project-new" && <ProjectNewSheet onClose={closeSheet} />}
      </Sidesheet>

      <Sidesheet
        open={sheet?.kind === "import"}
        title="Import participants"
        subtitle="Upload the partner organization's database"
        onClose={closeSheet}
      >
        {sheet?.kind === "import" && <ImportWizardSheet projectId={sheet.projectId} onClose={closeSheet} />}
      </Sidesheet>

      <Sidesheet
        open={sheet?.kind === "rad-detail"}
        title="Radiographer account"
        subtitle="Manage credentials and project assignments"
        onClose={closeSheet}
        footer={<>
          <button className="btn btn-danger" style={{marginRight: "auto"}}>Disable account</button>
          <button className="btn" onClick={closeSheet}>Close</button>
          <button className="btn btn-primary">Save changes</button>
        </>}
      >
        {sheet?.kind === "rad-detail" && <RadiographerDetailSheet radId={sheet.id} onClose={closeSheet} />}
      </Sidesheet>

      <Sidesheet
        open={sheet?.kind === "rad-new"}
        title="Invite radiographer"
        subtitle="Sends a signed set-password link by email"
        onClose={closeSheet}
        footer={<>
          <button className="btn" onClick={closeSheet}>Cancel</button>
          <button className="btn btn-primary" onClick={closeSheet}>{AdminIcons.mail} Send invitation</button>
        </>}
      >
        {sheet?.kind === "rad-new" && <RadiographerNewSheet onClose={closeSheet} />}
      </Sidesheet>

      {/* Tweaks */}
      <window.TweaksPanel>
        <window.TweakSection title="Display">
          <window.TweakRadio
            label="Density"
            value={tweaks.density}
            options={[
              { value: "comfortable", label: "Comfortable" },
              { value: "compact", label: "Compact" },
            ]}
            onChange={v => setTweak("density", v)}
          />
        </window.TweakSection>
      </window.TweaksPanel>
    </div>
  );
}

function Clock() {
  const [t, setT] = useStateAA(() => new Date());
  useEffectAA(() => {
    const i = setInterval(() => setT(new Date()), 30000);
    return () => clearInterval(i);
  }, []);
  return <>{String(t.getHours()).padStart(2, "0")}:{String(t.getMinutes()).padStart(2, "0")} WIB</>;
}

ReactDOM.createRoot(document.getElementById("root")).render(<AdminApp />);
