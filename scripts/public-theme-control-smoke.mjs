import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';

const baseURL = process.env.PUBLIC_THEME_BASE_URL || 'http://127.0.0.1:8000';
const chromePath = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const backup = execFileSync('php', ['-r', `
$loader = require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
if (app()->isProduction()) {
    fwrite(STDERR, 'Refusing to modify public theme state in production.'.PHP_EOL);
    exit(1);
}
$page = App\\Models\\PublicSitePage::where('slug', 'home')->firstOrFail();
echo json_encode($page->published_content);
`], { encoding: 'utf8' });

function setVisitorThemeSwitcher(enabled) {
    execFileSync('php', ['-r', `
$loader = require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
if (app()->isProduction()) {
    fwrite(STDERR, 'Refusing to modify public theme state in production.'.PHP_EOL);
    exit(1);
}
$page = App\\Models\\PublicSitePage::where('slug', 'home')->firstOrFail();
$content = $page->published_content ?: [];
$content['theme'] = [
    'appearance' => 'system',
    'accent' => 'seagrass',
    'allowed_accents' => ['calm', 'healing', 'seagrass'],
    'show_switcher' => ${enabled ? 'true' : 'false'},
];
$page->forceFill(['published_content' => $content])->save();
`], { stdio: 'inherit' });
}

function restoreTheme() {
    execFileSync('php', ['-r', `
$loader = require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
if (app()->isProduction()) {
    fwrite(STDERR, 'Refusing to modify public theme state in production.'.PHP_EOL);
    exit(1);
}
$page = App\\Models\\PublicSitePage::where('slug', 'home')->firstOrFail();
$page->forceFill(['published_content' => json_decode(base64_decode('${Buffer.from(backup).toString('base64')}'), true)])->save();
`], { stdio: 'inherit' });
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
    setVisitorThemeSwitcher(false);

    await page.goto('/');
    await page.evaluate(() => {
        window.localStorage.setItem('public-theme-preference', JSON.stringify({ appearance: 'dark', accent: 'blood' }));
    });
    await page.reload({ waitUntil: 'networkidle' });

    assert.equal(await page.evaluate(() => document.documentElement.dataset.publicAppearancePreference), 'dark');
    assert.equal(await page.evaluate(() => document.documentElement.dataset.publicAccent), 'seagrass');

    await page.getByRole('button', { name: 'Change appearance mode' }).click();
    assert.equal(await page.getByText('Appearance').first().isVisible(), true);
    assert.equal(await page.getByText('Accent').count(), 0);
    assert.equal(await page.getByText('Seagrass').count(), 0);

    setVisitorThemeSwitcher(true);
    await page.reload({ waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Open theme settings' }).click();
    assert.equal(await page.getByText('Accent').first().isVisible(), true);

    const firstPartyFailures = failedRequests.filter((entry) => entry.includes(baseURL));
    assert.deepEqual(consoleErrors.filter((entry) => !/403 \\(Forbidden\\)/.test(entry)), [], 'browser console errors');
    assert.deepEqual(pageErrors, [], 'browser page errors');
    assert.deepEqual(firstPartyFailures, [], 'failed first-party requests');

    console.log('Public theme control smoke passed');
} finally {
    await browser.close();
    restoreTheme();
}
