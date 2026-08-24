import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE5A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE5A_SCREENSHOT_DIR || 'storage/app/phase5a-smoke';
const password = process.env.PHASE5A_PASSWORD || 'Phase5ASmoke!';
const storekeeperEmail = process.env.PHASE5A_STOREKEEPER_EMAIL || 'phase5a-storekeeper@example.test';
const pharmacistEmail = process.env.PHASE5A_PHARMACIST_EMAIL || 'phase5a-pharmacist@example.test';
const batchNumber = `P5A-${Date.now()}`;

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

async function clickWithResponse(name, matcher, exact = true) {
    const [response] = await Promise.all([
        page.waitForResponse(matcher),
        page.getByRole('button', { name, exact }).click(),
    ]);
    assert(response.status() < 400, `Unexpected ${response.status()} response for ${name}`);
    await page.waitForLoadState('networkidle');
}

await login(storekeeperEmail);
await page.goto('/admin/inventory/catalogue');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Inventory Catalogue/i);
await page.locator('#location_code').fill(`P5A-WARD-${Date.now()}`);
await page.locator('#location_name').fill('Phase 5A Ward Store Smoke');
await clickWithResponse('Create location', (response) => response.url().endsWith('/admin/inventory/locations') && response.request().method() === 'POST');
await page.screenshot({ path: `${screenshotDir}/catalogue.png`, fullPage: true });

await page.goto('/admin/inventory/stock');
await page.waitForLoadState('networkidle');
const receiveForm = page.locator('form').filter({ hasText: 'Batch Receipt' });
await selectFirst(receiveForm.locator('select').nth(0), 'Main Store');
await selectFirst(receiveForm.locator('select').nth(1), 'Phase 5A configured medicine|P5A-MED');
await selectFirst(receiveForm.locator('select').nth(2), 'EACH');
await receiveForm.locator('#batch_number').fill(batchNumber);
await receiveForm.locator('#quantity').fill('40');
await receiveForm.locator('#expiry_date').fill('2027-12-31');
await receiveForm.locator('#unit_cost_minor').fill('1200');
await clickWithResponse('Receive batch', (response) => response.url().endsWith('/admin/inventory/batches/receive') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), new RegExp(batchNumber));
await page.screenshot({ path: `${screenshotDir}/stock.png`, fullPage: true });

await page.goto('/admin/inventory/transfers');
await page.waitForLoadState('networkidle');
const transferForm = page.locator('form').filter({ hasText: 'Request Transfer' });
await selectFirst(transferForm.locator('select').nth(0), 'Phase 5A configured medicine|P5A-MED');
await selectFirst(transferForm.locator('select').nth(1), batchNumber);
await selectFirst(transferForm.locator('select').nth(2), 'Main Store');
await selectFirst(transferForm.locator('select').nth(3), 'Pharmacy');
await transferForm.locator('#transfer_qty').fill('10');
await transferForm.locator('textarea').fill('Smoke pharmacy transfer');
await clickWithResponse('Request transfer', (response) => response.url().endsWith('/admin/inventory/transfers') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Dispatch', (response) => response.url().includes('/admin/inventory/transfers/') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Receive', (response) => response.url().includes('/admin/inventory/transfers/') && response.request().method() === 'PATCH');
await page.screenshot({ path: `${screenshotDir}/transfer.png`, fullPage: true });

await page.goto('/admin/inventory/adjustments');
await page.waitForLoadState('networkidle');
const adjustmentForm = page.locator('form').filter({ hasText: 'Request Adjustment' });
await selectFirst(adjustmentForm.locator('select').nth(0), 'Pharmacy');
await selectFirst(adjustmentForm.locator('select').nth(1), 'Phase 5A configured medicine|P5A-MED');
await selectFirst(adjustmentForm.locator('select').nth(2), batchNumber);
await adjustmentForm.locator('#quantity_delta').fill('-1');
await adjustmentForm.locator('textarea').fill('Smoke damage adjustment');
await clickWithResponse('Request adjustment', (response) => response.url().endsWith('/admin/inventory/adjustments') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
await logout();

await login(pharmacistEmail);
await page.goto('/admin/inventory/adjustments');
await page.waitForLoadState('networkidle');
await clickWithResponse('Approve', (response) => response.url().includes('/approve') && response.request().method() === 'PATCH');
await page.goto('/admin/inventory/reports');
await page.waitForLoadState('networkidle');
const reportText = await page.locator('body').innerText();
assert.match(reportText, /Low Stock|FEFO Suggestions|Near Expiry/i);
await page.screenshot({ path: `${screenshotDir}/reports.png`, fullPage: true });

await browser.close();
console.log('Phase 5A inventory smoke passed');
