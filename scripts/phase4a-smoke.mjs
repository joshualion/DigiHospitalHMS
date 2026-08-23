import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE4A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE4A_SCREENSHOT_DIR || 'storage/app/phase4a-smoke';
const password = process.env.PHASE4A_PASSWORD || 'Phase4ASmoke!';

await mkdir(screenshotDir, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();

async function login(email) {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));
}

async function logout() {
    await page.getByRole('button', { name: 'Logout' }).last().click();
    await page.waitForURL((url) => url.pathname === '/' || url.pathname === '/login');
}

async function selectFirst(select, matcher = null) {
    const value = await select.locator('option').evaluateAll((options, pattern) => {
        const re = pattern ? new RegExp(pattern, 'i') : null;
        return options.find((option) => option.value && (!re || re.test(option.textContent || '')))?.value || '';
    }, matcher);
    assert(value, 'Expected a selectable option');
    await select.selectOption(value);
    return value;
}

await login(process.env.PHASE4A_DOCTOR_EMAIL || 'phase4a-doctor@example.test');
await page.goto('/admin/laboratory/requests');
await page.waitForLoadState('networkidle');
const orderForm = page.locator('form').filter({ hasText: 'Order Lab Request' });
await selectFirst(orderForm.locator('select').nth(0), 'Phase4A');
await selectFirst(orderForm.locator('select').nth(1), 'Phase 4A');
await selectFirst(orderForm.locator('select').nth(3));
await selectFirst(orderForm.locator('select').nth(4));
await selectFirst(orderForm.locator('select').nth(5), 'Phase 4A lab test');
await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/admin/laboratory/requests') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Order request' }).click(),
]);
await page.waitForURL('**/admin/laboratory/requests/*');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Billing invoice/i);
const requestPath = new URL(page.url()).pathname;
await page.screenshot({ path: `${screenshotDir}/ordered.png`, fullPage: true });
await logout();

await login(process.env.PHASE4A_LAB_EMAIL || 'phase4a-lab@example.test');
await page.goto(requestPath);
await page.waitForLoadState('networkidle');
await selectFirst(page.locator('form').filter({ hasText: 'Specimen' }).locator('select').first(), 'blood');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/specimens') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Collect specimen' }).click(),
]);
await page.waitForLoadState('networkidle');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/transition') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Receive' }).first().click(),
]);
await page.waitForLoadState('networkidle');
await page.locator('input[id^="num_"]').first().fill('40');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/results') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Save draft' }).first().click(),
]);
await page.waitForLoadState('networkidle');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/transition') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Verify' }).first().click(),
]);
await page.screenshot({ path: `${screenshotDir}/verified.png`, fullPage: true });
await logout();

await login(process.env.PHASE4A_APPROVER_EMAIL || 'phase4a-approver@example.test');
await page.goto(requestPath);
await page.waitForLoadState('networkidle');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/transition') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Approve' }).first().click(),
]);
await page.reload();
await page.waitForLoadState('networkidle');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/release') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Release' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.locator('input[placeholder="Reason"]').fill('Smoke amendment');
await page.locator('textarea[placeholder="Amendment"]').fill('Append-only smoke amendment.');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/amendments') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Add amendment' }).click(),
]);
await page.getByRole('link', { name: 'Report' }).click();
await page.waitForURL('**/report');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Approved Laboratory Report/i);
await page.screenshot({ path: `${screenshotDir}/report.png`, fullPage: true });

await browser.close();
console.log('Phase 4A laboratory smoke passed');
