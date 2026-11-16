// Screenshots and rendered-page checks for the design system (PB-51).
//
//   1. DB_DATABASE=shariah_p2p_screenshots php artisan migrate:fresh --seed --seeder=ScreenshotSeeder
//   2. DB_DATABASE=shariah_p2p_screenshots php artisan serve --port=8010
//   3. npm run build && npm run screenshots            (from web/)
//
// Signs in as each demo account in headless Chrome (puppeteer-core drives the installed
// Chrome), then for every page, at 1440, 820 and 390 px, in conventional and Shariah mode:
//   - saves a full-page screenshot to docs/report-notes/screenshots/design-system/
//   - fails on a console error, an uncaught exception, or a horizontal scrollbar
//   - runs axe-core's WCAG 2.2 A/AA rules (contrast of the real rendered text included)
// It also flips the investor's real top-bar switch once, to check the PATCH round trip.
// Exits with code 1 if any check fails.
import { mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';

const require = createRequire(import.meta.url);
const BASE = process.env.SCREENSHOT_URL ?? 'http://127.0.0.1:8010';
const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const OUT = fileURLToPath(new URL('../../docs/report-notes/screenshots/design-system/', import.meta.url));
const PASSWORD = 'Demo#2026';
const WIDTHS = [1440, 820, 390];
const MODES = ['conventional', 'shariah'];
const ONLY = process.argv.slice(2); // optional page names to limit the run

const ACCOUNTS = {
    guest: null,
    borrower: 'borrower@example.com',
    investor: 'investor@example.com',
    newInvestor: 'new-investor@example.com',
    admin: 'admin@example.com',
};

const PAGES = [
    ['guest', 'welcome', '/'],
    ['guest', 'login', '/login'],
    ['guest', 'register', '/register'],
    ['guest', 'forgot-password', '/forgot-password'],
    ['guest', 'reset-password', '/reset-password/sample-token?email=aishath%40example.com'],
    ['borrower', 'borrower-portal', '/borrower'],
    ['borrower', 'borrower-profile', '/borrower/profile'],
    ['borrower', 'borrower-documents', '/borrower/documents'],
    ['borrower', 'change-password', '/account/password'],
    ['investor', 'investor-portal', '/investor'],
    ['investor', 'investor-profile', '/investor/profile'],
    ['investor', 'investor-documents', '/investor/documents'],
    ['investor', 'investor-acknowledgement', '/investor/acknowledgement'],
    ['newInvestor', 'investor-portal-no-profile', '/investor'],
    ['admin', 'admin-portal', '/admin'],
    ['admin', 'admin-queue', '/admin/verifications'],
    ['admin', 'admin-review', null], // the first queue row's review page, found at run time
    ['guest', 'design-preview', '/dev/design'],
];

const failures = [];
const fail = (where, message) => {
    failures.push(`${where}: ${message}`);
    console.log(`  FAIL ${where}: ${message}`);
};

async function signIn(browser, email) {
    const context = await browser.createBrowserContext();
    if (!email) return context;

    const page = await context.newPage();
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.type('#email', email);
    await page.type('#password', PASSWORD);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type="submit"]')]);
    if (page.url().endsWith('/login')) throw new Error(`Could not sign in as ${email}`);
    await page.close();
    return context;
}

async function runAxe(page, where) {
    await page.addScriptTag({ path: require.resolve('axe-core') });
    const result = await page.evaluate(async () => {
        // eslint-disable-next-line no-undef
        const r = await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
        return r.violations.map((v) => ({ id: v.id, help: v.help, nodes: v.nodes.slice(0, 3).map((n) => `${n.target.join(' ')} ${n.any[0]?.message ?? ''}`) }));
    });
    for (const violation of result) {
        fail(where, `axe ${violation.id}: ${violation.help}\n      ${violation.nodes.join('\n      ')}`);
    }
}

