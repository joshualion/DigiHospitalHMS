import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const baseURL = process.env.PHASE1A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const admin = {
    email: process.env.PHASE1A_ADMIN_EMAIL,
    password: process.env.PHASE1A_ADMIN_PASSWORD,
};

const nonAdmin = {
    email: process.env.PHASE1A_NON_ADMIN_EMAIL,
    password: process.env.PHASE1A_NON_ADMIN_PASSWORD,
};

for (const [key, value] of Object.entries({
    adminEmail: admin.email,
    adminPassword: admin.password,
    nonAdminEmail: nonAdmin.email,
    nonAdminPassword: nonAdmin.password,
})) {
    assert(value, `Missing smoke credential: ${key}`);
}

const browser = await chromium.launch({
    executablePath: chromePath,
    headless: true,
});

const context = await browser.newContext({
    baseURL,
    viewport: { width: 1366, height: 900 },
});

const page = await context.newPage();
const consoleErrors = [];
const failedRequests = [];

page.on('console', (message) => {
    if (message.type() === 'error') {
        consoleErrors.push(message.text());
    }
});

page.on('requestfailed', (request) => {
    failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`);
});

async function assertNoBrowserErrors(label) {
    assert.deepEqual(consoleErrors, [], `${label}: JavaScript console errors`);
    assert.deepEqual(failedRequests, [], `${label}: failed network requests`);
}

async function login(user) {
    await page.goto('/login');
    await page.locator('#email').fill(user.email);
    await page.locator('#password').fill(user.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForLoadState('networkidle');
}

await page.goto('/');
await page.waitForLoadState('networkidle');
assert.equal(await page.locator('#app').count(), 1, 'public homepage should mount Inertia');

await page.goto('/login');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Staff login|Login|Email/i);

await login(admin);
await page.goto('/admin/dashboard');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Dashboard|Workspace/i);

for (const path of [
    '/admin/hospital',
    '/admin/facilities',
    '/admin/departments',
    '/admin/staff',
    '/admin/roles',
    '/admin/settings',
    '/admin/numbering',
    '/admin/audit-logs',
]) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    assert.equal(page.url(), `${baseURL}${path}`);
    assert.match(await page.locator('body').innerText(), /Workspace/i);
}

await page.goto('/admin/dashboard');
await page.getByRole('link', { name: 'Facilities', exact: true }).click();
await page.waitForURL('**/admin/facilities');
await page.getByRole('link', { name: 'Departments', exact: true }).click();
await page.waitForURL('**/admin/departments');
await page.reload();
await page.waitForLoadState('networkidle');
assert.equal(page.url(), `${baseURL}/admin/departments`);
await page.goBack();
await page.waitForURL('**/admin/facilities');
await page.goForward();
await page.waitForURL('**/admin/departments');

await page.setViewportSize({ width: 390, height: 844 });
await page.goto('/admin/staff');
await page.waitForLoadState('networkidle');
const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
if (overflow > 1) {
    const offenders = await page.evaluate(() => {
        const client = document.documentElement.clientWidth;

        return Array.from(document.querySelectorAll('body *'))
            .map((element) => {
                const rect = element.getBoundingClientRect();

                return {
                    tag: element.tagName,
                    className: String(element.className),
                    text: (element.textContent || '').trim().slice(0, 80),
                    left: rect.left,
                    right: rect.right,
                    width: rect.width,
                    scrollWidth: element.scrollWidth,
                    clientWidth: element.clientWidth,
                };
            })
            .filter((entry) => entry.right > client + 1 || entry.scrollWidth > entry.clientWidth + 1)
            .slice(0, 10);
    });

    throw new Error(`mobile overflow detected: ${overflow}px ${JSON.stringify(offenders)}`);
}
await page.setViewportSize({ width: 1366, height: 900 });

await assertNoBrowserErrors('authenticated admin smoke');

await page.getByRole('button', { name: 'Logout' }).click();
await page.waitForLoadState('networkidle');
await page.goto('/admin/dashboard');
await page.waitForLoadState('networkidle');
assert.match(page.url(), /\/login$/);

consoleErrors.length = 0;
failedRequests.length = 0;
await login(nonAdmin);
const forbidden = await page.goto('/admin/dashboard');
await page.waitForLoadState('networkidle');
assert.equal(forbidden?.status(), 403);
assert.match(await page.locator('body').innerText(), /403|Forbidden/i);

const unexpectedConsoleErrors = consoleErrors.filter((error) => ! /403 \(Forbidden\)/.test(error));
assert.deepEqual(unexpectedConsoleErrors, [], 'guest and non-admin smoke: unexpected JavaScript console errors');
assert.deepEqual(failedRequests, [], 'guest and non-admin smoke: failed network requests');

await browser.close();
console.log('Phase 1A browser smoke passed');
