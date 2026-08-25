import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';

const baseURL = process.env.CLEANUP_B2_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.CLEANUP_B2_SCREENSHOT_DIR || 'storage/app/backend-ui-cleanup-b2';
const data = JSON.parse(await readFile(process.env.CLEANUP_B2_CONTEXT || 'storage/app/backend-ui-cleanup-b2/context.json', 'utf8'));
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

    for (const path of ['/admin/inventory/catalogue', '/admin/inventory/stock', '/admin/inventory/transfers', '/admin/inventory/adjustments', '/admin/inventory/reports', '/admin/pharmacy/prescriptions', '/admin/procurement']) {
        await visit(page, path);
    }

    await visit(page, '/admin/inventory/catalogue');
    await openAndClose(page, 'Add Item', /Add Item|Create item/i);
    await openAndClose(page, 'Add Location', /Add Location|Create location/i);

    await visit(page, '/admin/inventory/stock');
    await openAndClose(page, 'Receive Batch', /Receive Batch|Batch/i);
    await page.screenshot({ path: `${screenshotDir}/inventory-${viewport.width}.png`, fullPage: true });

    await visit(page, '/admin/inventory/transfers');
    await openAndClose(page, 'Request Transfer', /Request Transfer|Quantity/i);

    await visit(page, '/admin/pharmacy/prescriptions');
    await openAndClose(page, 'Draft Prescription', /Draft Prescription|Add medicine/i);
    const prescriptionLink = page.locator('a[href^="/admin/pharmacy/prescriptions/"]').first();
    if (await prescriptionLink.count()) {
        await prescriptionLink.click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForTimeout(250);
        await assertNoHorizontalOverflow(page);
        await openAndClose(page, 'Pharmacist Review', /Pharmacist Review|Record review/i);
        await openAndClose(page, 'Dispense', /Dispense|Quantity/i);
        await page.screenshot({ path: `${screenshotDir}/pharmacy-${viewport.width}.png`, fullPage: true });
    }

    await visit(page, '/admin/procurement');
    await openAndClose(page, 'Add Supplier', /Add Supplier|Save supplier/i);
    await openAndClose(page, 'Draft Requisition', /Draft Requisition|Add line/i);
    await openAndClose(page, 'Partial receipt', /Goods Receipt|Record receipt/i);
    await page.screenshot({ path: `${screenshotDir}/procurement-${viewport.width}.png`, fullPage: true });

    await context.close();
}

await browser.close();
console.log('Backend UI Cleanup B2 smoke passed');
