import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import assert from 'node:assert/strict';

const fixture=(...args)=>execFileSync('php',['tests/Support/radiologist-browser-fixture.php',...args],{encoding:'utf8',env:{...process.env,APP_ENV:'testing'}}).trim();
const base='http://127.0.0.1:18100';
const browser=await chromium.launch({headless:true,args:['--use-gl=angle','--use-angle=swiftshader','--enable-webgl','--ignore-gpu-blocklist']});
mkdirSync('tests/Browser/Screenshots',{recursive:true});
let rendered=0;
try {
    for (const path of ['direct','converted']) {
        const f=JSON.parse(fixture('seed',path));
        const admin=await browser.newContext({baseURL:base}); const ap=await admin.newPage();
        await ap.goto('/admin/login'); await ap.locator('[id="form.email"]').fill(f.admin); await ap.locator('[id="form.password"]').fill('password'); await ap.locator('button[type=submit]').click(); await ap.waitForURL('**/admin');
        const management=`/admin/radiologist-workspaces/${f.handoff}`; await ap.goto(management);
        await ap.locator('summary').click();
        await ap.locator('#credential-user').fill(f.doctorId); await ap.locator('#evidence').fill('SYNTHETIC-BROWSER-ONLY'); await ap.locator('#valid-until').fill('2099-01-01T00:00'); await ap.locator('#credential-password').fill('password');
        await ap.getByRole('button',{name:'Rekam verifikasi kelayakan'}).click();
        await ap.locator('#assigned-user').selectOption(f.doctorId); await ap.locator('#issue-password').fill('password'); await ap.getByRole('button',{name:'Terbitkan tautan'}).click();
        const entry=await ap.locator('#assignment-url').inputValue(); assert.match(entry,/signature=/);
        const context=await browser.newContext({baseURL:base,viewport:{width:1440,height:1100}}); const page=await context.newPage();
        await page.goto(entry); await page.locator('#identifier').fill(f.doctor); await page.locator('#password').fill('password'); await page.locator('button[type=submit]').click(); await page.waitForURL('**/radiologist/workspaces/*');
        const workspace=page.url(); const frame=page.frameLocator('iframe');
        await frame.locator('[data-dicom-viewer][data-viewer-state=ready]').waitFor({timeout:60000});
        const variation=await frame.locator('[data-testid=dicom-viewport] canvas').first().evaluate(canvas=>{
            const out=document.createElement('canvas');out.width=canvas.width;out.height=canvas.height;const ctx=out.getContext('2d');ctx.drawImage(canvas,0,0);const data=ctx.getImageData(0,0,out.width,out.height).data;let low=255,high=0;for(let i=0;i<data.length;i+=4){low=Math.min(low,data[i]);high=Math.max(high,data[i]);}return high-low;
        }); assert.ok(variation>0); rendered++;
        assert.equal(await page.locator('a[href*="ai-report"],a[href*="download"]').count(),0);
        await page.locator('#findings').focus(); assert.equal(await page.locator('#findings').evaluate(el=>el===document.activeElement),true);
        await page.locator('#findings').fill('Synthetic manually authored findings.'); await page.locator('#conclusion').fill('Synthetic manually authored conclusion.'); await page.getByRole('button',{name:'Simpan revisi draf'}).click();
        if (path==='converted') {
            await page.locator('#decision').selectOption('repeat_required'); await page.locator('#reason').selectOption('other'); await page.locator('#explanation').fill('Synthetic image artifact.'); await page.getByRole('button',{name:'Simpan keputusan klinis'}).click(); await page.getByRole('status').filter({hasText:'repeat_pending'}).waitFor();
            await page.locator('input[name=attest]').check(); await page.locator('#final-password').fill('password'); await page.getByRole('button',{name:/Finalisasi internal revisi/}).click(); assert.match(await page.textContent('body'),/tidak tersedia/); await page.goto(workspace);
            assert.equal(await page.locator('#findings').inputValue(),'Synthetic manually authored findings.');
        }
        await page.locator('#decision').selectOption('usable'); await page.getByRole('button',{name:'Simpan keputusan klinis'}).click();
        await page.locator('#findings').fill('Synthetic revised findings.'); await page.getByRole('button',{name:'Simpan revisi draf'}).click();
        await page.locator('input[name=attest]').check(); await page.locator('#final-password').fill('incorrect'); await page.getByRole('button',{name:/Finalisasi internal revisi/}).click(); assert.match(await page.textContent('body'),/tidak tersedia/); await page.goto(workspace);
        await page.locator('input[name=attest]').check(); await page.locator('#final-password').fill('password'); await page.getByRole('button',{name:/Finalisasi internal revisi/}).click(); await page.getByRole('status').filter({hasText:'Final internal'}).waitFor();
        assert.equal(await page.locator('#findings').count(),0); await page.screenshot({path:`tests/Browser/Screenshots/radiologist-${path}-final.png`,fullPage:true});
        const evidence=JSON.parse(fixture('verify')); assert.equal(evidence.finalizations,1); assert.equal(evidence.reports,2); assert.equal(evidence.ai_jobs,0);
        fixture('audit-fail'); assert.equal((await page.reload()).status(),503); assert.ok(!(await page.content()).includes('SYNTHETIC-MRN')); fixture('audit-restore');
        if(path==='direct') { await ap.goto(management); await ap.locator('#revoke-password').fill('password'); await ap.getByRole('button',{name:'Cabut akses',exact:true}).click(); } else { fixture('expire'); }
        assert.equal((await page.goto(workspace)).status(),403); assert.ok(!(await page.content()).includes('SYNTHETIC-MRN'));
        await context.close(); await admin.close();
    }
    console.log(JSON.stringify({rendered,paths:['direct','converted'],finalizations:2,repeatRequired:1,revoked:1,expired:1,auditFailure:2}));
} finally { await browser.close(); }
