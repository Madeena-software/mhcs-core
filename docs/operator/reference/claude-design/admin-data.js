// Admin sample data — organizations, projects, radiographers, KPI series
// Reuses participant generator from data.js

const ORG_TYPES = [
  { key: "pesantren",  label: "Pesantren",  identityType: "NISN" },
  { key: "school",     label: "School",     identityType: "NISN" },
  { key: "corporate",  label: "Corporate",  identityType: "Employee ID" },
  { key: "government", label: "Government", identityType: "NIK" },
];

const ORGS = [
  { id: "ORG-001", name: "Pesantren Al-Hidayah",       type: "pesantren",  identityType: "NISN",        city: "Bandung",    contact: "H. Abdul Karim",          contactEmail: "kontak@al-hidayah.sch.id", contactPhone: "+62 22 5512 0991", mou: "2026-04-12", status: "active",  totalParticipants: 1000 },
  { id: "ORG-002", name: "SMA Negeri 3 Yogyakarta",     type: "school",     identityType: "NISN",        city: "Yogyakarta", contact: "Drs. Sutrisno Wibowo",    contactEmail: "tu@sman3-yog.sch.id",      contactPhone: "+62 274 8821 4400", mou: "2026-03-28", status: "active",  totalParticipants: 720 },
  { id: "ORG-003", name: "PT Sumber Makmur Sentosa",    type: "corporate",  identityType: "Employee ID", city: "Jakarta",    contact: "Ibu Rini Marpaung (HRD)", contactEmail: "hrd@sumbermakmur.co.id",   contactPhone: "+62 21 4422 9100",  mou: "2026-04-30", status: "active",  totalParticipants: 480 },
  { id: "ORG-004", name: "Pesantren Darul Ulum Jombang",type: "pesantren",  identityType: "NISN",        city: "Jombang",    contact: "KH. Fauzi Nasution",      contactEmail: "info@darululum.or.id",     contactPhone: "+62 321 8011 332",  mou: "2026-05-02", status: "pending", totalParticipants: 1400 },
  { id: "ORG-005", name: "Dinas Kesehatan Kab. Garut",  type: "government", identityType: "NIK",         city: "Garut",      contact: "dr. Andi Pratama",        contactEmail: "screening@dinkes-garut.go.id", contactPhone: "+62 262 233 1100", mou: "2026-02-14", status: "completed",totalParticipants: 320 },
  { id: "ORG-006", name: "SMK Telkom Malang",           type: "school",     identityType: "NISN",        city: "Malang",     contact: "Bp. Hendra Saputra",      contactEmail: "ppdb@smktelkom-mlg.sch.id", contactPhone: "+62 341 4012 887", mou: "2026-05-18", status: "draft",   totalParticipants: 880 },
];

const PROJECTS = [
  {
    id: "PRJ-001", code: "ALHID-2026-1", name: "Skrining Tahunan Santri Al-Hidayah",
    orgId: "ORG-001", startDate: "2026-05-20", endDate: "2026-06-14", workingDays: 20,
    totalParticipants: 1000, dailyQuota: 50, completed: 153,
    booths: 1, status: "active", assignedRadiographers: ["RAD-001", "RAD-002"],
    todayCompleted: 32, todayWaiting: 8, todayInBooth: 1, todayNoShow: 2,
    emailSent: 148, emailFailed: 5,
  },
  {
    id: "PRJ-002", code: "SMA3YOG-2026-1", name: "Skrining Awal Tahun SMAN 3",
    orgId: "ORG-002", startDate: "2026-05-12", endDate: "2026-06-02", workingDays: 15,
    totalParticipants: 720, dailyQuota: 48, completed: 432,
    booths: 1, status: "active", assignedRadiographers: ["RAD-003"],
    todayCompleted: 41, todayWaiting: 6, todayInBooth: 1, todayNoShow: 0,
    emailSent: 421, emailFailed: 11,
  },
  {
    id: "PRJ-003", code: "SMSPT-2026-1", name: "Annual Medical Check-up Karyawan",
    orgId: "ORG-003", startDate: "2026-05-25", endDate: "2026-06-08", workingDays: 10,
    totalParticipants: 480, dailyQuota: 48, completed: 0,
    booths: 1, status: "scheduled", assignedRadiographers: ["RAD-004", "RAD-005"],
    todayCompleted: 0, todayWaiting: 0, todayInBooth: 0, todayNoShow: 0,
    emailSent: 0, emailFailed: 0,
  },
  {
    id: "PRJ-004", code: "GARUT-2026-1", name: "Program Skrining Massal Garut",
    orgId: "ORG-005", startDate: "2026-02-20", endDate: "2026-03-10", workingDays: 14,
    totalParticipants: 320, dailyQuota: 23, completed: 320,
    booths: 1, status: "completed", assignedRadiographers: ["RAD-001"],
    todayCompleted: 0, todayWaiting: 0, todayInBooth: 0, todayNoShow: 0,
    emailSent: 311, emailFailed: 9,
  },
];