async function capture(context, name, path) {
    const page = await context.newPage();
    const errors = [];
    page.on('console', (message) => message.type() === 'error' && errors.push(message.text()));
    page.on('pageerror', (error) => errors.push(error.message));

    for (const width of WIDTHS) {
        await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
        await page.goto(`${BASE}${path}`, { waitUntil: 'networkidle0' });
        await page.evaluate(() => document.fonts.ready);

        for (const mode of MODES) {
            const where = `${name} ${width} ${mode}`;
            await page.evaluate((m) => {
                document.documentElement.dataset.mode = m;
            }, mode);
            await new Promise((resolve) => setTimeout(resolve, 650)); // let the 500 ms cross-fade finish

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            if (overflow > 0) fail(where, `scrolls sideways by ${overflow}px`);

            if (width === 1440 || width === 390) await runAxe(page, where);

            await page.screenshot({ path: `${OUT}${name}-${width}-${mode}.png`, fullPage: true });
            console.log(`  ok   ${where}`);
        }
    }

    for (const error of errors) fail(name, `console: ${error}`);
    await page.close();
}

/** Flip the investor's real top-bar switch: palette changes at once, the PATCH saves it. */
async function checkModeSwitch(context) {
    const page = await context.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    await page.goto(`${BASE}/investor`, { waitUntil: 'networkidle0' });
    const before = await page.evaluate(() => document.documentElement.dataset.mode);
    const patch = page.waitForResponse((r) => r.url().endsWith('/investor/preferences/shariah-only') && r.request().method() === 'PATCH');
    await page.click('header [role="switch"]');
    const immediately = await page.evaluate(() => document.documentElement.dataset.mode);
    const response = await patch;
    const toast = await page
        .waitForFunction(() => document.body.innerText.includes('Shariah-only mode is on.'), { timeout: 3000 })
        .then(() => true, () => false);
    await page.reload({ waitUntil: 'networkidle0' });
    const afterReload = await page.evaluate(() => document.documentElement.dataset.mode);

    const ok = before === 'conventional' && immediately === 'shariah' && response.status() < 400 && toast && afterReload === 'shariah';
    console.log(`  ${ok ? 'ok  ' : 'FAIL'} mode switch: ${before} → ${immediately} at once, PATCH ${response.status()}, ${afterReload} after reload, toast seen: ${toast}`);
    if (!ok) fail('mode switch', 'did not switch and persist');

    // Put it back so the screenshots start conventional.
    await page.click('header [role="switch"]');
    await page.waitForNetworkIdle();
    await page.close();
}

/** One extra shot of the keyboard focus ring on a field and a button. */
async function captureFocus(context) {
    const page = await context.newPage();
    await page.setViewport({ width: 820, height: 900 });
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.keyboard.press('Tab'); // skip to the first control
    await page.focus('#email');
    await page.screenshot({ path: `${OUT}focus-input.png` });
    await page.focus('button[type="submit"]');
    await page.keyboard.press('Shift');
    await page.evaluate(() => document.querySelector('button[type="submit"]').focus({ focusVisible: true }));
    await page.screenshot({ path: `${OUT}focus-button.png` });
    await page.close();
}

mkdirSync(OUT, { recursive: true });
const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
const contexts = {};

try {
    for (const [account, email] of Object.entries(ACCOUNTS)) {
        contexts[account] = await signIn(browser, email);
    }

    if (ONLY.length === 0 || ONLY.includes('mode-switch')) await checkModeSwitch(contexts.investor);
    if (ONLY.length === 0 || ONLY.includes('focus')) await captureFocus(contexts.guest);

    for (const [account, name, path] of PAGES) {
        if (ONLY.length && !ONLY.includes(name)) continue;
        let url = path;
        if (!url) {
            const page = await contexts.admin.newPage();
            await page.goto(`${BASE}/admin/verifications`, { waitUntil: 'networkidle0' });
            url = new URL(await page.$eval('a[href*="/admin/verifications/"]', (a) => a.href)).pathname;
            await page.close();
        }
        console.log(name);
        await capture(contexts[account], name, url);
    }
} finally {
    await browser.close();
}

console.log(failures.length ? `\n${failures.length} problem(s).` : '\nAll pages passed.');
process.exit(failures.length ? 1 : 0);
