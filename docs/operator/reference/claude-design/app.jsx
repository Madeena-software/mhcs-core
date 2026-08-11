// Main App — top bar, module switcher, shared student state, tweaks
const { useState: useStateApp, useEffect: useEffectApp, useMemo: useMemoApp, useCallback: useCallbackApp } = React;

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "density": "comfortable",
  "datasetSize": 50
}/*EDITMODE-END*/;

function App() {
  const [tweaks, setTweak] = window.useTweaks(TWEAK_DEFAULTS);
  const [module, setModule] = useStateApp("frontdesk"); // frontdesk | booth
  const [currentBoothStudentId, setCurrentBoothStudentId] = useStateApp(null);
  const [toast, setToast] = useStateApp(null);

  // Generate students — re-seed when datasetSize changes
  const [students, setStudents] = useStateApp(() =>
    window.MMSSData.generateStudents(tweaks.datasetSize, 42)
  );

  useEffectApp(() => {
    setStudents(window.MMSSData.generateStudents(tweaks.datasetSize, 42));
    setCurrentBoothStudentId(null);
  }, [tweaks.datasetSize]);

  // Apply density to root
  useEffectApp(() => {
    document.documentElement.dataset.density = tweaks.density;
  }, [tweaks.density]);

  // ---------------- State machine actions ----------------
  function sendToBooth(studentId) {
    setStudents(prev => prev.map(s => s.id === studentId
      ? { ...s, screeningStatus: "in_progress", calledAt: nowStampApp() }
      : s
    ));
    setCurrentBoothStudentId(studentId);
    showToast({ text: "Patient sent to Photo Booth", action: "Open Booth", onAction: () => setModule("booth") });
  }

  function onSubmitComplete(studentId) {
    showToast({ text: "Submitted to AI Service — auto-email queued", tone: "success" });
  }

  function onNextStudent() {
    setCurrentBoothStudentId(null);
    // Auto-route back to Front Desk so operator can call next
    setTimeout(() => setModule("frontdesk"), 200);
  }

  // ---------------- Toast ----------------
  function showToast(t) {
    setToast(t);
    setTimeout(() => setToast(null), 3500);
  }

  // ---------------- Counts for tab badges ----------------
  const counts = useMemoApp(() => ({
    waiting: students.filter(s => s.screeningStatus === "awaiting_queue").length,
    inBooth: currentBoothStudentId ? 1 : 0,
  }), [students, currentBoothStudentId]);

  // Keyboard shortcuts: F = front desk, B = booth
  useEffectApp(() => {
    function onKey(e) {
      if (e.target.tagName === "INPUT" || e.target.tagName === "TEXTAREA") return;
      if (e.key === "f" || e.key === "F") setModule("frontdesk");
      if (e.key === "b" || e.key === "B") setModule("booth");
    }
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, []);

  return (
    <div className="app">
      <header className="topbar">
        <div className="topbar-left">
          <div className="brand">
            <div className="brand-mark">M</div>
            <span>Madeena</span>
            <span className="brand-sep">/</span>
            <span className="brand-sub">MMSS</span>
          </div>
          <div className="org-pill">
            <span className="org-pill-dot"></span>
            <span>Program</span>
            <strong>Pesantren Al-Hidayah</strong>
            <span style={{color: "var(--fg-4)"}}>· Day 4/20</span>
          </div>
        </div>

        <nav className="topbar-center" role="tablist">
          <button
            role="tab"
            aria-selected={module === "frontdesk"}
            className={`module-tab ${module === "frontdesk" ? "active" : ""}`}
            onClick={() => setModule("frontdesk")}>
            {Icons.users}
            Front Desk
            {counts.waiting > 0 && <span className="module-tab-count">{counts.waiting}</span>}
            <span className="kbd">F</span>
          </button>
          <button
            role="tab"
            aria-selected={module === "booth"}
            className={`module-tab ${module === "booth" ? "active" : ""}`}
            onClick={() => setModule("booth")}>
            {Icons.monitor}
            Photo Booth
            {counts.inBooth > 0 && <span className="module-tab-count">1</span>}
            <span className="kbd">B</span>
          </button>
        </nav>

        <div className="topbar-right">
          <a href="admin.html" className="app-switch" title="Open Super Admin">
            <span className="app-switch-mark">A</span>
            <span>Super Admin →</span>
          </a>
          <span style={{fontFamily: "var(--font-mono)", fontSize: 12, color: "var(--fg-3)"}}>
            <Clock />
          </span>
          <div className="user-chip">
            <Avatar name="Putri Andini" size={28} />
            <div>
              <div className="user-chip-name">Dr. Putri Andini</div>
              <div className="user-chip-role">Radiographer · BTH-01</div>
            </div>
          </div>
        </div>
      </header>

      {module === "frontdesk" ? (
        <FrontDesk
          students={students}
          setStudents={setStudents}
          currentBoothStudentId={currentBoothStudentId}
          sendToBooth={sendToBooth}
          onJumpToBooth={() => setModule("booth")}
          density={tweaks.density}
        />
      ) : (
        <PhotoBooth
          students={students}
          setStudents={setStudents}
          currentStudentId={currentBoothStudentId}
          onSubmitComplete={onSubmitComplete}
          onNextStudent={onNextStudent}
          density={tweaks.density}
        />
      )}

      {/* Toast */}
      <div className={`toast ${toast ? "show" : ""} ${toast?.tone === "success" ? "toast-success" : ""}`}>
        {toast?.tone === "success" && Icons.check}
        <span>{toast?.text}</span>
        {toast?.action && (
          <button className="btn btn-sm" style={{
            background: "transparent", border: "1px solid rgba(255,255,255,0.3)",
            color: "white", marginLeft: 8
          }} onClick={() => { toast.onAction?.(); setToast(null); }}>
            {toast.action}
          </button>
        )}
      </div>

      {/* Tweaks panel */}
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

        <window.TweakSection title="Sample Data">
          <window.TweakSelect
            label="Dataset size"
            value={tweaks.datasetSize}
            options={[
              { value: 50, label: "50 participants (single day)" },
              { value: 150, label: "150 participants (3 days)" },
              { value: 500, label: "500 participants (10 days)" },
            ]}
            onChange={v => setTweak("datasetSize", v)}
          />
          <p style={{fontSize: 11, color: "var(--fg-3)", margin: "6px 0 0"}}>
            Re-seeds the worklist. Daily quota is always shown as 50.
          </p>
        </window.TweakSection>
      </window.TweaksPanel>
    </div>
  );
}

function nowStampApp() {
  const d = new Date();
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}

function Clock() {
  const [t, setT] = useStateApp(() => new Date());
  useEffectApp(() => {
    const i = setInterval(() => setT(new Date()), 30000);
    return () => clearInterval(i);
  }, []);
  return <>{String(t.getHours()).padStart(2, "0")}:{String(t.getMinutes()).padStart(2, "0")} WIB</>;
}

ReactDOM.createRoot(document.getElementById("root")).render(<App />);
