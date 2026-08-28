import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';

test('inspects the actual FormData radiograph and gain Files', async () => {
  const radiograph = new File([Buffer.from('radiograph')], 'radiograph.npz');
  const gain = new File([Buffer.from('gain')], 'gain.npz');
  const form = new FormData();
  form.append('radiograph_npz', radiograph);
  form.append('gain_npz', gain);

  const evidence = await inspectFormData(form);

  assert.equal(evidence.radiograph.filename, 'radiograph.npz');
  assert.equal(evidence.radiograph.bytes, radiograph.size);
  assert.equal(evidence.gain.filename, 'gain.npz');
  assert.equal(evidence.gain.sha256, createHash('sha256').update('gain').digest('hex'));
});

test('detects the exact target and preserves distinguishable non-target ZIP members', async () => {
  const bytes = makeStoredZip([
    ['processedimage.npy', 'target'],
    ['processedimage.npy.backup', 'similar'],
    ['other.npy', 'other'],
  ]);
  const evidence = await inspectFile(new File([bytes], 'radiograph.npz'));

  assert.equal(evidence.target_member_present, true);
  assert.deepEqual(evidence.member_names, [
    'processedimage.npy',
    'processedimage.npy.backup',
    'other.npy',
  ]);
  assert.deepEqual(evidence.non_target_members, [
    'processedimage.npy.backup',
    'other.npy',
  ]);
});

test('calculates sanitized transmitted size and SHA evidence', async () => {
  const file = new File([Buffer.from('safe bytes')], 'radiograph.npz');
  const evidence = await inspectFile(file);

  assert.deepEqual(evidence, {
    filename: 'radiograph.npz',
    bytes: 10,
    sha256: createHash('sha256').update('safe bytes').digest('hex'),
    member_names: [],
    non_target_members: [],
    target_member_present: false,
    target_member_absent: true,
  });
});

test('browser instrumentation observes the original File without replacing it', async () => {
  const { chromium } = await import('playwright');
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await installUploadObserver(page);
  await page.route('https://local.test/', route => route.fulfill({ body: '<script>window.run = async () => { const form = new FormData(); form.append("radiograph_npz", new File(["same"], "same.npz")); form.append("gain_npz", new File(["gain"], "gain.npz")); try { await fetch("https://local.test/upload", { method: "POST", body: form }); } catch {} };</script>' }));
  await page.route('**/upload', route => route.abort());
  await page.goto('https://local.test/');
  await page.evaluate(() => window.run());
  await page.waitForTimeout(25);
  const result = await getObservedEvidence(page);
  await browser.close();

  assert.equal(result.request_count, 1);
  assert.equal(result.evidence.length, 1);
  assert.equal(result.evidence[0].radiograph.bytes, 4);
  assert.equal(result.evidence[0].radiograph.filename, 'same.npz');
  assert.equal(result.evidence[0].gain.filename, 'gain.npz');
});

test('normalization failure is fail-closed with no request and no heavy fallback', async () => {
  const decision = normalizationFailureDecision(new Error('ambiguous ZIP'));

  assert.deepEqual(decision, { upload: false, fallback: false, failure_family: 'normalization' });
});

test('sanitized evidence excludes payloads, credentials, and private identifiers', () => {
  const evidence = sanitizeEvidence({
    radiograph: { bytes: 4, sha256: 'a'.repeat(64), payload: 'secret' },
    gain: { bytes: 4, sha256: 'b'.repeat(64) },
    cookie: 'secret',
    object_key: 'private/key',
  });

  assert.deepEqual(evidence, {
    radiograph: { bytes: 4, sha256: 'a'.repeat(64) },
    gain: { bytes: 4, sha256: 'b'.repeat(64) },
  });
});

function makeStoredZip(entries) {
  const chunks = [];
  let offset = 0;
  const central = [];
  for (const [name, payload] of entries) {
    const nameBytes = Buffer.from(name);
    const data = Buffer.from(payload);
    const local = Buffer.alloc(30 + nameBytes.length + data.length);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);
    local.writeUInt16LE(0, 6);
    local.writeUInt16LE(0, 8);
    local.writeUInt32LE(0, 10);
    local.writeUInt32LE(crc32(data), 14);
    local.writeUInt32LE(data.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(nameBytes.length, 26);
    nameBytes.copy(local, 30);
    data.copy(local, 30 + nameBytes.length);
    chunks.push(local);
    const directory = Buffer.alloc(46 + nameBytes.length);
    directory.writeUInt32LE(0x02014b50, 0);
    directory.writeUInt16LE(20, 4);
    directory.writeUInt16LE(20, 6);
    directory.writeUInt16LE(0, 8);
    directory.writeUInt16LE(0, 10);
    directory.writeUInt32LE(0, 12);
    directory.writeUInt32LE(crc32(data), 16);
    directory.writeUInt32LE(data.length, 20);
    directory.writeUInt32LE(data.length, 24);
    directory.writeUInt16LE(nameBytes.length, 28);
    directory.writeUInt32LE(offset, 42);
    nameBytes.copy(directory, 46);
    central.push(directory);
    offset += local.length;
  }
  const directoryBytes = Buffer.concat(central);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(directoryBytes.length, 12);
  end.writeUInt32LE(offset, 16);
  return Buffer.concat([...chunks, directoryBytes, end]);
}

