import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE1B_BASE_URL || process.env.PHASE1A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE1B_SCREENSHOT_DIR || 'storage/app/phase1b-smoke';

const admin = {
    email: process.env.PHASE1B_ADMIN_EMAIL || process.env.PHASE1A_ADMIN_EMAIL,
    password: process.env.PHASE1B_ADMIN_PASSWORD || process.env.PHASE1A_ADMIN_PASSWORD,
};

for (const [key, value] of Object.entries({ adminEmail: admin.email, adminPassword: admin.password })) {
    assert(value, `Missing smoke credential: ${key}`);
}

await mkdir(screenshotDir, { recursive: true });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 950 } });
await context.grantPermissions([]);
const page = await context.newPage();
const consoleErrors = [];
const failedRequests = [];

page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
});

page.on('requestfailed', (request) => {
    const failure = request.failure()?.errorText ?? '';
    if (!/net::ERR_ABORTED/.test(failure)) failedRequests.push(`${request.method()} ${request.url()} ${failure}`);
});

async function assertHealthy(label) {
    const firstPartyFailures = failedRequests.filter((entry) => entry.includes(baseURL));
    const unexpectedConsoleErrors = consoleErrors.filter((error) => ! /403 \(Forbidden\)/.test(error));
    assert.deepEqual(unexpectedConsoleErrors, [], `${label}: unexpected JavaScript console errors`);
    assert.deepEqual(firstPartyFailures, [], `${label}: failed first-party requests`);
}

async function assertNoOverflow(label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    assert(overflow <= 1, `${label}: horizontal overflow ${overflow}px`);
}

async function login() {
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForLoadState('networkidle');
}

await page.goto('/');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Services|Departments|Appointment/i);
await assertNoOverflow('desktop homepage');
await page.screenshot({ path: `${screenshotDir}/desktop-homepage.png`, fullPage: true });

await page.setViewportSize({ width: 390, height: 844 });
await page.goto('/');
await page.waitForLoadState('networkidle');
await page.getByRole('button', { name: 'Open menu' }).click();
await page.locator('aside[aria-label="Mobile navigation"] a[href="/services"]').click();
await page.waitForFunction(() => window.location.pathname === '/services');
await assertNoOverflow('mobile services navigation');
await page.screenshot({ path: `${screenshotDir}/mobile-homepage.png`, fullPage: true });

await page.setViewportSize({ width: 1440, height: 950 });
for (const path of ['/services', '/doctors', '/news', '/contact']) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    assert.equal(page.url(), `${baseURL}${path}`);
    await assertNoOverflow(path);
}

await page.goto('/doctors/sample-clinician-profile');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Sample clinician profile/i);

await login();
await page.goto('/admin/public-website');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Website Pages|Media Library/i);
await page.screenshot({ path: `${screenshotDir}/admin-overview.png`, fullPage: true });

await page.getByRole('link', { name: 'Manage' }).first().click();
await page.waitForURL('**/admin/public-website/pages/**');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Page Draft and SEO|Sections/i);
await page.screenshot({ path: `${screenshotDir}/section-editor.png`, fullPage: true });

const preview = page.waitForEvent('popup');
await page.getByRole('link', { name: 'Preview draft' }).click();
const previewPage = await preview;
await previewPage.waitForLoadState('networkidle');
assert.match(await previewPage.locator('body').innerText(), /Preview mode/i);
await previewPage.screenshot({ path: `${screenshotDir}/preview-mode.png`, fullPage: true });
await previewPage.close();

await page.getByRole('button', { name: 'Publish page' }).click();
await page.waitForLoadState('networkidle');
await page.goto('/');
await page.waitForLoadState('networkidle');
await page.reload();
await page.waitForLoadState('networkidle');
await page.goBack();
await page.waitForLoadState('networkidle');
await page.goForward();
await page.waitForLoadState('networkidle');

await page.goto('/dashboard');
await page.waitForLoadState('networkidle');
await page.getByRole('button', { name: 'Logout' }).click();
await page.waitForLoadState('networkidle');
const protectedResponse = await page.goto('/admin/public-website');
await page.waitForLoadState('networkidle');
assert([302, 200].includes(protectedResponse?.status() ?? 0));
assert.match(page.url(), /\/login$/);

await assertHealthy('phase 1b browser smoke');
await browser.close();
console.log('Phase 1B browser smoke passed');
