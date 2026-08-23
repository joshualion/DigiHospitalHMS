import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE2C_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE2C_SCREENSHOT_DIR || 'storage/app/phase2c-smoke';
const admin = {
    email: process.env.PHASE2C_ADMIN_EMAIL || 'phase2c-smoke@example.test',
    password: process.env.PHASE2C_ADMIN_PASSWORD || 'Phase2CSmoke!',
};

await mkdir(screenshotDir, { recursive: true });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();

await page.goto('/login');
await page.locator('#email').fill(admin.email);
await page.locator('#password').fill(admin.password);
await page.getByRole('button', { name: 'Login' }).click();
await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));

await page.goto('/admin/clinical/worklist');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/worklist.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /Checked-in Patients/);

await page.getByRole('button', { name: 'Start' }).first().click();
await page.waitForURL('**/admin/encounters/*');
await page.waitForLoadState('networkidle');

await page.locator('#temp').fill('37.1');
await page.locator('#pulse').fill('82');
await page.locator('#resp').fill('18');
await page.locator('#bp_sys').fill('121');
await page.locator('#bp_dia').fill('79');
await page.locator('#spo2').fill('98');
await page.locator('#weight').fill('70');
await page.locator('#height').fill('170');
await page.locator('#pain').fill('2');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/vitals') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Record vitals' }).click(),
]);
await page.waitForLoadState('networkidle');

await page.getByLabel('Presenting complaint').fill('Smoke presenting complaint');
await page.getByRole('textbox', { name: 'History', exact: true }).fill('Smoke history');
await page.getByLabel('Examination findings').fill('Smoke examination');
await page.getByLabel('Treatment / management plan').fill('Smoke management plan');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/assessment') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Save assessment' }).click(),
]);
await page.waitForLoadState('networkidle');

await page.getByPlaceholder('Diagnosis description').fill('Smoke provisional diagnosis');
await page.getByPlaceholder('Coding system').fill('LOCAL');
await page.getByPlaceholder('Code').fill('SMK');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/diagnoses') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Add diagnosis' }).click(),
]);
await page.waitForLoadState('networkidle');

await Promise.all([
    page.waitForResponse((response) => response.url().includes('/transition') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Sign' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Clinical Assessment\s+signed/i);

await page.getByPlaceholder('Reason').fill('Smoke correction');
await page.getByPlaceholder('Amendment').fill('Smoke amendment after signing');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/amendments') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Add amendment' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.reload();
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/signed-amendment.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /Smoke amendment after signing/);

await browser.close();
console.log('Phase 2C outpatient encounter smoke passed');