function crc32(bytes) {
  let crc = 0xffffffff;
  for (const byte of bytes) {
    crc ^= byte;
    for (let bit = 0; bit < 8; bit += 1) crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
  }
  return (crc ^ 0xffffffff) >>> 0;
}

async function inspectFormData(form) {
  const radiograph = form.get('radiograph_npz');
  const gain = form.get('gain_npz');
  return {
    radiograph: await inspectFile(radiograph),
    gain: await inspectFile(gain),
  };
}

async function inspectFile(file) {
  const bytes = new Uint8Array(await file.arrayBuffer());
  const zip = parseZipMembers(bytes);
  return {
    filename: file.name,
    bytes: bytes.byteLength,
    sha256: createHash('sha256').update(bytes).digest('hex'),
    member_names: zip.map(member => member.name),
    non_target_members: zip.filter(member => member.name !== 'processedimage.npy').map(member => member.name),
    target_member_present: zip.some(member => member.name === 'processedimage.npy'),
    target_member_absent: !zip.some(member => member.name === 'processedimage.npy'),
  };
}

function parseZipMembers(bytes) {
  const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
  const end = Math.max(0, bytes.length - 0xffff - 22);
  let eocd = -1;
  for (let offset = bytes.length - 22; offset >= end; offset -= 1) {
    if (view.getUint32(offset, true) === 0x06054b50) { eocd = offset; break; }
  }
  if (eocd < 0) return [];
  const count = view.getUint16(eocd + 10, true);
  const directoryOffset = view.getUint32(eocd + 16, true);
  const members = [];
  let offset = directoryOffset;
  for (let index = 0; index < count && offset + 46 <= bytes.length; index += 1) {
    if (view.getUint32(offset, true) !== 0x02014b50) return [];
    const nameLength = view.getUint16(offset + 28, true);
    const name = new TextDecoder().decode(bytes.slice(offset + 46, offset + 46 + nameLength));
    members.push({ name, crc32: view.getUint32(offset + 16, true), compressed_bytes: view.getUint32(offset + 20, true), bytes: view.getUint32(offset + 24, true) });
    offset += 46 + nameLength + view.getUint16(offset + 30, true) + view.getUint16(offset + 32, true);
  }
  return members;
}

function normalizationFailureDecision() {
  return { upload: false, fallback: false, failure_family: 'normalization' };
}

function sanitizeEvidence(evidence) {
  return {
    radiograph: pickEvidence(evidence.radiograph),
    gain: pickEvidence(evidence.gain),
  };
}

function pickEvidence(value) {
  return { bytes: value.bytes, sha256: value.sha256 };
}

async function installUploadObserver(page) {
  await page.addInitScript({ content: `(${browserObserver.toString()})()` });
}

async function getObservedEvidence(page) {
  return page.evaluate(async () => {
    await Promise.all(window.__mhcsBrowserHarness.pending);
    return {
      request_count: window.__mhcsBrowserHarness.request_count,
      source_evidence: window.__mhcsBrowserHarness.source_evidence,
      evidence: window.__mhcsBrowserHarness.evidence,
      upload_telemetry: window.__mhcsBrowserHarness.upload_telemetry,
    };
  });
}

