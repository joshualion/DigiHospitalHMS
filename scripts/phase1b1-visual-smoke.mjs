import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';

const baseURL = process.env.PHASE1B_BASE_URL || process.env.PHASE1A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE1B1_SCREENSHOT_DIR || 'storage/app/phase1b1-smoke';

const admin = {
    email: process.env.PHASE1B_ADMIN_EMAIL || process.env.PHASE1A_ADMIN_EMAIL,
    password: process.env.PHASE1B_ADMIN_PASSWORD || process.env.PHASE1A_ADMIN_PASSWORD,
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

page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
});

page.on('requestfailed', (request) => {
    const failure = request.failure()?.errorText ?? '';
    if (!/net::ERR_ABORTED/.test(failure)) failedRequests.push(`${request.method()} ${request.url()} ${failure}`);
});

async function setTheme(appearance, accent) {
    await page.goto('/');
    await page.evaluate(([mode, color]) => {
        window.localStorage.setItem('public-theme-preference', JSON.stringify({ appearance: mode, accent: color }));
    }, [appearance, accent]);
}

async function assertNoOverflow(label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    assert(overflow <= 1, `${label}: horizontal overflow ${overflow}px`);
}

async function assertTheme(appearance, accent, label) {
    const attrs = await page.evaluate(() => ({
        appearance: document.documentElement.dataset.publicAppearance,
        preferred: document.documentElement.dataset.publicAppearancePreference,
        accent: document.documentElement.dataset.publicAccent,
    }));

    assert.equal(attrs.preferred, appearance, `${label}: stored appearance preference`);
    assert.equal(attrs.accent, accent, `${label}: accent`);
    assert(['light', 'dark'].includes(attrs.appearance), `${label}: resolved appearance`);
}

async function gotoAndCapture(path, name, viewport, appearance, accent) {
    await page.setViewportSize(viewport);
    await setTheme(appearance, accent);
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await assertTheme(appearance, accent, name);
    await assertNoOverflow(name);
    await page.screenshot({ path: `${screenshotDir}/${name}.png`, fullPage: true });
}

async function login() {
    await page.goto('/login');
    await page.locator('#email').fill(admin.email);
    await page.locator('#password').fill(admin.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForLoadState('networkidle');
}

await gotoAndCapture('/', 'home-desktop-light-calm', { width: 1440, height: 960 }, 'light', 'calm');
await gotoAndCapture('/', 'home-desktop-dark-calm', { width: 1440, height: 960 }, 'dark', 'calm');
await gotoAndCapture('/', 'home-mobile-light-healing', { width: 390, height: 844 }, 'light', 'healing');
await gotoAndCapture('/', 'home-mobile-dark-alert', { width: 390, height: 844 }, 'dark', 'alert');
await gotoAndCapture('/', 'home-tablet-light-blood', { width: 768, height: 1024 }, 'light', 'blood');

await page.setViewportSize({ width: 1440, height: 960 });
await page.goto('/');
await page.waitForLoadState('networkidle');
await page.getByRole('button', { name: 'Open theme settings' }).first().click();
await page.screenshot({ path: `${screenshotDir}/theme-chooser.png`, fullPage: true });
await page.getByRole('button', { name: /Dark/i }).first().click();
await page.getByRole('button', { name: /Seagrass/i }).first().click();
await page.reload();
await page.waitForLoadState('networkidle');
await assertTheme('dark', 'seagrass', 'theme persistence');

await page.getByRole('button', { name: /Emergency information/i }).click();
await page.screenshot({ path: `${screenshotDir}/services-accordion-expanded.png`, fullPage: true });
await assert.equal(await page.getByRole('button', { name: /Emergency information/i }).getAttribute('aria-expanded'), 'true');

await page.setViewportSize({ width: 390, height: 844 });
await page.goto('/');
await page.waitForLoadState('networkidle');
await page.getByRole('button', { name: 'Open menu' }).click();
await page.screenshot({ path: `${screenshotDir}/mobile-navigation.png`, fullPage: true });
await page.keyboard.press('Escape');

for (const path of ['/services', '/departments', '/doctors', '/doctors/sample-clinician-profile', '/contact', '/appointment', '/news', '/news/public-site-launch-note']) {
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await assertNoOverflow(path);
}

await context.close();
const reducedContext = await browser.newContext({ baseURL, viewport: { width: 1280, height: 900 }, reducedMotion: 'reduce' });
const reducedPage = await reducedContext.newPage();
await reducedPage.goto('/');
await reducedPage.waitForLoadState('networkidle');
assert.equal(await reducedPage.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches), true);
await reducedContext.close();

const adminContext = await browser.newContext({ baseURL, viewport: { width: 1440, height: 960 } });
const adminPage = await adminContext.newPage();
await adminPage.goto('/login');
await adminPage.locator('#email').fill(admin.email);
await adminPage.locator('#password').fill(admin.password);
await adminPage.getByRole('button', { name: 'Login' }).click();
await adminPage.waitForLoadState('networkidle');
await adminPage.goto('/admin/public-website');
await adminPage.waitForLoadState('networkidle');
await adminPage.getByRole('link', { name: 'Manage' }).first().click();
await adminPage.waitForURL('**/admin/public-website/pages/**');
await adminPage.waitForLoadState('networkidle');
assert.match(await adminPage.locator('body').innerText(), /Public Theme Defaults/i);
await adminPage.screenshot({ path: `${screenshotDir}/admin-theme-settings.png`, fullPage: true });

const preview = adminPage.waitForEvent('popup');
await adminPage.getByRole('link', { name: 'Preview draft' }).click();
const previewPage = await preview;
await previewPage.waitForLoadState('networkidle');
assert.match(await previewPage.locator('body').innerText(), /Preview mode/i);
await previewPage.screenshot({ path: `${screenshotDir}/draft-preview.png`, fullPage: true });
await previewPage.close();

await adminContext.close();

const firstPartyFailures = failedRequests.filter((entry) => entry.includes(baseURL));
assert.deepEqual(consoleErrors, [], 'Phase 1B.1: JavaScript console errors');
assert.deepEqual(firstPartyFailures, [], 'Phase 1B.1: failed first-party requests');

await browser.close();
console.log('Phase 1B.1 visual/theme smoke passed');
