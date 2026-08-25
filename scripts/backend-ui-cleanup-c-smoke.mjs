import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { mkdir, readFile } from 'node:fs/promises';

const baseURL = process.env.CLEANUP_C_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const screenshotDir = process.env.CLEANUP_C_SCREENSHOT_DIR || 'storage/app/backend-ui-cleanup-c';
const data = JSON.parse(await readFile(process.env.CLEANUP_C_CONTEXT || 'storage/app/backend-ui-cleanup-c/context.json', 'utf8'));
const requestedWidths = (process.env.CLEANUP_C_WIDTHS || '').split(',').map((entry) => Number(entry.trim())).filter(Boolean);
const viewports = [
    { width: 320, height: 700 },
    { width: 375, height: 760 },
    { width: 768, height: 900 },
    { width: 1024, height: 640 },
    { width: 1440, height: 900 },
].filter((viewport) => requestedWidths.length === 0 || requestedWidths.includes(viewport.width));

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

async function assertNoConsoleErrors(page, errors) {
    const failedRequests = await page.evaluate(() => performance.getEntriesByType('resource').filter((entry) => entry.responseStatus >= 400).map((entry) => entry.name));
    assert.equal(errors.length, 0, `Console errors: ${errors.join('\n')}`);
    assert.equal(failedRequests.length, 0, `Failed resource requests: ${failedRequests.join('\n')}`);
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

async function assertValidationStaysInModal(page, buttonName) {
    await page.getByRole('button', { name: buttonName }).first().click();
    await page.getByRole('dialog').waitFor();
    await page.getByRole('button', { name: /Save|Collect|Record|Update|Issue|Reserve|Authorize/ }).last().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);
    await page.getByRole('dialog').waitFor();
    await page.keyboard.press('Escape');
}

for (const viewport of viewports) {
    const context = await browser.newContext({ baseURL, viewport });
    const page = await context.newPage();
    const errors = [];
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => errors.push(error.message));

    await login(page);

    for (const path of ['/admin/inpatient', `/admin/inpatient/charts/${data.chart_id}`, '/admin/emar', `/admin/emar/charts/${data.chart_id}`, '/admin/blood-bank', `/admin/blood-bank/donors/${data.donor_id}`, `/admin/blood-bank/donations/${data.donation_id}`, `/admin/blood-bank/requests/${data.blood_request_id}`]) {
        await visit(page, path);
    }

    await visit(page, '/admin/inpatient');
    await page.screenshot({ path: `${screenshotDir}/inpatient-worklist-${viewport.width}.png`, fullPage: true });

    await visit(page, `/admin/inpatient/charts/${data.chart_id}`);
    await openAndClose(page, 'Progress Note', /Clinician Progress Note/i);
    await openAndClose(page, 'Observation', /Observation Chart/i);
    await openAndClose(page, 'Discharge Summary', /Discharge Summary/i);
    await assertValidationStaysInModal(page, 'Nursing Note');
    await page.screenshot({ path: `${screenshotDir}/inpatient-chart-${viewport.width}.png`, fullPage: true });

    await visit(page, '/admin/emar');
    await page.screenshot({ path: `${screenshotDir}/emar-worklist-${viewport.width}.png`, fullPage: true });

    await visit(page, `/admin/emar/charts/${data.chart_id}`);
    await openAndClose(page, 'Record', /Medication Administration/i);
    await page.screenshot({ path: `${screenshotDir}/emar-chart-${viewport.width}.png`, fullPage: true });

    await visit(page, '/admin/blood-bank');
    await openAndClose(page, 'Create Request', /Patient Blood Request/i);
    await openAndClose(page, 'Add Donor', /Register Donor/i);
    await page.screenshot({ path: `${screenshotDir}/blood-bank-${viewport.width}.png`, fullPage: true });

    await visit(page, `/admin/blood-bank/donors/${data.donor_id}`);
    await openAndClose(page, 'Screening Decision', /Manual Screening Decision/i);

    await visit(page, `/admin/blood-bank/donations/${data.donation_id}`);
    await openAndClose(page, 'Blood Group', /Blood Group/i);
    await openAndClose(page, 'Screening Result', /Screening Result/i);
    await openAndClose(page, 'Prepare Component', /Prepare Component/i);
    await page.screenshot({ path: `${screenshotDir}/blood-donation-${viewport.width}.png`, fullPage: true });

    await visit(page, `/admin/blood-bank/requests/${data.blood_request_id}`);
    await openAndClose(page, 'Collect Specimen', /Collect Specimen/i);
    await openAndClose(page, 'Enter ABO/Rh', /Enter Patient ABO\/Rh/i);
    await openAndClose(page, 'Crossmatch', /Manual Crossmatch/i);
    await openAndClose(page, 'Reserve', /Reserve Component/i);
    await openAndClose(page, 'Issue', /Issue Component/i);
    await openAndClose(page, 'Emergency Release', /Emergency Release/i);
    await assertValidationStaysInModal(page, 'Request State');
    await page.screenshot({ path: `${screenshotDir}/blood-request-${viewport.width}.png`, fullPage: true });

    await assertNoConsoleErrors(page, errors);
    await context.close();
}

await browser.close();
console.log('Backend UI Cleanup C smoke passed');
