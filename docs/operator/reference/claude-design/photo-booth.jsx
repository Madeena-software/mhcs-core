// Photo Booth module — patient profile, DICOM viewer, edit tools, AI findings
const { useState: useStatePB, useEffect: useEffectPB, useRef: useRefPB, useMemo: useMemoPB } = React;

// Booth process state machine (local to the booth, independent of student.screeningStatus)
// idle → awaiting_npz → converting → editing → submitting → ai_processing → ai_done → (next clears to idle)

function PhotoBooth({ students, setStudents, currentStudentId, onSubmitComplete, onNextStudent, density }) {
  const student = students.find(s => s.id === currentStudentId) || null;

  const [boothState, setBoothState] = useStatePB("idle"); // idle | awaiting_npz | converting | editing | submitting | ai_processing | ai_done
  const [edits, setEdits] = useStatePB(defaultEdits());
  const [history, setHistory] = useStatePB([]); // for resets
  const [aiResult, setAiResult] = useStatePB(null);
  const [completedAt, setCompletedAt] = useStatePB(null);
  const [activeTool, setActiveTool] = useStatePB(null);

  // Sync boothState with currentStudentId changes
  useEffectPB(() => {
    if (!student) {
      setBoothState("idle");
      return;
    }
    if (boothState === "idle" && student) {
      setBoothState("awaiting_npz");
      setEdits(defaultEdits());
      setAiResult(null);
    }
  }, [currentStudentId]);

  // Simulated .npz upload + conversion
  function simulateUpload() {
    setBoothState("converting");
    // 1.4s "conversion" delay
    setTimeout(() => {
      setBoothState("editing");
    }, 1400);
  }

  function updateEdit(patch) {
    setEdits(prev => ({ ...prev, ...patch }));
  }
  function resetEdits() {
    setEdits(defaultEdits());
    setActiveTool(null);
  }

  function submit() {
    setBoothState("submitting");
    setCompletedAt(nowStampPB());
    setStudents(prev => prev.map(s => s.id === currentStudentId
      ? { ...s, screeningStatus: "completed", aiStatus: "pending_ai", completedAt: nowStampPB() }
      : s
    ));
    onSubmitComplete && onSubmitComplete(currentStudentId);
    // 700ms "submit" then 1.6s "ai processing"
    setTimeout(() => {
      setBoothState("ai_processing");
      setTimeout(() => {
        const finding = window.MMSSData.MOCK_FINDINGS[student.mockFindingId];
        setAiResult(finding);
        setBoothState("ai_done");
        setStudents(prev => prev.map(s => s.id === currentStudentId
          ? { ...s, aiStatus: "ai_completed" }
          : s
        ));
      }, 1800);
    }, 700);
  }

  function clearForNext() {
    setBoothState("idle");
    setEdits(defaultEdits());
    setAiResult(null);
    setActiveTool(null);
    setCompletedAt(null);
    onNextStudent && onNextStudent();
  }

  if (!student && boothState === "idle") {
    return <BoothIdle />;
  }

  return (
    <div className="module booth">
      {/* LEFT — patient profile */}
      <div className="booth-left">
        <div className="patient-card">
          <div className="patient-card-label">
            {Icons.user}
            Active patient
            <span className="patient-card-locked">LOCKED</span>
          </div>
          <div className="patient-card-name">{student?.name}</div>
          <div className="patient-card-id">NISN {student?.nisn}</div>
        </div>

        <div className="patient-details">
          <div className="detail-row"><dt>Gender</dt><dd>{student?.gender === "L" ? "M" : student?.gender === "P" ? "F" : "—"}</dd></div>
          <div className="detail-row"><dt>Date of birth</dt><dd>{student?.dob}</dd></div>
          <div className="detail-row"><dt>Queue #</dt><dd>{String(student?.queueNumber || 0).padStart(3, "0")}</dd></div>
          <div className="detail-row long"><dt>Email</dt><dd style={{fontSize: 11, wordBreak: "break-all"}}>{student?.email}</dd></div>
          <div className="detail-row"><dt>Phone</dt><dd>{student?.phone}</dd></div>
        </div>

        <div className="session-meta">
          <div>Called at <strong>{student?.calledAt || "—"}</strong></div>
          <div>Booth ID <strong>BTH-01</strong></div>
          <div>Operator <strong>Dr. Putri Andini</strong></div>
        </div>
      </div>

      {/* CENTER — viewer */}
      <div className="booth-center">
        <div className="viewer-toolbar">
          <div className="viewer-toolbar-left">
            <div className="viewer-state">
              <span className="live-dot"></span>
              {boothStateLabel(boothState)}
            </div>
          </div>
          <div className="viewer-toolbar-right">
            {boothState === "editing" && (
              <>
                <span className="viewer-state" style={{fontFamily: "var(--font-mono)"}}>
                  500×600 · DICOM · 12-bit
                </span>
                <button className="btn btn-ghost btn-sm" onClick={resetEdits} title="Discard all edits, restore original">
                  {Icons.reset} Reset
                </button>
              </>
            )}
          </div>
        </div>

        <div className="viewer-stage">
          {boothState === "awaiting_npz" && (
            <div className="upload-zone" onClick={simulateUpload}>
              <div className="upload-zone-inner">
                <div className="upload-zone-icon">{Icons.upload}</div>
                <div className="upload-zone-title">Awaiting exposure</div>
                <div className="upload-zone-sub">
                  Drop the <code style={{fontFamily: "var(--font-mono)"}}>.npz</code> file from the DDR exposure software, or click to simulate.
                </div>
                <div className="upload-zone-meta">DDR-A · Room 1 · Active patient locked</div>
              </div>
            </div>
          )}
          {boothState === "converting" && (
            <div className="upload-zone" style={{cursor: "default"}}>
              <div className="upload-zone-inner">
                <div className="upload-zone-icon">
                  <div className="spinner" style={{width: 24, height: 24, borderWidth: 2}}></div>
                </div>
                <div className="upload-zone-title">Converting .npz → DICOM</div>
                <div className="upload-zone-sub">Calling conversion service…</div>
                <div className="upload-zone-meta">
                  <code style={{fontFamily: "var(--font-mono)"}}>
                    POST /convert · 187 MB · ~1.4s
                  </code>
                </div>
              </div>
            </div>
          )}
          {(boothState === "editing" || boothState === "submitting" || boothState === "ai_processing" || boothState === "ai_done") && (
            <>
              <XrayPlaceholder
                rotation={edits.rotation}
                flipH={edits.flipH}
                flipV={edits.flipV}
                windowLevel={edits.windowLevel}
                windowWidth={edits.windowWidth}
                marker={edits.marker}
                crop={edits.crop}
                label={`DDR-${student?.id}-2026-${edits.rotation || 0}.dcm`}
              />
              <div className="viewer-info">
                <div className="viewer-info-item">WL <strong>{edits.windowLevel}</strong></div>
                <div className="viewer-info-item">WW <strong>{edits.windowWidth}</strong></div>
                <div className="viewer-info-item">Rot <strong>{edits.rotation}°</strong></div>
                {edits.flipH && <div className="viewer-info-item"><strong>Flip-H</strong></div>}
                {edits.flipV && <div className="viewer-info-item"><strong>Flip-V</strong></div>}
                {edits.marker && <div className="viewer-info-item">Marker <strong>{edits.marker}</strong></div>}
                {edits.crop && <div className="viewer-info-item"><strong>Cropped</strong></div>}
              </div>
            </>
          )}
        </div>
      </div>

      {/* RIGHT — tool palette OR AI findings */}
      <div className="booth-right">
        {(boothState === "awaiting_npz" || boothState === "converting") && (
          <div className="tools-panel">
            <div className="tools-section">
              <div className="tools-section-title">Workflow</div>
              <BoothWorkflowList boothState={boothState} />
            </div>
            <div className="tools-section">
              <div className="tools-section-title">Help</div>
              <p style={{fontSize: 12, color: "var(--fg-3)", lineHeight: 1.5, margin: 0}}>
                The patient profile on the left is auto-linked to whoever was just called from the Front Desk.
                You don't need to search or type — once the DDR exposure produces a <code style={{fontFamily: "var(--font-mono)"}}>.npz</code> file, upload it here and the system handles conversion + display.
              </p>
            </div>
          </div>
        )}

        {boothState === "editing" && (
          <EditTools
            edits={edits}
            updateEdit={updateEdit}
            activeTool={activeTool}
            setActiveTool={setActiveTool}
          />
        )}

        {(boothState === "submitting" || boothState === "ai_processing" || boothState === "ai_done") && (
          <AIPanel
            state={boothState}
            result={aiResult}
            student={student}
            completedAt={completedAt}
          />
        )}

        {boothState === "editing" && (
          <div className="finalize-bar">
            <div className="finalize-hint">
              <span className={edits.marker ? "dot-success" : "dot-warn"}></span>
              {edits.marker ? "R/L marker applied" : "Apply R/L marker before submitting"}
            </div>
            <button
              className="btn btn-primary btn-lg"
              style={{justifyContent: "center"}}
              onClick={submit}
              disabled={!edits.marker}>
              {Icons.check}
              Submit & Next
            </button>
          </div>
        )}

        {boothState === "ai_done" && (
          <div className="finalize-bar">
            <div className="finalize-hint">
              <span className="dot-success"></span>
              Submitted to AI Service · Email dispatched
            </div>
            <button className="btn btn-fg btn-lg" style={{justifyContent: "center"}} onClick={clearForNext}>
              Clear booth — Next participant
              {Icons.arrowRight}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

function defaultEdits() {
  return {
    rotation: 0, flipH: false, flipV: false,
    windowLevel: 50, windowWidth: 60,
    marker: null,
    crop: null,
  };
}

function boothStateLabel(s) {
  return ({
    idle: "Booth idle",
    awaiting_npz: "Awaiting exposure (.npz)",
    converting: "Converting .npz → DICOM",
    editing: "Editing original DICOM",
    submitting: "Submitting to AI Service",
    ai_processing: "AI Diagnostic running",
    ai_done: "Result ready · email sent",
  })[s] || s;
}

function nowStampPB() {
  const d = new Date();
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}

// ---------------- Edit tools ----------------
function EditTools({ edits, updateEdit, activeTool, setActiveTool }) {
  return (
    <div className="tools-panel">
      <div className="tools-section">
        <div className="tools-section-title">Rotate</div>
        <div className="tool-grid">
          <button className="tool-btn" onClick={() => updateEdit({ rotation: (edits.rotation - 90 + 360) % 360 })}>
            {Icons.rotateCcw}
            <span className="tool-btn-label">-90°</span>
          </button>
          <button className="tool-btn" onClick={() => updateEdit({ rotation: (edits.rotation + 90) % 360 })}>
            {Icons.rotateCw}
            <span className="tool-btn-label">+90°</span>
          </button>
          <button className={`tool-btn ${edits.rotation === 180 ? "active" : ""}`}
            onClick={() => updateEdit({ rotation: edits.rotation === 180 ? 0 : 180 })}>
            <span className="tool-btn-label">180°</span>
            <span className="kbd">3</span>
          </button>
          <button className="tool-btn" onClick={() => updateEdit({ rotation: 0 })}>
            <span className="tool-btn-label">Original</span>
            <span className="kbd">0</span>
          </button>
        </div>
      </div>

      <div className="tools-section">
        <div className="tools-section-title">Flip</div>
        <div className="tool-grid">
          <button className={`tool-btn ${edits.flipH ? "active" : ""}`}
            onClick={() => updateEdit({ flipH: !edits.flipH })}>
            {Icons.flipH}
            <span className="tool-btn-label">Horizontal</span>
          </button>
          <button className={`tool-btn ${edits.flipV ? "active" : ""}`}
            onClick={() => updateEdit({ flipV: !edits.flipV })}>
            {Icons.flipV}
            <span className="tool-btn-label">Vertical</span>
          </button>
        </div>
      </div>

      <div className="tools-section">
        <div className="tools-section-title">Crop</div>
        <div className="tool-grid">
          <button className={`tool-btn ${edits.crop ? "active" : ""}`}
            onClick={() => updateEdit({ crop: edits.crop ? null : { x: 12, y: 8, w: 76, h: 84 } })}>
            {Icons.crop}
            <span className="tool-btn-label">{edits.crop ? "Cropping…" : "Crop region"}</span>
          </button>
          <button className="tool-btn"
            disabled={!edits.crop}
            onClick={() => updateEdit({ crop: null })}>
            {Icons.x}
            <span className="tool-btn-label">Clear</span>
          </button>
        </div>
      </div>

      <div className="tools-section">
        <div className="tools-section-title">Windowing</div>
        <div className="slider-row">
          <div className="slider-row-head">
            <label>Brightness (WL)</label>
            <span className="slider-value">{edits.windowLevel}</span>
          </div>
          <input className="slider" type="range" min="0" max="100" value={edits.windowLevel}
            onChange={e => updateEdit({ windowLevel: Number(e.target.value) })} />
        </div>
        <div className="slider-row">
          <div className="slider-row-head">
            <label>Contrast (WW)</label>
            <span className="slider-value">{edits.windowWidth}</span>
          </div>
          <input className="slider" type="range" min="0" max="100" value={edits.windowWidth}
            onChange={e => updateEdit({ windowWidth: Number(e.target.value) })} />
        </div>
      </div>

      <div className="tools-section">
        <div className="tools-section-title">Orientation Marker · required</div>
        <div className="rl-toggle">
          <button className={edits.marker === "R" ? "active" : ""}
            onClick={() => updateEdit({ marker: "R" })}>R</button>
          <button className={edits.marker === "L" ? "active" : ""}
            onClick={() => updateEdit({ marker: "L" })}>L</button>
          <button className={!edits.marker ? "active" : ""}
            onClick={() => updateEdit({ marker: null })}>—</button>
        </div>
        <p style={{fontSize: 11, color: "var(--fg-3)", marginTop: 8, marginBottom: 0}}>
          Apply Right or Left to indicate body orientation before submitting.
        </p>
      </div>
    </div>
  );
}

// ---------------- AI panel ----------------
function AIPanel({ state, result, student, completedAt }) {
  return (
    <div className="ai-panel">
      <div className="ai-header">
        <div className="ai-title-row">
          {Icons.zap}
          AI Diagnostic
        </div>
        <span className="ai-tag">RESUME</span>
      </div>

      {state === "submitting" && (
        <div className="processing">
          <div className="spinner"></div>
          Storing edited DICOM and dispatching to AI Service…
        </div>
      )}
      {state === "ai_processing" && (
        <div className="processing">
          <div className="spinner"></div>
          Running diagnostic & resume… (~1.8s)
        </div>
      )}

      {state === "ai_done" && result && (
        <>
          <div className="ai-summary">
            <div style={{display: "flex", justifyContent: "space-between", alignItems: "center"}}>
              <div className="ai-summary-label">Primary impression</div>
              <SeverityBadge severity={result.severity} />
            </div>
            <div className="ai-summary-text">{result.summary}</div>
            <div className="ai-confidence-bar">
              <span>conf</span>
              <div className="ai-conf-track">
                <div className="ai-conf-fill" style={{width: `${result.confidence * 100}%`}} />
              </div>
              <span>{(result.confidence * 100).toFixed(0)}%</span>
            </div>
          </div>

          <div>
            <div className="tools-section-title" style={{marginBottom: 6}}>Findings</div>
            <div className="ai-findings-list">
              {result.items.map((it, i) => (
                <div key={i} className="ai-finding">
                  <span className="ai-finding-label">{it.label}</span>
                  <SeverityBadge severity={it.severity} />
                  <span className="ai-finding-conf">{(it.confidence * 100).toFixed(0)}%</span>
                </div>
              ))}
            </div>
          </div>

          <div className="ai-plain">
            <div className="ai-plain-label">Plain-language summary (AI Resume → wali)</div>
            {result.plain}
          </div>

          <div style={{fontSize: 11, color: "var(--fg-3)", display: "flex", justifyContent: "space-between"}}>
            <span>Completed at <strong style={{color: "var(--fg)", fontFamily: "var(--font-mono)", fontWeight: 500}}>{completedAt}</strong></span>
            <span>Email → <strong style={{color: "var(--fg)"}}>wali.{student.name.split(" ")[0].toLowerCase()}@…</strong></span>
          </div>
        </>
      )}
    </div>
  );
}

// ---------------- Workflow list (during awaiting / converting) ----------------
function BoothWorkflowList({ boothState }) {
  const steps = [
    { k: "patient", label: "Patient profile auto-linked", done: boothState !== "idle", active: false },
    { k: "expose", label: "Expose patient on DDR", done: boothState !== "idle" && boothState !== "awaiting_npz", active: boothState === "awaiting_npz" },
    { k: "upload", label: "Upload .npz exposure", done: boothState !== "idle" && boothState !== "awaiting_npz", active: false },
    { k: "convert", label: "Conversion service: .npz → DICOM", done: boothState === "editing" || boothState === "submitting" || boothState === "ai_processing" || boothState === "ai_done", active: boothState === "converting" },
    { k: "edit", label: "Edit + submit DICOM", done: boothState === "submitting" || boothState === "ai_processing" || boothState === "ai_done", active: boothState === "editing" },
  ];
  return (
    <ol style={{listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 8}}>
      {steps.map(s => (
        <li key={s.k} style={{
          display: "grid", gridTemplateColumns: "20px 1fr", gap: 10, alignItems: "center",
          fontSize: 12,
          color: s.done ? "var(--fg)" : s.active ? "var(--accent)" : "var(--fg-3)"
        }}>
          <span style={{
            width: 16, height: 16, borderRadius: "50%",
            display: "grid", placeItems: "center",
            background: s.done ? "var(--fg)" : s.active ? "var(--accent-soft)" : "var(--surface-3)",
            color: s.done ? "var(--fg-inv)" : "var(--accent)",
            border: s.active ? "1px solid var(--accent)" : "1px solid var(--border-subtle)",
          }}>
            {s.done && <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M20 6 9 17l-5-5" /></svg>}
            {s.active && !s.done && <span style={{width: 6, height: 6, borderRadius: "50%", background: "var(--accent)"}}></span>}
          </span>
          <span>{s.label}</span>
        </li>
      ))}
    </ol>
  );
}

// ---------------- Booth idle state ----------------
function BoothIdle() {
  return (
    <div className="module booth">
      <div className="booth-left">
        <div className="patient-card">
          <div className="patient-card-label">
            {Icons.user}
            No active patient
          </div>
          <div className="patient-card-name" style={{color: "var(--fg-3)"}}>—</div>
          <div className="patient-card-id">Awaiting Front Desk to call next participant</div>
        </div>
      </div>
      <div className="booth-center">
        <div className="viewer-toolbar">
          <div className="viewer-state">
            <span className="live-dot" style={{background: "var(--border-strong)"}}></span>
            Booth idle
          </div>
        </div>
        <div className="viewer-stage">
          <EmptyStudentSlot>
            Booth is idle.<br/>Switch to the Front Desk module and call the next participant in queue.
          </EmptyStudentSlot>
        </div>
      </div>
      <div className="booth-right">
        <div className="tools-panel">
          <div className="tools-section">
            <div className="tools-section-title">Workflow</div>
            <BoothWorkflowList boothState="idle" />
          </div>
        </div>
      </div>
    </div>
  );
}

window.PhotoBooth = PhotoBooth;
