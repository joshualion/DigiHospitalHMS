import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE2A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE2A_SCREENSHOT_DIR || 'storage/app/phase2a-smoke';
const admin = {
    email: process.env.PHASE2A_ADMIN_EMAIL || process.env.PHASE1B3_ADMIN_EMAIL,
    password: process.env.PHASE2A_ADMIN_PASSWORD || process.env.PHASE1B3_ADMIN_PASSWORD,
};

for (const [key, value] of Object.entries({ adminEmail: admin.email, adminPassword: admin.password })) {
    assert(value, `Missing smoke credential: ${key}`);
}

await mkdir(screenshotDir, { recursive: true });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();
const unique = Date.now();
const firstName = `Phase2A${unique}`;
const lastName = 'Smoke';
const phone = `080${String(unique).slice(-8)}`;
const updatedOccupation = `Updated occupation ${unique}`;

async function login() {
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname === '/admin/patients');
    await page.waitForLoadState('networkidle');
}

const guest = await context.newPage();
await guest.goto('/admin/patients');
await guest.waitForURL('**/login');
await guest.close();

await login();
await page.goto('/admin/patients');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/patient-list.png`, fullPage: true });

await page.locator('#patient_first').fill(firstName);
await page.locator('#patient_last').fill(lastName);
await page.locator('#patient_dob').fill('1991-03-04');
await page.locator('#patient_phone').fill(phone);
await page.locator('#patient_email').fill(`${firstName.toLowerCase()}@example.test`);
await page.getByPlaceholder('Value').first().fill(`ID-${unique}`);
await page.locator('input[placeholder="Name"]').last().fill('Smoke Next Of Kin');
await page.getByPlaceholder('Relationship').first().fill('Sibling');
const firstRegistration = page.waitForResponse((response) => response.url().endsWith('/admin/patients') && response.request().method() === 'POST');
await page.getByRole('button', { name: 'Register patient' }).click();
const firstRegistrationResponse = await firstRegistration;
const firstRegistrationPayload = firstRegistrationResponse.request().postData() || '';
const firstRegistrationLocation = firstRegistrationResponse.headers().location;
if (firstRegistrationLocation) {
    await page.waitForURL(firstRegistrationLocation);
}
await page.waitForLoadState('networkidle');
if (!/\/admin\/patients\/\d+$/.test(new URL(page.url()).pathname)) {
    const inertia = await page.locator('#app').getAttribute('data-page');
    const errors = inertia ? JSON.stringify(JSON.parse(inertia).props.errors || {}) : '{}';
    throw new Error(`Registration did not reach profile. POST ${firstRegistrationResponse.status()} ${firstRegistrationLocation || ''}. Payload: ${firstRegistrationPayload.slice(0, 800)}. Errors: ${errors}. Page text: ${(await page.locator('body').innerText()).slice(0, 1200)}`);
}
const patientUrl = page.url();
await page.screenshot({ path: `${screenshotDir}/patient-profile.png`, fullPage: true });

await page.goto('/admin/patients');
await page.locator('#patient_first').fill(`${firstName}Dup`);
await page.locator('#patient_last').fill(lastName);
await page.locator('#patient_dob').fill('1991-03-04');
await page.locator('#patient_phone').fill(phone);
await page.getByRole('button', { name: 'Register patient' }).click();
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Possible duplicate records found/);

await page.getByPlaceholder('Search hospital number, name, phone or identifier').fill(phone);
await page.keyboard.press('Enter');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), new RegExp(firstName));

await page.goto(patientUrl);
await page.locator('#show_occupation').fill(updatedOccupation).catch(async () => {
    await page.getByLabel('Occupation').fill(updatedOccupation);
});
await page.getByRole('button', { name: 'Save demographics' }).click();
await page.waitForLoadState('networkidle');

await page.getByPlaceholder('Substance').fill('Smoke allergen');
await page.getByPlaceholder('Reaction').fill('Smoke reaction');
const allergyPost = page.waitForResponse((response) => response.url().includes('/allergies') && response.request().method() === 'POST');
await page.getByRole('button', { name: 'Record allergy' }).click();
await allergyPost;
await page.waitForLoadState('networkidle');

await page.getByPlaceholder('Title').fill('Smoke alert');
await page.getByPlaceholder('Category').fill('identity');
const alertPost = page.waitForResponse((response) => response.url().includes('/alerts') && response.request().method() === 'POST');
await page.getByRole('button', { name: 'Record alert' }).click();
await alertPost;
await page.waitForLoadState('networkidle');
await page.reload();
await page.waitForLoadState('networkidle');

const body = await page.locator('body').innerText();
assert.match(body, /Smoke allergen/);
assert.match(body, /Smoke alert/);
await page.screenshot({ path: `${screenshotDir}/patient-alerts.png`, fullPage: true });

await browser.close();
console.log('Phase 2A patient identity smoke passed');