const RADIOGRAPHERS = [
  { id: "RAD-001", name: "Dr. Putri Andini",        email: "putri.andini@madeena.id",      phone: "+62 813 4421 7790", status: "active",   lastSeen: "2 min ago",  assignedProjects: ["PRJ-001"],          createdAt: "2026-01-08", scans: 412 },
  { id: "RAD-002", name: "Bp. Arif Hasibuan",       email: "arif.hasibuan@madeena.id",     phone: "+62 821 5519 0023", status: "active",   lastSeen: "8 min ago",  assignedProjects: ["PRJ-001"],          createdAt: "2026-01-08", scans: 388 },
  { id: "RAD-003", name: "Bu. Salma Khairunnisa",   email: "salma.khairunnisa@madeena.id", phone: "+62 815 6612 4488", status: "active",   lastSeen: "just now",   assignedProjects: ["PRJ-002"],          createdAt: "2026-02-21", scans: 426 },
  { id: "RAD-004", name: "Dr. Iqbal Nasution",      email: "iqbal.nasution@madeena.id",    phone: "+62 819 3318 7741", status: "active",   lastSeen: "yesterday",  assignedProjects: ["PRJ-003"],          createdAt: "2026-03-04", scans: 219 },
  { id: "RAD-005", name: "Bp. Hafidz Maulana",      email: "hafidz.maulana@madeena.id",    phone: "+62 822 8809 1133", status: "pending",  lastSeen: "never",      assignedProjects: ["PRJ-003"],          createdAt: "2026-05-22", scans: 0 },
  { id: "RAD-006", name: "Dr. Zaki Pratama",        email: "zaki.pratama@madeena.id",      phone: "+62 812 7716 5520", status: "disabled", lastSeen: "5 days ago", assignedProjects: [],                   createdAt: "2025-12-10", scans: 178 },
];

// 14-day sparkline series for the dashboard
function genDailySeries(seed) {
  const rng = window.MMSSData ? null : null;
  // simple deterministic series
  const out = [];
  let v = 32 + (seed % 8);
  for (let i = 0; i < 14; i++) {
    v += Math.sin(i + seed) * 4 + ((seed * (i + 1)) % 9) - 4;
    out.push(Math.max(10, Math.round(v)));
  }
  return out;
}

const KPI_SERIES = {
  scansPerDay: genDailySeries(7),
  emailsPerDay: genDailySeries(11),
  attendancePct: genDailySeries(3).map(x => Math.min(99, 70 + (x % 25))),
};

// Helpers
function orgById(id) { return ORGS.find(o => o.id === id); }
function projectById(id) { return PROJECTS.find(p => p.id === id); }
function radiographerById(id) { return RADIOGRAPHERS.find(r => r.id === id); }
function projectsByOrg(orgId) { return PROJECTS.filter(p => p.orgId === orgId); }

window.MMSSAdminData = {
  ORG_TYPES, ORGS, PROJECTS, RADIOGRAPHERS, KPI_SERIES,
  orgById, projectById, radiographerById, projectsByOrg,
};
