import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';

const baseURL = process.env.PHASE6C_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE6C_SCREENSHOT_DIR || 'storage/app/phase6c-smoke';
const contextData = JSON.parse(await readFile(process.env.PHASE6C_CONTEXT || 'storage/app/phase6c-smoke/context.json', 'utf8'));

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

async function clickWithResponse(locator, matcher) {
    const [response] = await Promise.all([page.waitForResponse(matcher), locator.click()]);
    assert(response.status() < 400, `Unexpected ${response.status()} response`);
    await page.waitForLoadState('networkidle');
}

await login();
await page.goto(`/admin/emar/charts/${contextData.chart_id}`);
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Medication Schedule/);

await clickWithResponse(page.locator('article').filter({ hasText: 'regular' }).getByRole('button', { name: 'Record' }).first(), (response) => response.url().includes('/admin/emar/schedules/') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');

await page.locator('select').first().selectOption('omitted');
await page.locator('textarea[placeholder="Reason for non-administered or delayed outcome"]').fill('Smoke omitted reason');
await clickWithResponse(page.locator('article').filter({ hasText: 'stat' }).getByRole('button', { name: 'Record' }).first(), (response) => response.url().includes('/admin/emar/schedules/') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');

await page.locator('select').first().selectOption('administered');
await page.locator('textarea[placeholder="PRN indication"]').fill('Smoke PRN indication');
await page.locator('textarea[placeholder="PRN response/effect"]').fill('Smoke PRN response pending');
await clickWithResponse(page.locator('article').filter({ hasText: 'prn' }).getByRole('button', { name: 'Record' }).first(), (response) => response.url().includes('/admin/emar/schedules/') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');

await page.locator('input[placeholder="Correction reason"]').first().fill('Smoke correction');
await page.locator('input[placeholder="Correction note"]').first().fill('Append-only eMAR correction');
await clickWithResponse(page.getByRole('button', { name: 'Add correction' }).first(), (response) => response.url().includes('/admin/emar/administrations/') && response.request().method() === 'POST');
await page.screenshot({ path: `${screenshotDir}/emar-history.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /Correction|administered|omitted/i);

await browser.close();
console.log('Phase 6C eMAR smoke passed');
