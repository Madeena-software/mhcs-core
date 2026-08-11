// Sample data for the participant database (generalized — works for school, pesantren, corporate)

const FIRST_NAMES_M = [
  "Ahmad", "Muhammad", "Abdurrahman", "Abdullah", "Hasan", "Husein", "Yusuf",
  "Ibrahim", "Ismail", "Umar", "Utsman", "Ali", "Zaid", "Bilal", "Fauzi",
  "Rizki", "Hakim", "Faisal", "Iqbal", "Reza", "Arif", "Naufal", "Hafidz",
  "Zaki", "Daffa", "Rafi", "Ridho", "Syahrul", "Taufiq", "Wahyu"
];
const FIRST_NAMES_F = [
  "Aisyah", "Siti", "Khadijah", "Fatimah", "Maryam", "Zahra", "Nadia",
  "Salma", "Hafidzah", "Anisa", "Putri", "Aulia", "Najwa", "Rania",
  "Shafa", "Alya", "Hanan", "Nurul", "Latifah", "Hasna", "Inayah",
  "Kamila", "Lulu", "Maulida", "Qonita"
];
const MIDDLE = [
  "Nur", "Putra", "Putri", "Pratama", "Ramadhan", "Hidayah", "Maulana",
  "Akbar", "Anggara", "Saputra", "Kurniawan", "Wijaya", "Santoso", "Hakim",
  "Setiawan", "Permana", "Firdaus", "Az-Zahra", "Khairunnisa"
];
const LAST = [
  "Siregar", "Nasution", "Harahap", "Lubis", "Hasibuan", "Pohan", "Tanjung",
  "Hutapea", "Simbolon", "Manurung", "Sitorus", "Pasaribu", "Marpaung",
  "Sembiring", "Ginting", "Sinaga", "Damanik", "Sihombing", "Panjaitan"
];

// Optional grouping label — not every organization provides this.
// Kept intentionally generic so the same UI works for schools, pesantren,
// or corporate participants.
const GROUPS = ["Group A", "Group B", "Group C", "Group D", "Group E", null, null];

