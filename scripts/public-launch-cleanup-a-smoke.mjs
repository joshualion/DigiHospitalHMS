import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';

const baseURL = process.env.PUBLIC_LAUNCH_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PUBLIC_LAUNCH_SCREENSHOT_DIR || 'storage/app/public-launch-cleanup-a';
const widths = (process.env.PUBLIC_LAUNCH_WIDTHS || '320,375,768,1024,1440').split(',').map((width) => Number(width.trim())).filter(Boolean);
const modes = (process.env.PUBLIC_LAUNCH_MODES || 'light,dark').split(',').map((mode) => mode.trim()).filter(Boolean);
const heroStates = (process.env.PUBLIC_LAUNCH_STATES || 'zero,one,multiple,broken').split(',').map((state) => state.trim()).filter(Boolean);

function setHeroState(state) {
    execFileSync('php', ['artisan', 'public-site:hero-smoke-state', state], { stdio: 'inherit' });
}

async function assertNoOverflow(page, label) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    assert(overflow <= 1, `${label}: horizontal overflow ${overflow}px`);
}

await mkdir(screenshotDir, { recursive: true });
execFileSync('php', ['artisan', 'public-site:hero-smoke-state', 'backup'], { stdio: 'inherit' });

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL });
await context.addInitScript(() => {
    window.__setPublicLaunchTheme = (appearance) => {
        window.localStorage.setItem('public-theme-preference', JSON.stringify({ appearance, accent: 'calm' }));
    };
});
const page = await context.newPage();
page.setDefaultTimeout(8000);
page.setDefaultNavigationTimeout(30000);
const consoleErrors = [];
const failedRequests = [];

page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
});

page.on('requestfailed', (request) => {
    const failure = request.failure()?.errorText ?? '';
    if (!/net::ERR_ABORTED/.test(failure)) failedRequests.push(`${request.method()} ${request.url()} ${failure}`);
});

try {
    for (const state of heroStates) {
        setHeroState(state);

        for (const mode of modes) {
            for (const width of widths) {
                await page.setViewportSize({ width, height: width < 768 ? 760 : 900 });
                await page.goto('/', { waitUntil: 'domcontentloaded' });
                await page.evaluate((appearance) => {
                    window.localStorage.setItem('public-theme-preference', JSON.stringify({ appearance, accent: 'calm' }));
                    document.documentElement.dataset.publicAppearancePreference = appearance;
                    document.documentElement.dataset.publicAppearance = appearance;
                    document.documentElement.dataset.publicAccent = 'calm';
                }, mode);
                await page.waitForTimeout(300);
                await assertNoOverflow(page, `${state}-${mode}-${width}`);
                await page.screenshot({ path: `${screenshotDir}/home-${state}-${mode}-${width}.png`, fullPage: false });
            }
        }
    }

    if (process.env.PUBLIC_LAUNCH_EXTRA_PAGES !== '0') {
        for (const path of ['/services', '/departments', '/doctors', '/news', '/contact', '/appointment/request', '/policies']) {
            await page.setViewportSize({ width: 375, height: 760 });
            await page.goto(path, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(300);
            await assertNoOverflow(page, path);
        }
    }

    const firstPartyFailures = failedRequests.filter((entry) => entry.includes(baseURL) && !entry.includes('/frontend/images/slider/missing-launch-smoke.jpg'));
    const unexpectedConsoleErrors = consoleErrors.filter((error) => !(heroStates.includes('broken') && /404 \(Not Found\)/.test(error)));
    assert.deepEqual(unexpectedConsoleErrors, [], 'JavaScript console errors');
    assert.deepEqual(firstPartyFailures, [], 'failed first-party requests');
} finally {
    await browser.close();
    execFileSync('php', ['artisan', 'public-site:hero-smoke-state', 'restore'], { stdio: 'inherit' });
}

console.log('Public launch cleanup A smoke passed');
