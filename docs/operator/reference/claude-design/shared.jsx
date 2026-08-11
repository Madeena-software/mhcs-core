// Shared atoms: icons, badges, buttons, x-ray placeholder
const { useState, useEffect, useRef, useMemo, useCallback } = React;

// ---------------- Icons (single-stroke, 1.5px) ----------------
function Icon({ d, size = 16, stroke = 1.5, fill = "none" }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke="currentColor"
      strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {typeof d === "string" ? <path d={d} /> : d}
    </svg>
  );
}
const Icons = {
  search: <Icon d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm10 2-4.3-4.3" />,
  check: <Icon d="M20 6 9 17l-5-5" />,
  arrowRight: <Icon d="M5 12h14M13 5l7 7-7 7" />,
  arrowLeft: <Icon d="M19 12H5M11 5l-7 7 7 7" />,
  user: <Icon d="M20 21a8 8 0 1 0-16 0M12 13a4 4 0 1 1 0-8 4 4 0 0 1 0 8Z" />,
  users: <Icon d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" />,
  upload: <Icon d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" />,
  rotateCw: <Icon d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5" />,
  rotateCcw: <Icon d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5" />,
  flipH: <Icon d={<>
    <path d="M12 3v18" />
    <path d="M8 7 4 11l4 4" />
    <path d="m16 7 4 4-4 4" />
  </>} />,
  flipV: <Icon d={<>
    <path d="M3 12h18" />
    <path d="m7 8 4-4 4 4" />
    <path d="m7 16 4 4 4-4" />
  </>} />,
  crop: <Icon d="M6 2v16a2 2 0 0 0 2 2h14M2 6h16a2 2 0 0 1 2 2v14" />,
  sun: <Icon d="M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10ZM12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42" />,
  reset: <Icon d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5M12 8v4l3 2" />,
  send: <Icon d="m22 2-7 20-4-9-9-4 20-7Z" />,
  monitor: <Icon d="M2 4h20v12H2zM8 20h8M12 16v4" />,
  clipboard: <Icon d="M9 2h6a1 1 0 0 1 1 1v2H8V3a1 1 0 0 1 1-1ZM8 5H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />,
  bell: <Icon d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10 21a2 2 0 0 0 4 0" />,
  zap: <Icon d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z" />,
  x: <Icon d="M18 6 6 18M6 6l12 12" />,
  plus: <Icon d="M12 5v14M5 12h14" />,
  minus: <Icon d="M5 12h14" />,
  chevDown: <Icon d="m6 9 6 6 6-6" />,
  dot: <Icon d="M12 12.01" stroke={3} />,
  more: <Icon d="M12 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM19 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM5 13a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" fill="currentColor" stroke={0} />,
};

// ---------------- Status badge ----------------
function StatusBadge({ status }) {
  const m = {
    not_arrived: { label: "Not arrived", tone: "neutral" },
    awaiting_queue: { label: "Waiting", tone: "amber" },
    in_progress: { label: "In booth", tone: "teal" },
    completed: { label: "Completed", tone: "green" },
    pending_ai: { label: "AI pending", tone: "amber" },
    ai_completed: { label: "AI done", tone: "blue" },
    email_sent: { label: "Email sent", tone: "green" },
    email_failed: { label: "Email failed", tone: "red" },
  };
  const v = m[status] || { label: status, tone: "neutral" };
  return <span className={`badge badge-${v.tone}`}>{v.label}</span>;
}

// ---------------- Severity badge ----------------
function SeverityBadge({ severity }) {
  const m = {
    normal: { label: "Normal", tone: "green" },
    mild: { label: "Mild", tone: "amber" },
    moderate: { label: "Moderate", tone: "red" },
    severe: { label: "Severe", tone: "red" },
  };
  const v = m[severity] || { label: severity, tone: "neutral" };
  return <span className={`pill pill-${v.tone}`}>{v.label}</span>;
}

// ---------------- Avatar (initials, deterministic tint) ----------------
function Avatar({ name, size = 32 }) {
  const initials = name.split(" ").slice(0, 2).map(s => s[0]).join("").toUpperCase();
  // Hash to pick muted tint
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) >>> 0;
  const tints = [
    "var(--tint-a)", "var(--tint-b)", "var(--tint-c)", "var(--tint-d)", "var(--tint-e)"
  ];
  const bg = tints[h % tints.length];
  return (
    <div className="avatar" style={{ width: size, height: size, background: bg, fontSize: size * 0.38 }}>
      {initials}
    </div>
  );
}

