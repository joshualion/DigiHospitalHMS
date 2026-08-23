import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';

const baseURL = process.env.PHASE4B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE4B_SCREENSHOT_DIR || 'storage/app/phase4b-smoke';
const password = process.env.PHASE4B_PASSWORD || 'Phase4BSmoke!';
const doctorEmail = process.env.PHASE4B_DOCTOR_EMAIL || 'phase4b-doctor@example.test';
const radiologyEmail = process.env.PHASE4B_RADIOLOGY_EMAIL || 'phase4b-radiology@example.test';
const approverEmail = process.env.PHASE4B_APPROVER_EMAIL || 'phase4b-approver@example.test';

await mkdir(screenshotDir, { recursive: true });
const attachmentPath = `${screenshotDir}/support.png`;
await writeFile(attachmentPath, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', 'base64'));

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

await login(doctorEmail);
await page.goto('/admin/radiology/requests');
await page.waitForLoadState('networkidle');
const orderForm = page.locator('form').filter({ hasText: 'Order Radiology Request' });
await selectFirst(orderForm.locator('select').nth(0), 'Phase4B');
await selectFirst(orderForm.locator('select').nth(1), 'Phase 4B');
await selectFirst(orderForm.locator('select').nth(2));
await selectFirst(orderForm.locator('select').nth(3));
await selectFirst(orderForm.locator('select').nth(4), 'Phase 4B imaging study');
await orderForm.locator('textarea').fill('Smoke clinical indication for imaging.');
await clickWithResponse('Order request', (response) => response.url().endsWith('/admin/radiology/requests') && response.request().method() === 'POST');
await page.waitForURL('**/admin/radiology/requests/*');
assert.match(await page.locator('body').innerText(), /Billing invoice/i);
const requestPath = new URL(page.url()).pathname;
await page.screenshot({ path: `${screenshotDir}/ordered.png`, fullPage: true });
await logout();

await login(radiologyEmail);
await page.goto(requestPath);
await page.waitForLoadState('networkidle');
const scheduleForm = page.locator('form').filter({ hasText: 'Schedule' });
await scheduleForm.locator('#scheduled_at').fill('2026-09-01T10:00');
await scheduleForm.locator('#room').fill('XR-Smoke-1');
await scheduleForm.locator('#equipment').fill('XR-Smoke-Equipment');
await selectFirst(scheduleForm.locator('select').first(), 'Phase4B');
await clickWithResponse('Save schedule', (response) => response.url().includes('/schedule') && response.request().method() === 'PATCH');

await clickWithResponse('Arrived', (response) => response.url().includes('/transition') && response.request().method() === 'PATCH');
page.once('dialog', async (dialog) => dialog.accept('Smoke performance notes'));
await clickWithResponse('Performed', (response) => response.url().includes('/transition') && response.request().method() === 'PATCH');
await clickWithResponse('Reporting', (response) => response.url().includes('/transition') && response.request().method() === 'PATCH');

const reportForm = page.locator('form').filter({ hasText: 'Structured Report' });
await selectFirst(reportForm.locator('select').first(), 'Phase4B');
await reportForm.locator('textarea').nth(0).fill('Smoke radiology findings.');
await reportForm.locator('textarea').nth(1).fill('Smoke radiology impression.');
await reportForm.locator('textarea').nth(2).fill('Smoke radiology recommendation.');
await reportForm.locator('input[type="checkbox"]').check();
await reportForm.locator('textarea').nth(3).fill('Smoke critical finding note.');
await clickWithResponse('Save draft report', (response) => response.url().includes('/report') && response.request().method() === 'POST');
await clickWithResponse('Verify', (response) => response.url().includes('/transition') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');

await page.locator('input[type="file"]').setInputFiles(attachmentPath);
await clickWithResponse('Upload to quarantine', (response) => response.url().includes('/attachments') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /quarantined/i);
await clickWithResponse('Clear', (response) => response.url().includes('/clear') && response.request().method() === 'PATCH');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /cleared/i);
await page.screenshot({ path: `${screenshotDir}/verified-with-attachment.png`, fullPage: true });
await logout();

await login(approverEmail);
await page.goto(requestPath);
await page.waitForLoadState('networkidle');
await clickWithResponse('Approve', (response) => response.url().includes('/transition') && response.request().method() === 'PATCH');
await page.locator('input[id="communicated_to"]').fill('Ordering clinician');
await page.locator('input[id="method"]').fill('phone');
await page.locator('textarea[placeholder="Communication and escalation notes"]').fill('Smoke critical finding communication.');
await clickWithResponse('Record communication', (response) => response.url().includes('/critical-communications') && response.request().method() === 'POST');
await clickWithResponse('Release', (response) => response.url().includes('/transition') && response.request().method() === 'PATCH');
await page.locator('input[placeholder="Reason"]').fill('Smoke amendment');
await page.locator('textarea[placeholder="Amendment"]').fill('Append-only smoke amendment.');
await clickWithResponse('Add amendment', (response) => response.url().includes('/amendments') && response.request().method() === 'POST');
await page.getByRole('link', { name: 'Report' }).click();
await page.waitForURL('**/report');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Approved Radiology Report/i);
await page.screenshot({ path: `${screenshotDir}/report.png`, fullPage: true });

await browser.close();
console.log('Phase 4B radiology smoke passed');
