import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const container = process.env.RAD_BROWSER_CONTAINER;
assert.match(container ?? '', /^mhcs-rad-browser(?:-[a-z0-9]+)?$/);
const base = 'http://127.0.0.1:18099';
const fixture = (...args) => execFileSync('docker.exe', ['exec', container, 'php', 'tests/Support/radiographer-browser-fixture.php', ...args], { encoding: 'utf8' }).trim();
const browser = await chromium.launch({ headless: true, args: ['--use-gl=angle', '--use-angle=swiftshader', '--enable-webgl', '--ignore-gpu-blocklist'] });
mkdirSync('tests/Browser/Screenshots', { recursive: true });
let journeys = 0;
try {
    for (const path of ['direct', 'converted', 'supplied']) {
        const f = JSON.parse(fixture('seed', path));
        const context = await browser.newContext({ baseURL: base, viewport: { width: 1600, height: 1100 } });
        context.setDefaultTimeout(25000); context.setDefaultNavigationTimeout(25000);
        const page = await context.newPage();
        console.log(`${path}: fixture ready`);
        await page.goto('/admin/login');
        await page.locator('[id="form.email"]').fill(f.admin);
        await page.locator('[id="form.password"]').fill('password');
        await page.locator('button[type=submit]').click();
        await page.waitForURL('**/admin'); console.log(`${path}: Admin authenticated`);
        await page.goto(`/admin/study-worklist?${new URLSearchParams({facility:f.facility,date:f.date,session:f.session})}`);
        await page.getByRole('link', {name: 'Penugasan Radiografer / QC'}).click();
        await page.locator('#assigned-user').selectOption(f.workerId);
        await page.getByRole('button', {name: 'Terbitkan tautan'}).click();
        const entry = await page.locator('#assignment-url').inputValue(); console.log(`${path}: issued`);
        assert.match(entry, /signature=/); assert.ok(entry.startsWith(base + '/radiographer/entry/'));
        await page.locator('#manage-logout').click();
        await page.goto(entry);
        await page.locator('#identifier').fill(f.worker);
        await page.locator('#password').fill('password');
        await page.locator('button[type=submit]').click();
        try { await page.waitForURL('**/radiographer/workspaces/*'); } catch (error) { console.log('Workspace entry failed', new URL(page.url()).pathname, await page.locator('[role=alert]').allTextContents()); throw error; }
        const workspace = page.url(); console.log(`${path}: worker authenticated`);
        await page.waitForFunction(() => document.querySelector('[data-dicom-viewer]')?.dataset.viewerState === 'ready', {timeout: 60000});
        assert.ok(await page.locator('[data-testid=dicom-viewport] canvas').count() > 0);
        const pixels = await page.locator('[data-testid=dicom-viewport] canvas').first().evaluate(canvas => {
            const out = document.createElement('canvas'); out.width=canvas.width;out.height=canvas.height;
            const ctx=out.getContext('2d');ctx.drawImage(canvas,0,0);
            const data=ctx.getImageData(0,0,out.width,out.height).data;
            let low=255,high=0;for(let i=0;i<data.length;i+=4){low=Math.min(low,data[i]);high=Math.max(high,data[i]);}return high-low;
        });
        console.log(`${path}: rendered variation ${pixels}`);
        assert.ok(pixels > 0, 'Canvas must contain rendered pixel variation');
        assert.equal(await page.locator('a[href*="download"],a[href*="ai-report"]').count(), 0);
        await page.locator('[data-viewer-action="rotate-right"]:visible').first().click();
        await page.locator('#overlay-reason').focus();
        assert.equal(await page.locator('#overlay-reason').evaluate(el=>el===document.activeElement), true);
        await page.locator('#overlay-laterality').selectOption('not_applicable');
        for (const outcome of ['Tidak Layak', 'Perlu Eskalasi']) {
            await page.locator('#overlay-reason').fill('Synthetic technical review');
            await page.locator('#qc-outcome').selectOption(outcome);
            await page.locator('#qc-reason').fill('Synthetic non-Layak reason');
            await page.locator('#workspace-save-button').click();
            await page.getByText(`QC tersimpan: ${outcome}`).waitFor();
            await page.waitForFunction(() => document.querySelector('[data-dicom-viewer]')?.dataset.viewerState === 'ready');
        }
        await page.locator('#overlay-laterality').selectOption('not_applicable');
        await page.locator('#overlay-reason').fill('Synthetic image checked');
        for (const input of await page.locator('#workspace-save input[type=checkbox]').all()) await input.check();
        await page.locator('#workspace-save-button').click();
        await page.locator('#workspace-handoff').click();
        await page.getByText('Menunggu tinjauan radiolog — hanya baca').waitFor();
        assert.equal(await page.locator('#workspace-save').count(), 0);
        await page.waitForFunction(() => document.querySelector('[data-dicom-viewer]')?.dataset.viewerState === 'ready');
        await page.screenshot({path:`tests/Browser/Screenshots/radiographer-${path}.png`,fullPage:true});
        await page.locator('form[action$="/logout"] button').click();
        await page.goto('/admin/login');await page.locator('[id="form.email"]').fill(f.admin);await page.locator('[id="form.password"]').fill('password');await page.locator('button[type=submit]').click();await page.waitForURL('**/admin');
        await page.goto(`/admin/radiographer-workspaces/${f.reference}`);
        await page.locator('#reopen-reason').fill('Synthetic reopen attempt');
        await page.getByRole('button',{name:'Buka kembali dengan pemeriksaan status klinis'}).click();
        assert.match(await page.textContent('body'), /tidak tersedia/);
        fixture('status','not_finalized');
        await page.goto(`/admin/radiographer-workspaces/${f.reference}`);await page.locator('#reopen-reason').fill('Verified synthetic negative');await page.getByRole('button',{name:'Buka kembali dengan pemeriksaan status klinis'}).click();
        assert.match(await page.textContent('body'), /unreviewed/);
        await page.locator('#manage-logout').click();await page.goto(entry);await page.locator('#identifier').fill(f.worker);await page.locator('#password').fill('password');await page.locator('button[type=submit]').click();try { await page.waitForURL('**/radiographer/workspaces/*'); } catch (error) { console.log('Workspace entry failed', new URL(page.url()).pathname, await page.locator('[role=alert]').allTextContents()); throw error; }
        await page.waitForFunction(()=>document.querySelector('[data-dicom-viewer]')?.dataset.viewerState==='ready');
        await page.locator('#overlay-reason').fill('Synthetic second cycle');for(const input of await page.locator('#workspace-save input[type=checkbox]').all())await input.check();await page.locator('#workspace-save-button').click();await page.locator('#workspace-handoff').click();await page.getByText('Menunggu tinjauan radiolog — hanya baca').waitFor();
        const result=JSON.parse(fixture('verify'));assert.equal(result.handoffs,2);assert.equal(result.cycles,2);assert.ok(result.audit>0);
        if (path === 'converted') {
            fixture('expire');
        } else {
            const adminContext = await browser.newContext({baseURL:base});
            const adminPage = await adminContext.newPage();
            await adminPage.goto('/admin/login');await adminPage.locator('[id="form.email"]').fill(f.admin);await adminPage.locator('[id="form.password"]').fill('password');await adminPage.locator('button[type=submit]').click();await adminPage.waitForURL('**/admin');
            await adminPage.goto(`/admin/radiographer-workspaces/${f.reference}`);await adminPage.getByRole('button',{name:'Cabut akses',exact:true}).click();await adminContext.close();
        }
        assert.equal((await page.reload()).status(),403);
        assert.ok(!(await page.content()).includes('SYNTHETIC-MRN'));
        await context.close();journeys++;
    }
    console.log(JSON.stringify({journeys,rendered:journeys,paths:['direct','converted','supplied'],handoffs:journeys*2,unknownReopenDenied:journeys,syntheticReopen:journeys,qcStates:3,revoked:2,expired:1}));
} finally { await browser.close(); }