// ---------------- X-ray placeholder ----------------
// Diagonal-stripe pattern with a faint anatomical hint via SVG shapes
// (lung-shaped silhouettes only — not a recreation of an actual radiograph)
function XrayPlaceholder({ rotation = 0, flipH = false, flipV = false, windowLevel = 50, windowWidth = 50, marker = null, crop = null, label = "DDR-2024-EXPOSURE.npz" }) {
  // Compute a CSS filter from windowLevel / windowWidth
  const brightness = 0.5 + (windowLevel / 100) * 1.0;     // 0.5–1.5
  const contrast = 0.5 + (windowWidth / 100) * 1.5;       // 0.5–2.0
  const transform = `rotate(${rotation}deg) scaleX(${flipH ? -1 : 1}) scaleY(${flipV ? -1 : 1})`;

  return (
    <div className="xray-frame">
      <div className="xray-canvas" style={{ filter: `brightness(${brightness}) contrast(${contrast})`, transform }}>
        <svg viewBox="0 0 400 480" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
          <defs>
            <pattern id="stripes" patternUnits="userSpaceOnUse" width="14" height="14" patternTransform="rotate(45)">
              <rect width="14" height="14" fill="#0a0a0a" />
              <rect width="7" height="14" fill="#141414" />
            </pattern>
            <radialGradient id="vignette" cx="50%" cy="45%" r="65%">
              <stop offset="0%" stopColor="#1a1a1a" stopOpacity="0" />
              <stop offset="100%" stopColor="#000" stopOpacity="0.9" />
            </radialGradient>
            <linearGradient id="lungShade" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor="#262626" />
              <stop offset="100%" stopColor="#0e0e0e" />
            </linearGradient>
          </defs>
          {/* Background stripes */}
          <rect width="400" height="480" fill="url(#stripes)" />
          {/* Soft "ribcage" hint shapes (purely decorative, not real anatomy) */}
          <ellipse cx="140" cy="240" rx="70" ry="120" fill="url(#lungShade)" opacity="0.55" />
          <ellipse cx="260" cy="240" rx="70" ry="120" fill="url(#lungShade)" opacity="0.55" />
          <rect x="190" y="150" width="20" height="200" rx="6" fill="#1f1f1f" opacity="0.7" />
          {/* Faint rib curves */}
          {[0,1,2,3,4,5].map(i => (
            <g key={i} opacity="0.18" stroke="#3a3a3a" strokeWidth="1.5" fill="none">
              <path d={`M 60 ${170 + i*32} Q 200 ${150 + i*32} 340 ${170 + i*32}`} />
            </g>
          ))}
          {/* Vignette */}
          <rect width="400" height="480" fill="url(#vignette)" />
          {/* Crosshair tick marks */}
          <g stroke="#444" strokeWidth="1" opacity="0.5">
            <line x1="0" y1="240" x2="20" y2="240" />
            <line x1="380" y1="240" x2="400" y2="240" />
            <line x1="200" y1="0" x2="200" y2="20" />
            <line x1="200" y1="460" x2="200" y2="480" />
          </g>
          {/* Filename watermark */}
          <text x="12" y="470" fill="#666" fontFamily="JetBrains Mono, monospace" fontSize="10">{label}</text>
          <text x="388" y="20" fill="#666" fontFamily="JetBrains Mono, monospace" fontSize="10" textAnchor="end">PLACEHOLDER · NOT A REAL RADIOGRAPH</text>
        </svg>
      </div>
      {/* R/L marker overlay (sits outside transform so it stays readable) */}
      {marker && (
        <div className={`xray-marker xray-marker-${marker.toLowerCase()}`}>
          {marker}
        </div>
      )}
      {/* Crop overlay */}
      {crop && (
        <div className="xray-crop" style={{
          left: `${crop.x}%`, top: `${crop.y}%`,
          width: `${crop.w}%`, height: `${crop.h}%`,
        }}>
          <div className="xray-crop-mask top" />
          <div className="xray-crop-mask bottom" />
          <div className="xray-crop-mask left" />
          <div className="xray-crop-mask right" />
          {["tl","tr","bl","br"].map(c => <span key={c} className={`xray-crop-handle ${c}`} />)}
        </div>
      )}
    </div>
  );
}

// ---------------- Empty pattern (booth idle) ----------------
function EmptyStudentSlot({ children }) {
  return (
    <div className="empty-slot">
      <div className="empty-slot-art" aria-hidden="true">
        <svg viewBox="0 0 80 80" width="80" height="80">
          <defs>
            <pattern id="empty-stripes" patternUnits="userSpaceOnUse" width="6" height="6" patternTransform="rotate(45)">
              <rect width="3" height="6" fill="var(--border-subtle)" />
            </pattern>
          </defs>
          <rect width="80" height="80" rx="40" fill="url(#empty-stripes)" />
        </svg>
      </div>
      <div className="empty-slot-text">{children}</div>
    </div>
  );
}

Object.assign(window, {
  Icon, Icons, StatusBadge, SeverityBadge, Avatar, XrayPlaceholder, EmptyStudentSlot
});
