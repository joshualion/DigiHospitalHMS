import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE3B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE3B_SCREENSHOT_DIR || 'storage/app/phase3b-smoke';
const password = process.env.PHASE3B_PASSWORD || 'Phase3BSmoke!';
const cashierEmail = process.env.PHASE3B_CASHIER_EMAIL || 'phase3b-cashier@example.test';
const accountantEmail = process.env.PHASE3B_ACCOUNTANT_EMAIL || 'phase3b-accountant@example.test';

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

async function selectFirst(select, matcher = null) {
    const value = await select.locator('option').evaluateAll((options, pattern) => {
        const re = pattern ? new RegExp(pattern, 'i') : null;
        const match = options.find((option) => option.value && (!re || re.test(option.textContent || '')));
        return match?.value || '';
    }, matcher);
    assert(value, 'Expected a selectable option');
    await select.selectOption(value);
    return value;
}

function acceptDialogs(values) {
    let index = 0;
    page.on('dialog', async (dialog) => {
        if (index >= values.length) return;
        const value = values[index++];
        await dialog.accept(value);
    });
}

await login(cashierEmail);
await page.goto('/admin/payments/workbench');
await page.waitForLoadState('networkidle');

await selectFirst(page.locator('form').first().locator('select').first());
await page.locator('#opening_float').fill('1000');
await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/admin/cashier-shifts') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Open shift' }).click(),
]);
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Open Shift/i);

await selectFirst(page.locator('form').filter({ hasText: 'Post Payment' }).locator('select').nth(0));
await selectFirst(page.locator('form').filter({ hasText: 'Post Payment' }).locator('select').nth(1), 'Cash');
await selectFirst(page.locator('form').filter({ hasText: 'Post Payment' }).locator('select').nth(2));
await page.locator('#payment_amount').fill('5000');
await page.getByRole('button', { name: 'Allocate invoice' }).click();
await selectFirst(page.locator('select').filter({ hasText: 'P3B-PART' }).first());
await page.locator('#allocation_amount').fill('5000');
await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/admin/payments') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Post payment' }).click(),
]);
await page.waitForURL('**/admin/payments/*/receipt');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Receipt/i);
await page.screenshot({ path: `${screenshotDir}/partial-receipt.png`, fullPage: true });

await page.goto('/admin/payments/workbench');
await page.waitForLoadState('networkidle');
await selectFirst(page.locator('form').filter({ hasText: 'Post Payment' }).locator('select').nth(0));
await selectFirst(page.locator('form').filter({ hasText: 'Post Payment' }).locator('select').nth(1), 'transfer');
await selectFirst(page.locator('form').filter({ hasText: 'Post Payment' }).locator('select').nth(2));
await page.locator('#payment_amount').fill('4000');
await page.locator('#payment_reference').fill('SMOKE-TRANSFER');
await page.getByRole('button', { name: 'Allocate invoice' }).click();
await selectFirst(page.locator('select').filter({ hasText: 'P3B-FULL' }).first());
await page.locator('#allocation_amount').fill('4000');
await Promise.all([
    page.waitForResponse((response) => response.url().endsWith('/admin/payments') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Post payment' }).click(),
]);
await page.waitForURL('**/admin/payments/*/receipt');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Allocated/i);
await page.screenshot({ path: `${screenshotDir}/full-receipt.png`, fullPage: true });

await page.goto('/admin/payments/accounting');
await page.waitForLoadState('networkidle');
acceptDialogs(['1000', 'Smoke refund request']);
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/refunds') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Refund' }).first().click(),
]);

await page.goto('/admin/payments/workbench');
await page.waitForLoadState('networkidle');
await page.locator('#counted_cash').fill('6000');
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/close') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Close shift' }).click(),
]);

await page.getByRole('button', { name: 'Logout' }).last().click();
await page.waitForURL((url) => url.pathname === '/' || url.pathname === '/login');
await login(accountantEmail);
await page.goto('/admin/payments/accounting');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/accounting.png`, fullPage: true });

page.once('dialog', async (dialog) => dialog.accept('Reviewed in smoke test'));
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/review') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Review' }).first().click(),
]);

page.once('dialog', async (dialog) => dialog.accept('Approved in smoke test'));
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/decision') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Approve' }).first().click(),
]);
page.once('dialog', async (dialog) => dialog.accept());
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/process') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Process' }).first().click(),
]);

page.once('dialog', async (dialog) => dialog.accept('Smoke reversal'));
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/reverse') && response.request().method() === 'PATCH'),
    page.getByRole('button', { name: 'Reverse' }).last().click(),
]);

await browser.close();
console.log('Phase 3B payments and reconciliation smoke passed');
