// Front Desk module — search & confirm worklist, waiting room, booth status
const { useState: useStateFD, useMemo: useMemoFD, useEffect: useEffectFD } = React;
const PAGE_SIZE = 12;

function FrontDesk({ students, setStudents, currentBoothStudentId, sendToBooth, onJumpToBooth, density }) {
  const [search, setSearch] = useStateFD("");
  const [filter, setFilter] = useStateFD("today"); // today | not_arrived | awaiting | completed
  const [selectedId, setSelectedId] = useStateFD(null);
  const [page, setPage] = useStateFD(1);

  // Today's worklist filtering
  const filtered = useMemoFD(() => {
    let list = students;
    if (filter === "not_arrived") list = list.filter(s => s.screeningStatus === "not_arrived");
    else if (filter === "awaiting") list = list.filter(s => s.screeningStatus === "awaiting_queue");
    else if (filter === "completed") list = list.filter(s => s.screeningStatus === "completed");
    if (search.trim()) {
      const q = search.toLowerCase();
      list = list.filter(s =>
        s.name.toLowerCase().includes(q) ||
        s.nisn.includes(q) ||
        (s.group && s.group.toLowerCase().includes(q))
      );
    }
    return list;
  }, [students, filter, search]);

  // Reset to page 1 whenever the filtered set changes shape
  useEffectFD(() => { setPage(1); }, [search, filter]);

  const pageCount = Math.max(1, Math.ceil(filtered.length / PAGE_SIZE));
  const safePage = Math.min(page, pageCount);
  const pageStart = (safePage - 1) * PAGE_SIZE;
  const pageEnd = Math.min(filtered.length, pageStart + PAGE_SIZE);
  const pageItems = filtered.slice(pageStart, pageEnd);

  // Stats
  const stats = useMemoFD(() => {
    const completed = students.filter(s => s.screeningStatus === "completed").length;
    const waiting = students.filter(s => s.screeningStatus === "awaiting_queue").length;
    const inProgress = students.filter(s => s.screeningStatus === "in_progress").length;
    const total = students.length;
    return { completed, waiting, inProgress, total, quotaPct: Math.round((completed / total) * 100) };
  }, [students]);

  // Waiting room — ordered by queue number
  const waiting = useMemoFD(
    () => students.filter(s => s.screeningStatus === "awaiting_queue")
      .sort((a, b) => a.queueNumber - b.queueNumber),
    [students]
  );

  // Currently in booth
  const inBoothStudent = students.find(s => s.id === currentBoothStudentId) || null;

  function confirmArrival(id) {
    setStudents(prev => {
      const maxQ = prev.reduce((m, s) => s.queueNumber ? Math.max(m, s.queueNumber) : m, 0);
      return prev.map(s => s.id === id
        ? { ...s, screeningStatus: "awaiting_queue", queueNumber: maxQ + 1, confirmedAt: nowStamp() }
        : s
      );
    });
  }

  function callNext() {
    if (!waiting.length || inBoothStudent) return;
    sendToBooth(waiting[0].id);
  }

  return (
    <div className="module frontdesk">
      {/* LEFT: today's worklist */}
      <div className="fd-pane">
        <div className="stats-strip">
          <Stat label="Daily Quota" value={stats.completed} total={50} accent="accent" />
          <Stat label="Waiting" value={stats.waiting} accent="amber" />
          <Stat label="In Booth" value={stats.inProgress} accent="teal" />
          <Stat label="Not arrived" value={Math.max(0, 50 - stats.completed - stats.waiting - stats.inProgress)} accent="neutral" />
        </div>

        <div className="search-wrap">
          <div className="search-input">
            {Icons.search}
            <input
              type="text"
              placeholder="Search by name or ID number…"
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
            {search && (
              <button className="btn-ghost btn btn-icon" onClick={() => setSearch("")} aria-label="Clear">
                {Icons.x}
              </button>
            )}
          </div>
          <div className="filter-chips">
            {[
              { k: "today", label: "All" },
              { k: "not_arrived", label: "Not arrived" },
              { k: "awaiting", label: "Waiting" },
              { k: "completed", label: "Completed" },
            ].map(f => (
              <button key={f.k}
                className={`filter-chip ${filter === f.k ? "active" : ""}`}
                onClick={() => setFilter(f.k)}>
                {f.label}
              </button>
            ))}
          </div>
        </div>

        <div className="worklist">
          {filtered.length === 0
            ? <div className="no-students">No participants match your search.</div>
            : pageItems.map(s => (
              <WorklistRow key={s.id}
                student={s}
                selected={selectedId === s.id}
                onSelect={() => setSelectedId(prev => prev === s.id ? null : s.id)}
                onConfirm={() => confirmArrival(s.id)}
              />
            ))
          }
        </div>

        {filtered.length > 0 && (
          <Pagination
            page={safePage}
            pageCount={pageCount}
            pageStart={pageStart}
            pageEnd={pageEnd}
            total={filtered.length}
            onChange={setPage}
          />
        )}
      </div>

      {/* CENTER: waiting room */}
      <div className="fd-pane">
        <div className="pane-header">
          <div>
            <div className="pane-title">
              Waiting Room
              <span className="pane-title-count">{waiting.length}</span>
            </div>
            <div className="pane-subtitle">Sequential queue — next called to booth</div>
          </div>
          <button
            className="btn btn-fg btn-lg"
            disabled={!waiting.length || !!inBoothStudent}
            onClick={callNext}>
            Call & Send to Booth
            {Icons.arrowRight}
          </button>
        </div>

        {waiting.length === 0 ? (
          <div className="empty-waiting">
            <EmptyStudentSlot>
              No participants currently waiting.<br />
              Confirm an arrival to fill the queue.
            </EmptyStudentSlot>
          </div>
        ) : (
          <div className="waiting-room">
            {waiting.map((s, i) => (
              <div key={s.id} className={`waiting-card ${i === 0 ? "next" : ""}`}>
                <div className="queue-num">{String(s.queueNumber).padStart(2, "0")}</div>
                <div className="waiting-card-body">
                  <div className="waiting-card-name">{s.name}</div>
                  <div className="waiting-card-meta">
                    <span>NISN {s.nisn}</span>
                    <span>since {s.confirmedAt}</span>
                  </div>
                </div>
                {i === 0 && !inBoothStudent ? (
                  <button className="btn btn-primary btn-sm" onClick={() => sendToBooth(s.id)}>
                    Send →
                  </button>
                ) : (
                  <span className="badge badge-amber">Wait #{s.queueNumber}</span>
                )}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* RIGHT: booth status + daily progress */}
      <div className="fd-pane">
        <div className="pane-header">
          <div className="pane-title">
            {Icons.monitor}
            Booth Status
          </div>
          <span className="live-indicator">
            <span className="live-dot"></span>
            Live
          </span>
        </div>

        <div className="booth-status">
          <div className="booth-card">
            <div className="booth-card-header">
              <div className="booth-card-title">Now in booth</div>
              <div className="booth-card-meta">Room 1 · DDR-A</div>
            </div>
            {inBoothStudent ? (
              <div className="booth-active">
                <div className="booth-active-patient">
                  <Avatar name={inBoothStudent.name} size={44} />
                  <div>
                    <div className="booth-active-name">{inBoothStudent.name}</div>
                    <div className="booth-active-sub">Queue #{inBoothStudent.queueNumber} · {genderLabel(inBoothStudent.gender)}</div>
                  </div>
                </div>
                <dl className="kvlist">
                  <dt>Called</dt><dd>{inBoothStudent.calledAt || "—"}</dd>
                  <dt>NISN</dt><dd>{inBoothStudent.nisn}</dd>
                  <dt>Gender</dt><dd>{genderLabel(inBoothStudent.gender)}</dd>
                </dl>
                <button className="btn btn-ghost btn-sm" onClick={onJumpToBooth}>
                  Open Photo Booth →
                </button>
              </div>
            ) : (
              <EmptyStudentSlot>
                Booth idle. Call the next participant to begin imaging.
              </EmptyStudentSlot>
            )}
          </div>

          <div className="booth-card">
            <div className="booth-card-header">
              <div className="booth-card-title">Today's Progress</div>
              <div className="booth-card-meta">{stats.completed} of 50 · {stats.quotaPct}%</div>
            </div>
            <ProgressRing percent={stats.quotaPct} stats={stats} />
          </div>

          <div className="booth-card">
            <div className="booth-card-header">
              <div className="booth-card-title">Program Information</div>
            </div>
            <dl className="kvlist">
              <dt>Project</dt><dd style={{fontFamily: "var(--font-sans)"}}>Pesantren Al-Hidayah</dd>
              <dt>Day</dt><dd>4 of 20</dd>
              <dt>Total</dt><dd>1.000 participants</dd>
              <dt>Quota/day</dt><dd>50 participants</dd>
            </dl>
          </div>
        </div>
      </div>
    </div>
  );
}

function nowStamp() {
  const d = new Date();
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
}

function genderLabel(g) {
  return g === "L" ? "M" : g === "P" ? "F" : "—";
}

function Stat({ label, value, total, accent = "neutral" }) {
  const pct = total ? Math.min(100, (value / total) * 100) : 0;
  return (
    <div className="stat">
      <div className="stat-label">{label}</div>
      <div className="stat-value">
        {String(value).padStart(2, "0")}
        {total && <small>/ {total}</small>}
      </div>
      {total && (
        <div className="stat-bar">
          <div className="stat-bar-fill" style={{
            width: `${pct}%`,
            background: accent === "amber" ? "var(--amber)" :
                       accent === "teal" ? "var(--accent)" : "var(--accent)"
          }} />
        </div>
      )}
    </div>
  );
}

function WorklistRow({ student, selected, onSelect, onConfirm }) {
  const canConfirm = student.screeningStatus === "not_arrived";
  return (
    <>
      <div className={`row ${selected ? "selected" : ""}`} onClick={onSelect}>
        <Avatar name={student.name} size={28} />
        <div className="row-main">
          <div className="row-name">{student.name}</div>
          <div className="row-meta">
            <span>NISN {student.nisn}</span>
          </div>
        </div>
        <StatusBadge status={student.screeningStatus} />
        <div className="row-actions">
          {canConfirm ? (
            <button className="btn btn-primary btn-sm" onClick={(e) => { e.stopPropagation(); onConfirm(); }}>
              {Icons.check}
              Confirm
            </button>
          ) : student.screeningStatus === "awaiting_queue" ? (
            <span className="badge badge-amber" style={{fontFamily: "var(--font-mono)"}}>#{student.queueNumber}</span>
          ) : student.screeningStatus === "completed" ? (
            <StatusBadge status={student.aiStatus || "ai_completed"} />
          ) : null}
        </div>
      </div>
      {selected && <ParticipantDetail student={student} onClose={onSelect} />}
    </>
  );
}

function ParticipantDetail({ student, onClose }) {
  return (
    <div className="row-detail" onClick={onClose}>
      <div className="row-detail-grid">
        <div className="detail-field">
          <span className="detail-field-label">Gender</span>
          <span className="detail-field-value">{genderLabel(student.gender)}</span>
        </div>
        <div className="detail-field">
          <span className="detail-field-label">Date of birth</span>
          <span className="detail-field-value">{student.dob}</span>
        </div>
        <div className="detail-field">
          <span className="detail-field-label">Queue #</span>
          <span className="detail-field-value">{student.queueNumber ? `#${String(student.queueNumber).padStart(3, "0")}` : <em style={{color: "var(--fg-4)"}}>—</em>}</span>
        </div>
        <div className="detail-field">
          <span className="detail-field-label">Phone</span>
          <span className="detail-field-value">{student.phone}</span>
        </div>
        <div className="detail-field wide">
          <span className="detail-field-label">Email</span>
          <span className="detail-field-value" style={{wordBreak: "break-all"}}>{student.email}</span>
        </div>
      </div>
    </div>
  );
}

function ProgressRing({ percent, stats }) {
  const r = 32;
  const c = 2 * Math.PI * r;
  const dash = (percent / 100) * c;
  return (
    <div className="progress-ring">
      <svg className="ring-svg" width="80" height="80">
        <circle cx="40" cy="40" r={r} fill="none" strokeWidth="6" className="ring-bg" />
        <circle cx="40" cy="40" r={r} fill="none" strokeWidth="6" className="ring-fg"
          strokeDasharray={`${dash} ${c}`} strokeLinecap="round" />
        <text className="ring-text" x="40" y="44" textAnchor="middle">{percent}%</text>
      </svg>
      <div className="progress-list">
        <div className="progress-line">
          <span className="progress-line-dot" style={{background: "var(--accent)"}} />
          <span className="progress-line-label">Completed</span>
          <span className="progress-line-value">{stats.completed}</span>
        </div>
        <div className="progress-line">
          <span className="progress-line-dot" style={{background: "var(--amber)"}} />
          <span className="progress-line-label">Waiting</span>
          <span className="progress-line-value">{stats.waiting}</span>
        </div>
        <div className="progress-line">
          <span className="progress-line-dot" style={{background: "var(--border-strong)"}} />
          <span className="progress-line-label">Not arrived</span>
          <span className="progress-line-value">{Math.max(0, 50 - stats.completed - stats.waiting - stats.inProgress)}</span>
        </div>
      </div>
    </div>
  );
}

window.FrontDesk = FrontDesk;

// ---------------- Pagination ----------------
function Pagination({ page, pageCount, pageStart, pageEnd, total, onChange }) {
  // Compact page-number list with ellipses (e.g. 1 … 4 5 6 … 24)
  const nums = useMemoFD(() => {
    const out = [];
    const push = (v) => out.push(v);
    if (pageCount <= 7) {
      for (let i = 1; i <= pageCount; i++) push(i);
    } else {
      push(1);
      const start = Math.max(2, page - 1);
      const end = Math.min(pageCount - 1, page + 1);
      if (start > 2) push("…l");
      for (let i = start; i <= end; i++) push(i);
      if (end < pageCount - 1) push("…r");
      push(pageCount);
    }
    return out;
  }, [page, pageCount]);

  return (
    <div className="pagination">
      <div className="pagination-info">
        Showing <strong>{pageStart + 1}</strong>–<strong>{pageEnd}</strong> of <strong>{total}</strong>
      </div>
      <div className="pagination-controls">
        <button className="page-btn" disabled={page <= 1} onClick={() => onChange(page - 1)} aria-label="Previous page">
          {Icons.arrowLeft}
        </button>
        {nums.map((n, i) =>
          typeof n === "number" ? (
            <button
              key={i}
              className={`page-btn ${n === page ? "active" : ""}`}
              onClick={() => onChange(n)}>
              {n}
            </button>
          ) : (
            <span key={i} className="page-ellipsis">…</span>
          )
        )}
        <button className="page-btn" disabled={page >= pageCount} onClick={() => onChange(page + 1)} aria-label="Next page">
          {Icons.arrowRight}
        </button>
      </div>
    </div>
  );
}
