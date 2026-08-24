import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';

const baseURL = process.env.PHASE6A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE6A_SCREENSHOT_DIR || 'storage/app/phase6a-smoke';
const contextPath = process.env.PHASE6A_CONTEXT || 'storage/app/phase6a-smoke/context.json';
const contextData = JSON.parse(await readFile(contextPath, 'utf8'));

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

async function selectByText(select, matcher) {
    const value = await select.locator('option').evaluateAll((options, pattern) => {
        const re = new RegExp(pattern, 'i');
        return options.find((option) => option.value && re.test(option.textContent || ''))?.value || '';
    }, matcher);
    assert(value, `Expected selectable option matching ${matcher}`);
    await select.selectOption(value);
}

async function clickWithResponse(buttonName, matcher) {
    const [response] = await Promise.all([
        page.waitForResponse(matcher),
        page.getByRole('button', { name: buttonName, exact: true }).first().click(),
    ]);
    assert(response.status() < 400, `Unexpected ${response.status()} response for ${buttonName}`);
    await page.waitForLoadState('networkidle');
}

await login();
await page.goto('/admin/admissions');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Admissions|Bed Census/i);

const requestForm = page.locator('form').filter({ hasText: 'Admission Request' });
await selectByText(requestForm.locator('select').nth(0), contextData.hospital_number);
await selectByText(requestForm.locator('select').nth(1), 'Phase 6A Facility');
await selectByText(requestForm.locator('select').nth(2), `Visit #${contextData.visit_id}`);
await selectByText(requestForm.locator('select').nth(3), `Encounter #${contextData.encounter_id}`);
await selectByText(requestForm.locator('select').nth(4), 'Phase 6A Medicine');
await requestForm.locator('textarea').nth(0).fill('Smoke admission request');
await requestForm.locator('textarea').nth(1).fill('Configured provisional diagnosis');
await requestForm.getByRole('checkbox').check();
await clickWithResponse('Request admission', (response) => response.url().endsWith('/admin/admissions/requests') && response.request().method() === 'POST');
await page.screenshot({ path: `${screenshotDir}/request.png`, fullPage: true });

await clickWithResponse('Approve', (response) => response.url().includes('/admin/admissions/') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Allocate bed', (response) => response.url().includes('/admin/admissions/') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /occupied|ADM-/i);

page.once('dialog', (dialog) => dialog.accept('Phase 6A smoke transfer'));
await clickWithResponse('Transfer', (response) => response.url().includes('/admin/admissions/') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /transferred|cleaning/i);

await clickWithResponse('Discharge', (response) => response.url().includes('/admin/admissions/') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
const bodyAfterDischarge = await page.locator('body').innerText();
assert.match(bodyAfterDischarge, /discharged/i);
assert.match(bodyAfterDischarge, /Invoice/i);

await clickWithResponse('Release', (response) => response.url().includes('/admin/admissions/beds/') && response.request().method() === 'PATCH');
await page.screenshot({ path: `${screenshotDir}/discharged-release.png`, fullPage: true });

await browser.close();
console.log('Phase 6A admissions smoke passed');
