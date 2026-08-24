import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE5C_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE5C_SCREENSHOT_DIR || 'storage/app/phase5c-smoke';
const password = process.env.PHASE5C_PASSWORD || 'Phase5CSmoke!';
const storekeeperEmail = process.env.PHASE5C_STOREKEEPER_EMAIL || 'phase5c-storekeeper@example.test';
const approverEmail = process.env.PHASE5C_APPROVER_EMAIL || 'phase5c-approver@example.test';
const unique = Date.now();

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
        page.getByRole('button', { name, exact }).first().click(),
    ]);
    assert(response.status() < 400, `Unexpected ${response.status()} response for ${name}`);
    await page.waitForLoadState('networkidle');
}

await login(storekeeperEmail);
await page.goto('/admin/procurement');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Procurement|Purchase Requisitions/i);

await page.locator('#supplier_code').fill(`P5C-${unique}`);
await page.locator('#supplier_name').fill(`Phase 5C Smoke Supplier ${unique}`);
await page.locator('#supplier_contact_person').fill('Smoke Contact');
await page.locator('#supplier_phone').fill('08000000000');
await page.locator('#supplier_payment_terms').fill('Net 30');
await page.locator('#supplier_lead_time_days').fill('7');
await clickWithResponse('Save supplier', (response) => response.url().endsWith('/admin/procurement/suppliers') && response.request().method() === 'POST');

const reqForm = page.locator('form').filter({ hasText: 'Draft Requisition' });
await selectFirst(reqForm.locator('select').nth(0), 'Phase 5C Main Store');
await selectFirst(reqForm.locator('select').nth(1), 'Phase 5C configured medicine');
await selectFirst(reqForm.locator('select').nth(2), 'P5C-EACH');
await reqForm.locator('#req_qty_0').fill('10');
await reqForm.locator('#req_unit_cost_0').fill('500');
await reqForm.locator('#req_tax_0').fill('0');
await clickWithResponse('Create requisition', (response) => response.url().endsWith('/admin/procurement/requisitions') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Submit', (response) => response.url().includes('/admin/procurement/requisitions/') && response.request().method() === 'PATCH');
await page.screenshot({ path: `${screenshotDir}/submitted.png`, fullPage: true });
await logout();

await login(approverEmail);
await page.goto('/admin/procurement');
await page.waitForLoadState('networkidle');
await clickWithResponse('Approve', (response) => response.url().includes('/admin/procurement/requisitions/') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Convert PO', (response) => response.url().includes('/admin/procurement/requisitions/') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /PO-/);
await clickWithResponse('Partial receipt', (response) => response.url().includes('/receipts') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Full receipt', (response) => response.url().includes('/receipts') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /fully_received|partially_received/i);
await clickWithResponse('Supplier return', (response) => response.url().includes('/return') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
await clickWithResponse('Reverse receipt', (response) => response.url().includes('/reverse') && response.request().method() === 'POST');
await page.screenshot({ path: `${screenshotDir}/receipt-corrections.png`, fullPage: true });

await browser.close();
console.log('Phase 5C procurement smoke passed');
