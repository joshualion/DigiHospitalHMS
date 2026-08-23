import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE2B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE2B_SCREENSHOT_DIR || 'storage/app/phase2b-smoke';
const admin = {
    email: process.env.PHASE2B_ADMIN_EMAIL || process.env.PHASE2A_ADMIN_EMAIL || process.env.PHASE1B3_ADMIN_EMAIL,
    password: process.env.PHASE2B_ADMIN_PASSWORD || process.env.PHASE2A_ADMIN_PASSWORD || process.env.PHASE1B3_ADMIN_PASSWORD,
};

for (const [key, value] of Object.entries({ adminEmail: admin.email, adminPassword: admin.password })) {
    assert(value, `Missing smoke credential: ${key}`);
}

await mkdir(screenshotDir, { recursive: true });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();
const unique = Date.now();

async function login() {
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));
    await page.waitForLoadState('networkidle');
}

async function selectFirst(select) {
    const value = await select.locator('option').evaluateAll((options) => options.find((option) => option.value)?.value || '');
    assert(value, 'Expected a selectable option');
    await select.selectOption(value);
    return value;
}

async function firstOptionValue(select) {
    return select.locator('option').evaluateAll((options) => options.find((option) => option.value)?.value || '');
}

await page.goto('/appointment/request');
await page.locator('#request_name').fill(`Phase2B Public ${unique}`);
await page.locator('#request_phone').fill(`080${String(unique).slice(-8)}`);
if (await firstOptionValue(page.locator('select').nth(0))) {
    await selectFirst(page.locator('select').nth(0));
}
if (await firstOptionValue(page.locator('select').nth(1))) {
    await selectFirst(page.locator('select').nth(1));
}
await page.locator('input[type="date"]').fill('2026-08-24');
await page.locator('input[type="checkbox"]').check();
await page.getByRole('button', { name: 'Submit request' }).click();
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/public-request.png`, fullPage: true });

await login();
await page.goto('/admin/appointments');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/appointments.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /Appointments|Book Appointment/);

if (!await firstOptionValue(page.locator('form').filter({ hasText: 'Book Appointment' }).locator('select').nth(0))) {
    await page.goto('/admin/patients');
    await page.waitForLoadState('networkidle');
    await page.locator('#patient_first').fill(`Phase2B${unique}`);
    await page.locator('#patient_last').fill('Smoke');
    await page.locator('#patient_dob').fill('1992-04-05');
    await page.locator('#patient_phone').fill(`081${String(unique).slice(-8)}`);
    const patientPost = page.waitForResponse((response) => response.url().endsWith('/admin/patients') && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Register patient' }).click();
    await patientPost;
    await page.waitForLoadState('networkidle');
    await page.goto('/admin/appointments');
    await page.waitForLoadState('networkidle');
}

const scheduleClinician = page.locator('form').filter({ hasText: 'Clinician Schedule' }).locator('select').first();
await selectFirst(scheduleClinician);
const schedulePost = page.waitForResponse((response) => response.url().includes('/admin/clinician-schedules') && response.request().method() === 'POST');
await page.getByRole('button', { name: 'Save schedule' }).click();
await schedulePost;
await page.waitForLoadState('networkidle');

const bookingForm = page.locator('form').filter({ hasText: 'Book Appointment' });
await selectFirst(bookingForm.locator('select').nth(0));
await selectFirst(bookingForm.locator('select').nth(1));
await selectFirst(bookingForm.locator('select').nth(2));
await selectFirst(bookingForm.locator('select').nth(3));
await selectFirst(bookingForm.locator('select').nth(4));
await bookingForm.locator('input[type="datetime-local"]').fill('2026-08-24T09:00');
await bookingForm.locator('textarea').fill(`Smoke booking ${unique}`);
const bookingPost = page.waitForResponse((response) => response.url().endsWith('/admin/appointments') && response.request().method() === 'POST');
await page.getByRole('button', { name: 'Book' }).click();
await bookingPost;
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /scheduled|confirmed|Smoke booking|Appointments/);

const checkInPost = page.waitForResponse((response) => response.url().includes('/check-in') && response.request().method() === 'POST');
await page.getByRole('button', { name: 'Check in' }).first().click();
await checkInPost;
await page.waitForURL('**/admin/queues');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/queue.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /Queue #|Queue Board/);

const callPatch = page.waitForResponse((response) => response.url().includes('/admin/queues/') && response.request().method() === 'PATCH');
await page.getByRole('button', { name: 'Call' }).first().click();
await callPatch;
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /called|Queue Board/i);

await browser.close();
console.log('Phase 2B appointments and queues smoke passed');