function browserObserver() {
  const state = window.__mhcsBrowserHarness = { request_count: 0, source_evidence: { radiograph: null, gain: null }, evidence: [], upload_telemetry: [], pending: [] };
  const hex = bytes => [...new Uint8Array(bytes)].map(byte => byte.toString(16).padStart(2, '0')).join('');
  const inspect = async file => {
    const bytes = new Uint8Array(await file.arrayBuffer());
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    const members = zipMembers(bytes);
    return { filename: file.name, bytes: bytes.byteLength, sha256: hex(digest), member_names: members.map(member => member.name), non_target_members: members.filter(member => member.name !== 'processedimage.npy').map(member => member.name), target_member_present: members.some(member => member.name === 'processedimage.npy'), target_member_absent: !members.some(member => member.name === 'processedimage.npy') };
  };
  const inspectBody = async body => {
    if (!(body instanceof FormData)) return null;
    const radiograph = body.get('radiograph_npz');
    const gain = body.get('gain_npz');
    if (!(radiograph instanceof File) || !(gain instanceof File)) return null;
    return { radiograph: await inspect(radiograph), gain: await inspect(gain) };
  };
  const record = body => {
    if (!(body instanceof FormData)) return;
    state.request_count += 1;
    state.pending.push(inspectBody(body).then(evidence => { if (evidence) state.evidence.push(evidence); }));
  };
  document.addEventListener('change', event => {
    const file = event.target?.files?.[0];
    if (!file || !['radiograph_npz', 'gain_npz'].includes(event.target.name)) return;
    state.pending.push(inspect(file).then(evidence => { state.source_evidence[event.target.name === 'radiograph_npz' ? 'radiograph' : 'gain'] = evidence; }));
  }, true);
  const fetch = window.fetch;
  window.fetch = function (input, init) { record(init?.body); return fetch.apply(this, arguments); };
  const send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.send = function (body) {
    record(body);
    if (body instanceof FormData) this.upload.addEventListener('progress', event => state.upload_telemetry.push({ loaded: event.loaded, total: event.total, length_computable: event.lengthComputable }));
    return send.apply(this, arguments);
  };
  function zipMembers(bytes) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    let eocd = -1;
    for (let offset = bytes.length - 22; offset >= Math.max(0, bytes.length - 0xffff - 22); offset -= 1) if (view.getUint32(offset, true) === 0x06054b50) { eocd = offset; break; }
    if (eocd < 0) return [];
    const members = []; let offset = view.getUint32(eocd + 16, true); const count = view.getUint16(eocd + 10, true);
    for (let index = 0; index < count && offset + 46 <= bytes.length; index += 1) {
      if (view.getUint32(offset, true) !== 0x02014b50) return [];
      const length = view.getUint16(offset + 28, true);
      members.push({ name: new TextDecoder().decode(bytes.slice(offset + 46, offset + 46 + length)) });
      offset += 46 + length + view.getUint16(offset + 30, true) + view.getUint16(offset + 32, true);
    }
    return members;
  }
}

async function runProductionValidation() {
  const required = ['APP_URL', 'CAPTURE_URL', 'EXPECTED_APPLICATION_REVISION', 'GOVERNING_TASK_REVISION', 'AUTHORIZATION_MARKER', 'OPERATOR_EMAIL', 'OPERATOR_PASSWORD', 'RADIOGRAPH_PATH', 'GAIN_PATH'];
  if (required.some(name => !process.env[name])) throw new Error('missing required validation input');
  if (!/^[0-9a-f]{40}$/.test(process.env.EXPECTED_APPLICATION_REVISION)) throw new Error('invalid application revision');
  if (process.env.AUTHORIZATION_MARKER !== 'AUTHORIZE_ONE_PRODUCTION_NORMALIZED_RADIOGRAPH_RUN') throw new Error('invalid authorization marker');
  if (new URL(process.env.CAPTURE_URL).origin !== new URL(process.env.APP_URL).origin) throw new Error('capture URL is outside deployed application');
  const { chromium } = await import('playwright');
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext();
    const page = await context.newPage();
    await installUploadObserver(page);
    await page.goto(`${process.env.APP_URL}/operator/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"], input[name="identifier"]', process.env.OPERATOR_EMAIL);
    await page.fill('input[name="password"]', process.env.OPERATOR_PASSWORD);
    await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type="submit"]')]);
    await page.goto(process.env.CAPTURE_URL, { waitUntil: 'networkidle' });
    await page.setInputFiles('input[name="radiograph_npz"]', process.env.RADIOGRAPH_PATH);
    await page.setInputFiles('input[name="gain_npz"]', process.env.GAIN_PATH);
    await page.click('button[type="submit"]');
    try {
      await page.waitForFunction(() => window.__mhcsBrowserHarness.request_count > 0, null, { timeout: 30000 });
    } catch {
      console.log(JSON.stringify({ ...normalizationFailureDecision(), harness_revision: process.env.GITHUB_SHA, deployed_application_revision: process.env.EXPECTED_APPLICATION_REVISION, governing_task_revision: process.env.GOVERNING_TASK_REVISION }));
      throw new Error('normalization produced no upload request');
    }
    const observed = await getObservedEvidence(page);
    if (observed.request_count !== 1 || observed.evidence.length !== 1) throw new Error('at-most-one-upload contract failed');
    const { radiograph, gain } = observed.evidence[0];
    const source = observed.source_evidence;
    if (!source.radiograph?.target_member_present || !radiograph.target_member_absent || radiograph.bytes >= source.radiograph.bytes) throw new Error('normalized radiograph evidence failed');
    if (JSON.stringify(radiograph.non_target_members) !== JSON.stringify(source.radiograph.non_target_members)) throw new Error('non-target member preservation failed');
    if (gain.bytes !== source.gain.bytes || gain.sha256 !== source.gain.sha256) throw new Error('gain identity evidence failed');
    console.log(JSON.stringify({ harness_revision: process.env.GITHUB_SHA, deployed_application_revision: process.env.EXPECTED_APPLICATION_REVISION, governing_task_revision: process.env.GOVERNING_TASK_REVISION, request_count: observed.request_count, upload_telemetry: observed.upload_telemetry, original: source, transmitted: observed.evidence }));
  } finally {
    await browser.close();
  }
}

if (process.env.MHCS_PRODUCTION_HARNESS === '1') runProductionValidation().catch(error => { console.error(`failure_family=browser_validation`); process.exitCode = 1; });
