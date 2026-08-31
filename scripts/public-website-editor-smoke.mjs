import { chromium } from 'playwright';
import assert from 'node:assert/strict';

const baseURL = process.env.PHASE1B3_BASE_URL || process.env.PHASE1B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const editorPath = process.env.PUBLIC_WEBSITE_EDITOR_PATH || '/admin/public-website/pages/10';
const widths = (process.env.PUBLIC_WEBSITE_EDITOR_WIDTHS || '375,768,1024,1440').split(',').map((width) => Number(width.trim())).filter(Boolean);
const admin = {
    email: process.env.PHASE1B3_ADMIN_EMAIL || process.env.PHASE1B_ADMIN_EMAIL || process.env.PHASE1A_ADMIN_EMAIL,
    password: process.env.PHASE1B3_ADMIN_PASSWORD || process.env.PHASE1B_ADMIN_PASSWORD || process.env.PHASE1A_ADMIN_PASSWORD,
};

for (const [key, value] of Object.entries({ adminEmail: admin.email, adminPassword: admin.password })) {
    assert(value, `Missing smoke credential: ${key}`);
}

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();
const consoleErrors = [];
const pageErrors = [];
const failedRequests = [];

page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
});

page.on('pageerror', (error) => pageErrors.push(error.message));

page.on('requestfailed', (request) => {
    const failure = request.failure()?.errorText ?? '';
    if (!/net::ERR_ABORTED/.test(failure)) failedRequests.push(`${request.method()} ${request.url()} ${failure}`);
});

try {
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL('**/dashboard');
    await page.waitForLoadState('networkidle');

    const indexResponse = await page.goto('/admin/public-website');
    assert.equal(indexResponse?.status(), 200, 'public website index status');
    await page.waitForLoadState('networkidle');

    for (const width of widths) {
        await page.setViewportSize({ width, height: width < 768 ? 760 : 960 });
        const editorResponse = await page.goto(editorPath);
        assert.equal(editorResponse?.status(), 200, `${editorPath} status at ${width}px`);
        await page.waitForLoadState('networkidle');
        await page.getByRole('button', { name: 'Sections' }).click();
        await page.getByRole('button', { name: 'Branding & SEO' }).click();
    }

    const staleResponse = await page.goto('/admin/public-website/pages/999999');
    assert.ok([200, 302].includes(staleResponse?.status() ?? 0), 'stale editor response status');
    await page.waitForURL('**/admin/public-website');
    await page.waitForLoadState('networkidle');

    const firstPartyFailures = failedRequests.filter((entry) => entry.includes(baseURL));
    assert.deepEqual(consoleErrors.filter((entry) => !/403 \(Forbidden\)/.test(entry)), [], 'browser console errors');
    assert.deepEqual(pageErrors, [], 'browser page errors');
    assert.deepEqual(firstPartyFailures, [], 'failed first-party requests');

    console.log('Public website editor smoke passed');
} catch (error) {
    console.error(JSON.stringify({
        url: page.url(),
        body: (await page.locator('body').innerText().catch(() => '')).slice(0, 1000),
        consoleErrors,
        pageErrors,
        failedRequests,
    }, null, 2));
    throw error;
} finally {
    await browser.close();
}
