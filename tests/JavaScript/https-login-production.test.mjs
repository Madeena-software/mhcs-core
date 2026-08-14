import { chromium } from 'playwright';
import assert from 'node:assert/strict';

async function verifyProductionHttps() {
  console.log('===> Launching Playwright browser...');
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const context = await browser.newContext({
    ignoreHTTPSErrors: false,
  });

  const page = await context.newPage();

  console.log('===> Test 1: Operator login navigation starting at plain HTTP...');
  const httpResponse = await page.goto('http://fams.mhcsgo.cloud/operator/login', {
    waitUntil: 'networkidle',
    timeout: 30000,
  });

  const finalUrl = page.url();
  console.log(`  Final URL: ${finalUrl}`);
  assert(finalUrl.startsWith('https://fams.mhcsgo.cloud/operator/login'), `Expected HTTPS URL but got: ${finalUrl}`);

  // Inspect rendered form action
  const formAction = await page.getAttribute('form', 'action');
  console.log(`  Rendered form action: ${formAction}`);
  assert.equal(formAction, 'https://fams.mhcsgo.cloud/operator/login', `Form action must be https://fams.mhcsgo.cloud/operator/login but got: ${formAction}`);

  // Verify cookies
  const cookies = await context.cookies('https://fams.mhcsgo.cloud');
  console.log(`  Cookies captured: ${cookies.length}`);
  const sessionCookie = cookies.find(c => c.name.includes('session'));
  assert(sessionCookie, 'Session cookie must exist');
  console.log(`  Session cookie: name=${sessionCookie.name}, secure=${sessionCookie.secure}, httpOnly=${sessionCookie.httpOnly}, sameSite=${sessionCookie.sameSite}`);
  assert.equal(sessionCookie.secure, true, 'Session cookie must be Secure');
  assert.equal(sessionCookie.httpOnly, true, 'Session cookie must be HttpOnly');

  // Submit invalid login over HTTPS to verify secure form submission & redirect
  console.log('===> Test 2: Submitting login over HTTPS...');
  await page.fill('#identifier', 'invalid-operator@example.test');
  await page.fill('#password', 'wrongpassword123');

  let submittedRequestUrl = null;
  page.on('request', request => {
    if (request.method() === 'POST') {
      submittedRequestUrl = request.url();
    }
  });

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle', timeout: 30000 }),
    page.click('button[type="submit"]')
  ]);

  console.log(`  Submitted POST URL: ${submittedRequestUrl}`);
  assert.equal(submittedRequestUrl, 'https://fams.mhcsgo.cloud/operator/login', 'POST request must be sent directly over HTTPS');

  const postSubmitUrl = page.url();
  console.log(`  URL after submission: ${postSubmitUrl}`);
  assert(postSubmitUrl.startsWith('https://fams.mhcsgo.cloud/operator/login'), `Post-submission URL must remain HTTPS: ${postSubmitUrl}`);

  console.log('===> Test 3: Member login navigation starting at plain HTTP...');
  await page.goto('http://fams.mhcsgo.cloud/login', {
    waitUntil: 'networkidle',
    timeout: 30000,
  });

  const memberUrl = page.url();
  console.log(`  Final Member URL: ${memberUrl}`);
  assert(memberUrl.startsWith('https://fams.mhcsgo.cloud/login'), `Expected HTTPS Member URL but got: ${memberUrl}`);

  const memberFormAction = await page.getAttribute('form', 'action');
  console.log(`  Member rendered form action: ${memberFormAction}`);
  assert.equal(memberFormAction, 'https://fams.mhcsgo.cloud/login', `Member form action must be https://fams.mhcsgo.cloud/login but got: ${memberFormAction}`);

  await browser.close();
  console.log('\n✅ ALL PLAYWRIGHT HTTPS PRODUCTION VERIFICATIONS PASSED SUCCESSFULLY!');
}

verifyProductionHttps().catch(err => {
  console.error('\n❌ PLAYWRIGHT VERIFICATION FAILED:', err);
  process.exit(1);
});
