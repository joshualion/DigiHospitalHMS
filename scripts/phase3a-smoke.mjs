import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE3A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE3A_SCREENSHOT_DIR || 'storage/app/phase3a-smoke';
const admin = {
    email: process.env.PHASE3A_ADMIN_EMAIL || 'phase3a-smoke@example.test',
    password: process.env.PHASE3A_ADMIN_PASSWORD || 'Phase3ASmoke!',
};
const unique = Date.now();

await mkdir(screenshotDir, { recursive: true });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();

async function selectFirst(select) {
    const value = await select.locator('option').evaluateAll((options) => options.find((option) => option.value)?.value || '');
    assert(value, 'Expected a selectable option');
    await select.selectOption(value);
    return value;
}

await page.goto('/login');
await page.locator('#email').fill(admin.email);
await page.locator('#password').fill(admin.password);
await page.getByRole('button', { name: 'Login' }).click();
await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));

await page.goto('/admin/billing/catalogue');
await page.waitForLoadState('networkidle');
await page.locator('#category_name').fill(`Smoke Category ${unique}`);
await page.locator('#category_code').fill(`SC${String(unique).slice(-6)}`);
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/admin/billing/categories') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Create category' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.reload();

await selectFirst(page.locator('select').nth(0));
await page.locator('#service_code').fill(`SVC${String(unique).slice(-6)}`);
await page.locator('#service_name').fill(`Smoke Service ${unique}`);
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/admin/billing/services') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Create service' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.reload();

await selectFirst(page.locator('form').filter({ hasText: 'Price' }).locator('select').first());
await page.locator('#price_amount').fill('15000');
await page.locator('#price_from').fill('2026-01-01');
await page.getByPlaceholder('Reason').fill('Smoke price');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/prices') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Add price' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/catalogue.png`, fullPage: true });

await page.goto('/admin/billing/invoices');
await selectFirst(page.locator('form').locator('select').nth(0));
await selectFirst(page.locator('form').locator('select').nth(1));
await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/admin/billing/invoices') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Create draft' }).click(),
]);
await page.waitForURL('**/admin/billing/invoices/*');
await page.waitForLoadState('networkidle');

await selectFirst(page.locator('form').filter({ hasText: 'Service Line' }).locator('select'));
await page.locator('#line_qty').fill('1');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/service-lines') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Add service' }).click(),
]);
await page.waitForLoadState('networkidle');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/issue') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Issue' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.reload();
assert.match(await page.locator('body').innerText(), /issued/i);

page.once('dialog', async (dialog) => dialog.accept('Smoke void'));
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/transition') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Void' }).click(),
]);
await page.waitForLoadState('networkidle');
await page.reload();
assert.match(await page.locator('body').innerText(), /voided/i);

await Promise.all([
    page.waitForResponse((response) => response.url().includes('/replacement') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Replacement draft' }).click(),
]);
await page.waitForURL('**/admin/billing/invoices/*');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/replacement-draft.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /draft/i);

await browser.close();
console.log('Phase 3A service catalogue and invoicing smoke passed');