// Deterministic pseudo-random so order is stable across reloads
function mulberry32(seed) {
  return function() {
    let t = (seed += 0x6D2B79F5);
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

function pick(rng, arr) { return arr[Math.floor(rng() * arr.length)]; }

function pad(n, w) { return String(n).padStart(w, "0"); }

function makeStudent(rng, i) {
  // 50/50 gender → class pool
  const isMale = rng() > 0.5;
  const first = pick(rng, isMale ? FIRST_NAMES_M : FIRST_NAMES_F);
  const mid = pick(rng, MIDDLE);
  const last = pick(rng, LAST);
  const name = `${first} ${mid} ${last}`;

  const classPool = GROUPS;
  const group = pick(rng, classPool);

  // NISN: 10 digits, year-based prefix
  const nisn = `00${70 + Math.floor(rng() * 8)}${pad(Math.floor(rng() * 10000000), 7)}`;

  // Age 15–18
  const year = 2008 + Math.floor(rng() * 3);
  const month = 1 + Math.floor(rng() * 12);
  const day = 1 + Math.floor(rng() * 28);
  const dob = `${year}-${pad(month, 2)}-${pad(day, 2)}`;

  // Guardian email — simple slug
  const slug = name.toLowerCase().replace(/[^a-z]+/g, ".").replace(/^\.|\.$/g, "");
  const email = `wali.${slug}@gmail.com`;

  const phone = `+62 8${Math.floor(rng() * 9) + 1}${pad(Math.floor(rng() * 100000000), 8)}`;

  return {
    id: `STU-${pad(i + 1, 4)}`,
    name,
    nisn,
    group,
    gender: isMale ? "L" : "P",
    dob,
    email,
    phone,
    screeningStatus: "not_arrived", // default
    aiStatus: null,
    queueNumber: null,
    confirmedAt: null,
    calledAt: null,
    completedAt: null,
    // For the prototype: one student per booth visit gets a different mock finding
    mockFindingId: Math.floor(rng() * MOCK_FINDINGS.length),
  };
}

// Mock AI findings — realistic radiology-style language
const MOCK_FINDINGS = [
  {
    summary: "No acute cardiopulmonary abnormality detected.",
    severity: "normal",
    confidence: 0.97,
    items: [
      { label: "Lung fields clear", confidence: 0.98, severity: "normal" },
      { label: "Heart size within normal limits", confidence: 0.96, severity: "normal" },
      { label: "Costophrenic angles sharp", confidence: 0.95, severity: "normal" },
      { label: "No pleural effusion", confidence: 0.97, severity: "normal" },
    ],
    plain: "Hasil foto rontgen dada anak Anda terlihat normal. Tidak ditemukan kelainan pada paru-paru maupun jantung. Tetap jaga pola hidup sehat dan istirahat yang cukup.",
  },
  {
    summary: "Mild bronchial wall thickening, right lower zone.",
    severity: "mild",
    confidence: 0.82,
    items: [
      { label: "Bronchial wall thickening (R lower)", confidence: 0.82, severity: "mild" },
      { label: "Lung fields otherwise clear", confidence: 0.91, severity: "normal" },
      { label: "Heart size normal", confidence: 0.96, severity: "normal" },
      { label: "No consolidation", confidence: 0.93, severity: "normal" },
    ],
    plain: "Ditemukan sedikit penebalan pada saluran napas kanan bawah. Tidak berbahaya, namun disarankan untuk berkonsultasi dengan dokter jika terdapat batuk yang berkepanjangan.",
  },
  {
    summary: "Possible early infiltrate, left upper lobe — recommend clinical correlation.",
    severity: "moderate",
    confidence: 0.74,
    items: [
      { label: "Suspicious infiltrate (L upper lobe)", confidence: 0.74, severity: "moderate" },
      { label: "No effusion", confidence: 0.94, severity: "normal" },
      { label: "Heart size normal", confidence: 0.95, severity: "normal" },
      { label: "Hila unremarkable", confidence: 0.88, severity: "normal" },
    ],
    plain: "Terdapat indikasi awal yang perlu pemeriksaan lebih lanjut pada paru-paru kiri bagian atas. Mohon segera konsultasikan hasil ini ke dokter atau fasilitas kesehatan terdekat.",
  },
  {
    summary: "No acute findings. Mild scoliosis noted.",
    severity: "normal",
    confidence: 0.93,
    items: [
      { label: "Lung fields clear", confidence: 0.96, severity: "normal" },
      { label: "Mild thoracic scoliosis", confidence: 0.78, severity: "mild" },
      { label: "Heart size normal", confidence: 0.95, severity: "normal" },
      { label: "No effusion", confidence: 0.97, severity: "normal" },
    ],
    plain: "Hasil foto rontgen menunjukkan kondisi paru-paru normal. Terdapat sedikit kelengkungan tulang belakang yang ringan dan umumnya tidak memerlukan tindakan khusus.",
  },
];

function generateStudents(count, seed = 42) {
  const rng = mulberry32(seed);
  const out = [];
  for (let i = 0; i < count; i++) out.push(makeStudent(rng, i));

  // Pre-seed some state so the workday already looks "in motion":
  // ~20% completed, ~15% awaiting_queue, ~2% in_progress, rest not_arrived
  const completedN = Math.floor(count * 0.20);
  const queuedN = Math.floor(count * 0.15);
  const inProgressN = 0; // start clean — user drives the booth

  let queueCounter = 1;
  // Completed (with AI results in various states)
  for (let i = 0; i < completedN; i++) {
    const s = out[i];
    s.screeningStatus = "completed";
    s.queueNumber = queueCounter++;
    s.confirmedAt = "08:" + pad(Math.floor(rng() * 60), 2);
    s.calledAt = "09:" + pad(Math.floor(rng() * 60), 2);
    s.completedAt = "09:" + pad(15 + Math.floor(rng() * 45), 2);
    const r = rng();
    s.aiStatus = r < 0.6 ? "email_sent" : r < 0.85 ? "ai_completed" : r < 0.95 ? "pending_ai" : "email_failed";
  }
  // Awaiting queue
  for (let i = completedN; i < completedN + queuedN; i++) {
    const s = out[i];
    s.screeningStatus = "awaiting_queue";
    s.queueNumber = queueCounter++;
    s.confirmedAt = "10:" + pad(Math.floor(rng() * 60), 2);
  }

  return out;
}

window.MMSSData = { generateStudents, MOCK_FINDINGS };
