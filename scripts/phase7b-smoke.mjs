import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';

const baseURL = process.env.PHASE7B_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.PHASE7B_SCREENSHOT_DIR || 'storage/app/phase7b-smoke';
const data = JSON.parse(await readFile(process.env.PHASE7B_CONTEXT || 'storage/app/phase7b-smoke/context.json', 'utf8'));

await mkdir(screenshotDir, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();

await page.goto('/login');
await page.locator('#email').fill(data.auth_email);
await page.locator('#password').fill(data.password);
await page.getByRole('button', { name: 'Login' }).click();
await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));

await page.goto('/admin/blood-bank');
await page.waitForLoadState('networkidle');
assert.match(await page.locator('body').innerText(), /Patient Blood Request|Request Worklist/i);

execFileSync('php', ['scripts/phase7b-smoke-actions.php', String(data.request_id), data.tech_email, data.auth_email, ...data.component_ids.map(String)], { stdio: 'inherit' });

await page.goto(data.request_url);
await page.waitForLoadState('networkidle');
const body = await page.locator('body').innerText();
assert.match(body, /Manual Compatibility Tests|Reservations and Issues/i);
assert.match(body, /returned|reversed|Blood Requests/i);
await page.screenshot({ path: `${screenshotDir}/blood-request.png`, fullPage: true });

await browser.close();
console.log('Phase 7B patient blood request smoke passed');
