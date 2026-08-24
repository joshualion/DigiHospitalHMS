import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE5B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE5B_SCREENSHOT_DIR || 'storage/app/phase5b-smoke';
const password = process.env.PHASE5B_PASSWORD || 'Phase5BSmoke!';
const doctorEmail = process.env.PHASE5B_DOCTOR_EMAIL || 'phase5b-doctor@example.test';
const pharmacistEmail = process.env.PHASE5B_PHARMACIST_EMAIL || 'phase5b-pharmacist@example.test';

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

async function clickFirstWithResponse(name, matcher) {
    const [response] = await Promise.all([
        page.waitForResponse(matcher),
        page.getByRole('button', { name, exact: true }).first().click(),
    ]);
    assert(response.status() < 400, `Unexpected ${response.status()} response for ${name}`);
    await page.waitForLoadState('networkidle');
}

await login(doctorEmail);
await page.goto('/admin/pharmacy/prescriptions');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Draft Prescription/i);

const draftForm = page.locator('form').filter({ hasText: 'Draft Prescription' });
await selectFirst(draftForm.locator('select').nth(0), 'Phase5B Patient|P5B-000001');
await selectFirst(draftForm.locator('select').nth(1), 'Phase 5B');
await selectFirst(draftForm.locator('select').nth(2));
await selectFirst(draftForm.locator('select').nth(3), 'Phase 5B configured medicine');
await selectFirst(draftForm.locator('select').nth(4), 'P5B-TAB');
await draftForm.locator('textarea').first().fill('Smoke prescription clinical note.');
await draftForm.locator('input[id^="dose_"]').fill('1 tablet');
await draftForm.locator('input[id^="qty_"]').fill('10');
await draftForm.locator('input[id^="freq_"]').fill('twice daily');
await draftForm.locator('input[id^="duration_"]').fill('5 days');
await draftForm.locator('textarea').nth(1).fill('Take after meals.');
await clickWithResponse('Create draft', (response) => response.url().endsWith('/admin/pharmacy/prescriptions') && response.request().method() === 'POST');
await page.waitForURL('**/admin/pharmacy/prescriptions/*');
assert.match(await page.locator('body').innerText(), /Allergies And Alerts|Smoke allergy|Smoke alert/i);
await clickWithResponse('Sign', (response) => response.url().includes('/sign') && response.request().method() === 'POST');
const prescriptionPath = new URL(page.url()).pathname;
await page.screenshot({ path: `${screenshotDir}/signed.png`, fullPage: true });
await logout();

await login(pharmacistEmail);
await page.goto(prescriptionPath);
await page.waitForLoadState('networkidle');
await clickWithResponse('Record review', (response) => response.url().includes('/reviews') && response.request().method() === 'POST');
await clickWithResponse('Bill', (response) => response.url().includes('/bill') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Invoice/i);

const dispenseForm = page.locator('form').filter({ hasText: 'Dispense' }).first();
await selectFirst(dispenseForm.locator('select').nth(0), 'Phase 5B Pharmacy');
await selectFirst(dispenseForm.locator('select').nth(1), 'P5B-');
await dispenseForm.locator('input[type="number"]').fill('4');
await clickWithResponse('Dispense', (response) => response.url().includes('/dispense') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');

const secondDispenseForm = page.locator('form').filter({ hasText: 'Dispense' }).first();
await selectFirst(secondDispenseForm.locator('select').nth(0), 'Phase 5B Pharmacy');
await selectFirst(secondDispenseForm.locator('select').nth(1), 'P5B-');
await secondDispenseForm.locator('input[type="number"]').fill('6');
await clickWithResponse('Dispense', (response) => response.url().includes('/dispense') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /completed|outstanding 0\.0000/i);

page.once('dialog', async (dialog) => dialog.accept('Smoke patient return'));
await clickFirstWithResponse('Return', (response) => response.url().includes('/return') && response.request().method() === 'POST');
await page.reload();
await page.waitForLoadState('networkidle');
page.once('dialog', async (dialog) => dialog.accept('Smoke dispense reversal'));
await Promise.all([
    page.waitForResponse((response) => response.url().includes('/reverse') && response.request().method() === 'POST'),
    page.getByRole('button', { name: 'Reverse', exact: true }).nth(1).click(),
]);
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/dispensed-returned-reversed.png`, fullPage: true });

await browser.close();
console.log('Phase 5B prescribing and dispensing smoke passed');
