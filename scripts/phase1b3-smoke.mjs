import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE1B3_BASE_URL || process.env.PHASE1B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE1B3_SCREENSHOT_DIR || 'storage/app/phase1b3-smoke';
const admin = {
    email: process.env.PHASE1B3_ADMIN_EMAIL || process.env.PHASE1B_ADMIN_EMAIL || process.env.PHASE1A_ADMIN_EMAIL,
    password: process.env.PHASE1B3_ADMIN_PASSWORD || process.env.PHASE1B_ADMIN_PASSWORD || process.env.PHASE1A_ADMIN_PASSWORD,
};

for (const [key, value] of Object.entries({ adminEmail: admin.email, adminPassword: admin.password })) {
    assert(value, `Missing smoke credential: ${key}`);
}

await mkdir(screenshotDir, { recursive: true });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const page = await context.newPage();
const consoleErrors = [];
const failedRequests = [];
const unique = Date.now();
const heroHeadline = `Phase 1B3 hero ${unique}`;
const serviceTitle = `Phase 1B3 service ${unique}`;
const footerSummary = `Phase 1B3 footer ${unique}`;

page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
});

page.on('requestfailed', (request) => {
    const failure = request.failure()?.errorText ?? '';
    if (!/net::ERR_ABORTED/.test(failure)) failedRequests.push(`${request.method()} ${request.url()} ${failure}`);
});

async function login() {
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL('**/dashboard');
    await page.waitForLoadState('networkidle');
}

await login();
await page.goto('/admin/public-website');
await page.waitForLoadState('networkidle');
await page.getByRole('link', { name: 'Manage' }).first().click();
await page.waitForURL('**/admin/public-website/pages/**');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/structured-editor-start.png`, fullPage: true });

await page.getByRole('button', { name: 'Sections' }).click();
await page.getByRole('button', { name: /Hero slider/i }).click();
await page.getByPlaceholder('Headline').first().fill(heroHeadline);
await page.getByRole('button', { name: 'Save section draft' }).click();
await page.waitForLoadState('networkidle');

await page.getByRole('button', { name: 'Content items' }).click();
await page.getByText(/General consultation|Phase 1B3 service/).first().click();
await page.getByPlaceholder('Title').first().fill(serviceTitle);
await page.getByPlaceholder('Description').first().fill('Structured editor smoke service description');
await page.getByRole('button', { name: 'Save item draft' }).click();
await page.waitForLoadState('networkidle');

await page.getByRole('button', { name: 'Branding & SEO' }).click();
await page.getByLabel('Footer summary').fill(footerSummary);
await page.getByRole('button', { name: 'Save branding, navigation and footer draft' }).click();
await page.waitForLoadState('networkidle');

const previewHref = await page.getByRole('link', { name: 'Preview draft' }).getAttribute('href');
const previewPage = await context.newPage();
await previewPage.goto(previewHref);
await previewPage.waitForLoadState('networkidle');
const previewText = await previewPage.locator('body').innerText();
assert.match(previewText, /Preview mode/);
assert.match(previewText, new RegExp(heroHeadline));
assert.match(previewText, new RegExp(footerSummary));
await previewPage.screenshot({ path: `${screenshotDir}/preview-structured-draft.png`, fullPage: true });
await previewPage.close();

const pagePublish = page.waitForResponse((response) => response.url().includes('/admin/public-website/pages/') && response.url().includes('/publish') && response.request().method() === 'POST');
await page.getByRole('button', { name: 'Publish page' }).click();
await pagePublish;
await page.waitForLoadState('networkidle');

await page.getByRole('button', { name: 'Content items' }).click();
await page.getByText(serviceTitle).first().click();
const serviceCard = page.locator('article').filter({ hasText: serviceTitle }).first();
const itemPublish = page.waitForResponse((response) => response.url().includes('/admin/public-website/items/') && response.url().includes('/publish') && response.request().method() === 'POST');
await serviceCard.getByRole('button', { name: /^Publish$/ }).click();
await itemPublish;
await page.waitForLoadState('networkidle');

await page.goto('/');
await page.waitForLoadState('networkidle');
const publicText = await page.locator('body').innerText();
assert.match(publicText, new RegExp(heroHeadline));
assert.match(publicText, new RegExp(serviceTitle));
assert.match(publicText, new RegExp(footerSummary));
await page.screenshot({ path: `${screenshotDir}/published-structured-homepage.png`, fullPage: true });

await page.setViewportSize({ width: 390, height: 844 });
await page.goto('/admin/public-website');
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/mobile-admin-overview.png`, fullPage: true });

const firstPartyFailures = failedRequests.filter((entry) => entry.includes(baseURL));
assert.deepEqual(consoleErrors.filter((entry) => !/403 \(Forbidden\)/.test(entry)), [], 'Phase 1B.3 console errors');
assert.deepEqual(firstPartyFailures, [], 'Phase 1B.3 failed first-party requests');

await browser.close();
console.log('Phase 1B.3 structured editor smoke passed');
