import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';

const baseURL = process.env.CLEANUP_A_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.CLEANUP_A_SCREENSHOT_DIR || 'storage/app/backend-ui-cleanup-a';
const data = JSON.parse(await readFile(process.env.CLEANUP_A_CONTEXT || 'storage/app/backend-ui-cleanup-a/context.json', 'utf8'));
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

for (const viewport of viewports) {
    const context = await browser.newContext({ baseURL, viewport });
    const page = await context.newPage();
    await login(page);

    for (const path of ['/admin/facilities', '/admin/departments', '/admin/staff', '/admin/patients', '/admin/appointments', '/admin/admissions']) {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await assertNoHorizontalOverflow(page);
    }

    await page.goto('/admin/facilities');
    await page.getByRole('button', { name: 'Add Facility' }).click();
    await page.getByRole('button', { name: 'Create facility' }).click();
    await page.getByRole('dialog').waitFor();
    assert.match(await page.getByRole('dialog').innerText(), /Add Facility|Name|Code/i);
    await page.keyboard.press('Escape');

    await page.goto('/admin/patients');
    await page.getByRole('button', { name: 'Register Patient' }).click();
    await page.getByRole('dialog').waitFor();
    await page.getByRole('dialog').getByRole('button', { name: 'Register patient', exact: true }).click();
    assert.match(await page.getByRole('dialog').innerText(), /Register Patient|First name/i);
    await page.screenshot({ path: `${screenshotDir}/patients-${viewport.width}.png`, fullPage: true });

    await page.goto('/admin/admissions');
    await page.getByRole('button', { name: 'Request Admission' }).click();
    await page.getByRole('dialog').waitFor();
    assert.match(await page.getByRole('dialog').innerText(), /Admission Request/i);
    await page.keyboard.press('Escape');
    await page.getByRole('button', { name: 'Approve' }).first().click();
    await page.getByRole('dialog').waitFor();
    assert.match(await page.getByRole('dialog').innerText(), /approve admission/i);
    await page.screenshot({ path: `${screenshotDir}/admissions-${viewport.width}.png`, fullPage: true });
    await page.keyboard.press('Escape');
    await page.waitForTimeout(150);

    if (viewport.width < 1024) {
        await page.getByRole('button', { name: 'Open sidebar' }).click();
        const navScroll = await page.locator('nav[aria-label="Admin navigation"]').evaluate((node) => node.scrollHeight > node.clientHeight);
        assert.equal(navScroll, true);
        await page.getByRole('link', { name: /Patients/ }).click();
        await page.waitForURL(/\/admin\/patients/);
    }

    await context.close();
}

await browser.close();
console.log('Backend UI Cleanup A smoke passed');
