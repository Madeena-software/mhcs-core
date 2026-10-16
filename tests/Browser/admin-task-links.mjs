import {chromium} from 'playwright';
import {execFileSync} from 'node:child_process';
import {mkdirSync} from 'node:fs';
import assert from 'node:assert/strict';

const container=process.env.MHCS_RAD_CONTAINER;
assert.match(container??'',/^mhcs-task-links-e6$/);
const fixture=(...args)=>execFileSync('docker',['exec','-e','APP_ENV=testing',container,'php','tests/Support/member-result-browser-fixture.php',...args],{encoding:'utf8'}).trim();
const base='http://127.0.0.1:18100';
const artifact=process.env.MHCS_LINK_ARTIFACTS;
assert.ok(artifact?.startsWith('/home/faliq/.codex/task-state/mhcs-core/'));
mkdirSync(artifact,{recursive:true});
const browser=await chromium.launch({headless:true,args:['--use-gl=angle','--use-angle=swiftshader','--enable-webgl','--ignore-gpu-blocklist']});
const newContext=async(options)=>{
 const context=await browser.newContext(options);
 await context.route('**/*',route=>new URL(route.request().url()).origin===base?route.continue():route.abort());
 return context;
};
const login=async(page,email,admin=false)=>{
    if(admin) {await page.goto('/admin/login');await page.locator('[id="form.email"]').fill(email);await page.locator('[id="form.password"]').fill('password');}
    else {await page.locator('#identifier').fill(email);await page.locator('#password').fill('password');}
    await page.locator('button[type=submit]').click();
};
const ready=async(page,frame=false)=>{
    const root=frame?page.frameLocator('iframe'):page;
    try { await root.locator('[data-dicom-viewer][data-viewer-state=ready]').waitFor({timeout:60000}); }
    catch (error) { console.log(JSON.stringify({viewerState:await root.locator('[data-dicom-viewer]').getAttribute('data-viewer-state'),visibleError:await root.locator('#dicom-viewer-error').textContent()}));throw error; }
    const variation=await root.locator('[data-testid=dicom-viewport] canvas').first().evaluate(canvas=>{
        const out=document.createElement('canvas');out.width=canvas.width;out.height=canvas.height;const ctx=out.getContext('2d');ctx.drawImage(canvas,0,0);const data=ctx.getImageData(0,0,out.width,out.height).data;let low=255,high=0;for(let i=0;i<data.length;i+=4){low=Math.min(low,data[i]);high=Math.max(high,data[i]);}return high-low;
    });assert.ok(variation>0);
};
let journeys=0, copies=0, deniedCopies=0, unavailableCopies=0;
const copy=async(page,context)=>{
    await context.grantPermissions(['clipboard-read','clipboard-write'],{origin:base});
    const button=page.getByRole('button',{name:'Salin tautan',exact:true});
    await button.focus();await page.keyboard.press('Enter');
    await page.locator('#copy-status').filter({hasText:'berhasil disalin'}).waitFor();
    const value=await page.evaluate(()=>navigator.clipboard.readText());
    assert.equal(value,await page.locator('#assignment-url').inputValue());copies++;
    const cdp=await context.newCDPSession(page);
    const {targetInfo}=await cdp.send('Target.getTargetInfo');
    const browserContextId=targetInfo.browserContextId;
    await cdp.send('Browser.setPermission',{permission:{name:'clipboard-write',allowWithoutSanitization:true},setting:'denied',origin:base,browserContextId});
    await cdp.send('Browser.setPermission',{permission:{name:'clipboard-write',allowWithoutSanitization:false},setting:'denied',origin:base,browserContextId});
    assert.equal(await page.evaluate(async()=>(await navigator.permissions.query({name:'clipboard-write'})).state),'denied');
    await button.click();await page.locator('#copy-status').filter({hasText:'belum disalin'}).waitFor();
    assert.equal(await page.locator('#assignment-url').evaluate(e=>e===document.activeElement&&e.selectionStart===0&&e.selectionEnd===e.value.length),true);deniedCopies++;
    await cdp.send('Browser.setPermission',{permission:{name:'clipboard-write',allowWithoutSanitization:true},setting:'granted',origin:base,browserContextId});
    await page.evaluate(()=>Object.defineProperty(navigator,'clipboard',{value:undefined,configurable:true}));
    await button.click();await page.locator('#copy-status').filter({hasText:'belum disalin'}).waitFor();
    assert.equal(await page.locator('#assignment-url').evaluate(e=>e===document.activeElement),true);unavailableCopies++;
    await page.evaluate(()=>delete navigator.clipboard);
    return value;
};
const worklist=async(page,f)=>{
 await page.goto(`/admin/study-worklist?${new URLSearchParams({facility:f.facility,date:f.date,session:f.session})}`);
};
try {
 for(const path of ['direct','converted']) {
    const f=JSON.parse(fixture('seed',path));console.log(path+': seeded');
    const admin=await newContext({baseURL:base});const ap=await admin.newPage();ap.setDefaultTimeout(20000);ap.setDefaultNavigationTimeout(20000);
    await login(ap,f.admin,true);await ap.waitForURL('**/admin');console.log(path+': admin authenticated');
    await worklist(ap,f);await ap.getByRole('link',{name:'Penugasan Radiografer / QC',exact:true}).click();assert.ok((await ap.textContent('body')).includes('Belum ditugaskan'));await ap.locator('#assigned-user').selectOption(f.workerId);await ap.getByRole('button',{name:'Terbitkan tautan',exact:true}).click();
    let workerEntry=await copy(ap,admin);
    await ap.reload();assert.equal(await ap.locator('#assignment-url').count(),0);
    await ap.locator('#assigned-user').selectOption(f.workerId);await ap.getByRole('button',{name:'Tugaskan ulang',exact:true}).click();const oldWorkerEntry=workerEntry;workerEntry=await copy(ap,admin);assert.notEqual(workerEntry,oldWorkerEntry);
    assert.equal(JSON.parse(fixture('verify')).publications,0);
    const worker=await newContext({baseURL:base});const wp=await worker.newPage();wp.setDefaultTimeout(20000);await wp.goto(workerEntry);await wp.locator('#identifier').fill(f.worker);await wp.locator('#password').fill('incorrect');await wp.locator('button[type=submit]').click();assert.ok(new URL(wp.url()).pathname.includes('login'));await login(wp,f.worker);await wp.waitForURL('**/radiographer/workspaces/*');assert.equal((await wp.goto(oldWorkerEntry)).status(),403);await wp.goto(workerEntry);await ready(wp);console.log(path+': radiographer rendered');
    await wp.locator('#overlay-laterality').selectOption('not_applicable');await wp.locator('#overlay-reason').fill('Synthetic browser QC');for(const box of await wp.locator('#workspace-save input[type=checkbox]').all())await box.check();await wp.locator('#workspace-save-button').click();await wp.locator('#workspace-handoff').click();await wp.getByText('Menunggu tinjauan radiolog — hanya baca').waitFor();
    console.log(path+': handoff complete');const {handoff}=JSON.parse(fixture('handoff'));
    await worklist(ap,f);await ap.getByRole('link',{name:'Penugasan Radiolog / laporan klinis',exact:true}).click();await ap.getByText('Verifikasi kelayakan profesional (kapabilitas khusus)',{exact:true}).click();await ap.locator('#credential-user').fill(f.doctorId);await ap.locator('#evidence').fill('SYNTHETIC-BROWSER-ONLY');await ap.locator('#valid-until').fill('2099-01-01T00:00');await ap.locator('#credential-password').fill('password');await ap.getByRole('button',{name:'Rekam verifikasi kelayakan'}).click();
    await ap.locator('#assigned-user').selectOption(f.doctorId);await ap.locator('#issue-password').fill('password');await ap.getByRole('button',{name:'Terbitkan tautan',exact:true}).click();let doctorEntry=await copy(ap,admin);
    await ap.getByRole('link',{name:'Kembali ke daftar studi',exact:true}).click();await worklist(ap,f);await ap.getByRole('link',{name:'Penugasan Radiolog / laporan klinis',exact:true}).click();assert.equal(await ap.locator('#assignment-url').count(),0);
    await ap.locator('#assigned-user').selectOption(f.doctorId);await ap.locator('#issue-password').fill('password');await ap.getByRole('button',{name:'Terbitkan tautan',exact:true}).click();const oldDoctorEntry=doctorEntry;doctorEntry=await copy(ap,admin);assert.notEqual(doctorEntry,oldDoctorEntry);
    const doctor=await newContext({baseURL:base});const dp=await doctor.newPage();dp.setDefaultTimeout(20000);await dp.goto(doctorEntry);await login(dp,f.doctor);await dp.waitForURL('**/radiologist/workspaces/*');assert.equal((await dp.goto(oldDoctorEntry)).status(),403);await dp.goto(doctorEntry);await ready(dp,true);console.log(path+': radiologist rendered');
    await dp.locator('#decision').selectOption('usable');await dp.getByRole('button',{name:'Simpan keputusan klinis'}).click();await dp.locator('#findings').fill('Synthetic browser findings.');await dp.locator('#conclusion').fill('Synthetic browser conclusion.');await dp.locator('#recommendations').fill('Synthetic browser recommendations.');await dp.getByRole('button',{name:'Simpan revisi draf'}).click();const workspace=dp.url();await dp.locator('input[name=attest]').check();await dp.locator('#final-password').fill('incorrect');await dp.getByRole('button',{name:/Finalisasi internal revisi/}).click();assert.match(await dp.textContent('body'),/tidak tersedia/);assert.equal(JSON.parse(fixture('verify')).publications,0);await dp.goto(workspace);
    await dp.locator('input[name=attest]').check();await dp.locator('#final-password').fill('password');await dp.getByRole('button',{name:/Finalisasi internal revisi/}).click();await dp.getByRole('status').filter({hasText:'Final internal'}).waitFor();
    assert.equal(JSON.parse(fixture('seal')).publications,0);console.log(path+': finalized and sealed');
    await ap.goto(`/admin/radiologist-workspaces/${handoff}`);await ap.getByRole('link',{name:/Publikasi hasil Member/}).click();
    await ap.locator('#confirm-recipient').focus();assert.equal(await ap.locator('#confirm-recipient').evaluate(e=>e===document.activeElement),true);await ap.locator('#confirm-recipient').check();await ap.getByRole('button',{name:'Publikasikan versi final',exact:true}).click();const entry=await ap.locator('#result-url').inputValue();assert.match(entry,/signature=/);
    const member=await newContext({baseURL:base});const mp=await member.newPage();mp.setDefaultTimeout(20000);await mp.goto(entry);await login(mp,f.member);await mp.getByRole('heading',{name:'Hasil pemeriksaan final',exact:true}).waitFor();
    const body=await mp.textContent('body');for(const text of ['Synthetic browser findings.','Synthetic browser conclusion.','Synthetic browser recommendations.',f.doctor])assert.ok(body.includes(text));for(const text of ['SYNTHETIC-MRN','SYNTHETIC^ONLY','draft_id','AI report','point_cost'])assert.ok(!body.includes(text));assert.equal(await mp.locator('iframe,a[href*="download"]').count(),0);
    // Screenshots capture the final-only viewport; browser chrome/locators are excluded.
    await mp.screenshot({path:`${artifact}/member-${path}-final.png`,fullPage:true});
    fixture('identity-deny');assert.equal((await mp.reload()).status(),403);fixture('identity-restore');assert.equal((await mp.reload()).status(),200);
    fixture('audit-fail');assert.equal((await mp.reload()).status(),503);assert.ok(!(await mp.content()).includes('Synthetic browser findings'));fixture('audit-restore');
    const wrong=await newContext({baseURL:base});const wrongPage=await wrong.newPage();await wrongPage.goto(entry);await login(wrongPage,f.worker);await wrongPage.getByRole('heading',{name:'Hasil tidak tersedia',exact:true}).waitFor();assert.ok(!(await wrongPage.content()).includes('Synthetic browser findings'));await wrong.close();
    if(path==='direct') {
        fixture('lineage-deny');assert.equal((await mp.reload()).status(),403);
        await ap.goto(`/admin/member-results/${f.case}`);await ap.getByRole('button',{name:'Tahan akses',exact:true}).click();
        fixture('lineage-restore');assert.equal((await mp.reload()).status(),403);
        await ap.goto(`/admin/member-results/${f.case}`);await ap.locator('#confirm-recipient').check();await ap.getByRole('button',{name:'Publikasikan versi final',exact:true}).click();
        const replacement=await ap.locator('#result-url').inputValue();assert.notEqual(replacement,entry);assert.equal((await mp.reload()).status(),403);
        assert.equal((await mp.goto(replacement)).status(),200);assert.ok((await mp.textContent('body')).includes('Synthetic browser findings.'));
        await ap.goto(`/admin/member-results/${f.case}`);await ap.getByRole('button',{name:'Tangguhkan akses',exact:true}).click();assert.equal((await mp.reload()).status(),403);
        await ap.goto(`/admin/member-results/${f.case}`);await ap.getByRole('button',{name:'Cabut akses',exact:true}).click();assert.equal((await mp.reload()).status(),403);
    } else {fixture('expire');assert.equal((await mp.reload()).status(),403);}
    fixture('lineage-deny');assert.equal((await mp.reload()).status(),403);
    const proof=JSON.parse(fixture('verify'));assert.equal(proof.ai,0);assert.equal(proof.publications,path==='direct'?2:1);assert.equal(proof.finalizations,1);
    for(const [role,entry,recipient] of [['radiographer',workerEntry,wp],['radiologist',doctorEntry,dp]]) {
        fixture('expire-profession',role);assert.equal((await recipient.goto(entry)).status(),403);
        await ap.goto(`/admin/${role}-workspaces/${role==='radiographer'?f.reference:handoff}`);await ap.locator('#assignment-status').filter({hasText:'kedaluwarsa'}).waitFor();assert.equal(await ap.locator('#assignment-url').count(),0);
        if(role==='radiologist')await ap.locator('#revoke-password').fill('password');await ap.getByRole('button',{name:'Cabut akses',exact:true}).click();await ap.locator('#assignment-status').filter({hasText:'dicabut'}).waitFor();assert.equal((await recipient.goto(entry)).status(),403);
    }
    await member.close();await worker.close();await doctor.close();await admin.close();journeys++;
 }
 console.log(JSON.stringify({journeys,copies,deniedCopies,unavailableCopies,paths:['direct','converted'],browserHandoffs:2,rendered:4,finalized:2,separatePublications:3,manualRepublication:1,staleGeneration:1,lineageDenied:1,memberLogin:2,wrongRecipient:2,identityDenied:2,auditFailure:2,suspended:1,revoked:1,expired:1}));
} finally {await browser.close();}
