import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';

const baseURL = process.env.CLEANUP_B1_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.CLEANUP_B1_SCREENSHOT_DIR || 'storage/app/backend-ui-cleanup-b1';
const data = JSON.parse(await readFile(process.env.CLEANUP_B1_CONTEXT || 'storage/app/backend-ui-cleanup-b1/context.json', 'utf8'));
const viewports = [
    { width: 320, height: 700 },
    { width: 375, height: 760 },
    { width: 768, height: 900 },
    { width: 1024, height: 640 },
    { width: 1440, height: 900 },
];

await mkdir(screenshotDir, { recursive: true });
const browser = await chromium.launch({ executablePath: chromePath, headless: true });

async function login(page) {
    await page.goto('/login');
    await page.locator('#email').fill(data.email);
    await page.locator('#password').fill(data.password);
    await page.getByRole('button', { name: 'Login' }).click();
    await page.waitForURL((url) => url.pathname === '/dashboard' || url.pathname.startsWith('/admin'));
}

async function assertNoHorizontalOverflow(page) {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    assert.equal(overflow, false);
}

async function visit(page, path) {
    await page.goto(path);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(250);
    await assertNoHorizontalOverflow(page);
}

async function openAndClose(page, buttonName, expectedText) {
    await page.getByRole('button', { name: buttonName }).first().click();
    await page.getByRole('dialog').waitFor();
    assert.match(await page.getByRole('dialog').innerText(), expectedText);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(100);
}

for (const viewport of viewports) {
    const context = await browser.newContext({ baseURL, viewport });
    const page = await context.newPage();
    await login(page);

    for (const path of ['/admin/clinical/worklist', '/admin/billing/catalogue', '/admin/billing/invoices', '/admin/payments/workbench', '/admin/payments/accounting', '/admin/laboratory/catalogue', '/admin/laboratory/requests', '/admin/radiology/catalogue', '/admin/radiology/requests']) {
        await visit(page, path);
    }

    await visit(page, '/admin/billing/catalogue');
    await openAndClose(page, 'Add Service', /Add Service|Create service/i);

    await visit(page, '/admin/billing/invoices');
    await openAndClose(page, 'Create Draft Invoice', /Create Draft Invoice|Create draft/i);

    await visit(page, '/admin/payments/workbench');
    await openAndClose(page, 'Post Payment', /Post Payment|Invoice allocation/i);
    await page.screenshot({ path: `${screenshotDir}/payments-${viewport.width}.png`, fullPage: true });

    await visit(page, '/admin/laboratory/requests');
    await openAndClose(page, 'Order Lab Request', /Order Lab Request|Clinical notes/i);
    await page.screenshot({ path: `${screenshotDir}/laboratory-${viewport.width}.png`, fullPage: true });

    await visit(page, '/admin/radiology/requests');
    await openAndClose(page, 'Order Radiology Request', /Order Radiology Request|Clinical indication/i);
    await page.screenshot({ path: `${screenshotDir}/radiology-${viewport.width}.png`, fullPage: true });

    await visit(page, '/admin/clinical/worklist');
    await assertNoHorizontalOverflow(page);

    await context.close();
}

await browser.close();
console.log('Backend UI Cleanup B1 smoke passed');
