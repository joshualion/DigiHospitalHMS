import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';

const baseURL = process.env.PHASE6B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE6B_SCREENSHOT_DIR || 'storage/app/phase6b-smoke';
const contextData = JSON.parse(await readFile(process.env.PHASE6B_CONTEXT || 'storage/app/phase6b-smoke/context.json', 'utf8'));

await mkdir(screenshotDir, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();

async function login() {
    await page.goto('/login');
    await page.locator('#email').fill(contextData.email);
    await page.locator('#password').fill(contextData.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));
}

async function clickWithResponse(name, matcher) {
    const [response] = await Promise.all([
        page.waitForResponse(matcher),
        page.getByRole('button', { name, exact: true }).first().click(),
    ]);
    assert(response.status() < 400, `Unexpected ${response.status()} response for ${name}`);
    await page.waitForLoadState('networkidle');
}

await login();
await page.goto('/admin/inpatient');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), new RegExp(contextData.admission_number));
await clickWithResponse('Create chart', (response) => response.url().includes('/admin/inpatient/admissions/') && response.request().method() === 'POST');
await page.waitForURL((url) => url.pathname.includes('/admin/inpatient/charts/'));

await page.locator('textarea[placeholder="Subjective"]').fill('Patient reviewed on ward');
await page.locator('textarea[placeholder="Objective"]').fill('Observation reviewed');
await page.locator('textarea[placeholder="Assessment"]').fill('Smoke inpatient assessment');
await page.locator('textarea[placeholder="Plan"]').fill('Continue ward care plan');
await clickWithResponse('Save note', (response) => response.url().includes('/progress-notes') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Sign', (response) => response.url().includes('/progress-notes/') && response.request().method() === 'POST');

await page.locator('#obs_temp').fill('37.1');
await page.locator('#obs_pulse').fill('84');
await page.locator('#obs_resp').fill('18');
await page.locator('#obs_bp_s').fill('118');
await page.locator('#obs_bp_d').fill('76');
await page.locator('#obs_spo2').fill('98');
await page.locator('#obs_pain').fill('1');
await page.locator('#obs_glucose').fill('5.4');
await page.locator('textarea[placeholder="Consciousness notes"]').fill('Awake and oriented');
await clickWithResponse('Record observation', (response) => response.url().includes('/observations') && response.request().method() === 'POST');

await page.locator('textarea[placeholder="Problem"]').fill('Needs assisted mobilisation');
await page.locator('textarea[placeholder="Goal"]').fill('Safe mobilisation');
await page.locator('textarea[placeholder="Intervention"]').fill('Assist when ambulating');
await page.locator('textarea[placeholder="Evaluation"]').fill('Ongoing');
await clickWithResponse('Record care plan', (response) => response.url().includes('/care-plans') && response.request().method() === 'POST');

await page.locator('textarea[placeholder="Order instruction"]').fill('Monitor and document ward observations');
await clickWithResponse('Record order', (response) => response.url().includes('/orders') && response.request().method() === 'POST');

await page.locator('textarea[placeholder="Handover summary"]').fill('Stable patient handed over for night review');
await clickWithResponse('Sign handover', (response) => response.url().includes('/handovers') && response.request().method() === 'POST');

await page.locator('textarea[placeholder="Admission summary"]').fill('Admitted for configured inpatient care');
await page.locator('textarea[placeholder="Clinical course"]').fill('Clinician reviewed and documented progress');
await page.locator('textarea[placeholder="Discharge plan"]').fill('Reviewed discharge summary');
await clickWithResponse('Draft summary', (response) => response.url().includes('/discharge-summary') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Sign summary', (response) => response.url().includes('/discharge-summaries/') && response.request().method() === 'POST');
await page.screenshot({ path: `${screenshotDir}/signed-summary.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /closed|signed/i);

await browser.close();
console.log('Phase 6B inpatient chart smoke passed');
