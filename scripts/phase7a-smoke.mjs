import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';

const baseURL = process.env.PHASE7A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE7A_SCREENSHOT_DIR || 'storage/app/phase7a-smoke';
const data = JSON.parse(await readFile(process.env.PHASE7A_CONTEXT || 'storage/app/phase7a-smoke/context.json', 'utf8'));

await mkdir(screenshotDir, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();

async function login(email) {
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(data.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));
}

async function patchButton(locator) {
    const [response] = await Promise.all([
        page.waitForResponse((response) => response.url().includes('/components/') && response.request().method() === 'PATCH' && response.status() < 400),
        locator.click(),
    ]);
    assert(response.status() < 400);
    await page.waitForLoadState('networkidle');
}

await login(data.verifier_email);
await page.goto('/admin/blood-bank');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Blood Bank|quarantine/i);

const donationId = data.donation_url.split('/').pop();
execFileSync('php', ['scripts/phase7a-smoke-verify.php', donationId, data.verifier_email], { stdio: 'inherit' });

await page.goto(data.donation_url);
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Blood Donation|Components|quarantined/i);

execFileSync('php', ['scripts/phase7a-smoke-component-actions.php', donationId, data.verifier_email, data.target_location], { stdio: 'inherit' });
await page.reload();
await page.waitForLoadState('networkidle');
await page.screenshot({ path: `${screenshotDir}/blood-bank.png`, fullPage: true });
assert.match(await page.locator('body').innerText(), /recalled|transferred|available/i);

await browser.close();
console.log('Phase 7A blood-bank smoke passed');


