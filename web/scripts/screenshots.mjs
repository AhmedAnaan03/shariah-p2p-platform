// Rendered-page checks and report screenshots (PB-51; four themes since PB-55; axe-only mode
// for the testing and evidence policy, see CLAUDE.md and web/README.md).
//
//   1. DB_DATABASE=shariah_p2p_screenshots php artisan migrate:fresh --seed --seeder=ScreenshotSeeder
//   2. DB_DATABASE=shariah_p2p_screenshots php artisan serve --port=8010
//   3. npm run build, then one of (from web/):
//
//      npm run check:a11y -- onboarding-borrower-profile admin-queue
//          Axe-only, for an item that changed pages or styling: only the named pages, light
//          conventional, no screenshots saved. Quick.
//
//      npm run screenshots -- --out=/path/outside/the/repo
//          Full mode, once in Sprint 5 (PB-56): every page at 1440, 820 and 390 px in all four
//          themes, with full-page screenshots, the mode-switch and theme-toggle round trips and
//          the focus-ring shots.
//
// Options (both modes):
//   <page names>      limit the run to these pages (required with --axe-only; see PAGES below)
//   --theme=<list>    comma-separated: conventional, shariah, conventional-dark, shariah-dark
//                     (default: conventional with --axe-only, all four otherwise)
//   --out=<dir>       where screenshots go (default docs/report-notes/screenshots/design-system/,
//                     git-ignored); Sprint 5 report screenshots are saved outside the repo
//
// For every page and width it fails on a console error, an uncaught exception, a horizontal
// scrollbar, text squeezed to one letter per line or the funding ring overlapping its figures
// (checkLayout), and at 1440 and 390 px runs axe-core's WCAG 2.2 A/AA rules (contrast of the real
// rendered text included). Exits with code 1 if any check fails.
import { mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer-core';

const require = createRequire(import.meta.url);
const BASE = process.env.SCREENSHOT_URL ?? 'http://127.0.0.1:8010';
const CHROME = process.env.CHROME_PATH ?? '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PASSWORD = 'Demo#2026';
const WIDTHS = [1440, 820, 390];

// Every palette the design system defines: name → [data-mode, data-theme].
const ALL_THEMES = {
    conventional: ['conventional', 'light'],
    shariah: ['shariah', 'light'],
    'conventional-dark': ['conventional', 'dark'],
    'shariah-dark': ['shariah', 'dark'],
};

const args = process.argv.slice(2);
const option = (name) => args.find((arg) => arg.startsWith(`--${name}=`))?.split('=').slice(1).join('=');
const AXE_ONLY = args.includes('--axe-only');
const ONLY = args.filter((arg) => !arg.startsWith('--')); // page names to limit the run
const OUT = option('out') ? `${resolve(option('out'))}/` : fileURLToPath(new URL('../../docs/report-notes/screenshots/design-system/', import.meta.url));

const themeNames = (option('theme') ?? (AXE_ONLY ? 'conventional' : Object.keys(ALL_THEMES).join(','))).split(',').map((name) => name.trim());
for (const name of themeNames) {
    if (!ALL_THEMES[name]) {
        console.error(`Unknown theme "${name}". Use: ${Object.keys(ALL_THEMES).join(', ')}.`);
        process.exit(2);
    }
}
// [data-mode, data-theme, label used in file names and messages]
const THEMES = themeNames.map((name) => [...ALL_THEMES[name], name]);

const ACCOUNTS = {
    guest: null,
    borrower: 'borrower@example.com',
    investor: 'investor@example.com',
    newInvestor: 'new-investor@example.com',
    onboardingInvestor: 'onboarding-investor@example.com',
    readyBorrower: 'ready-borrower@example.com',
    verifiedBorrower: 'verified-borrower@example.com',
    admin: 'admin@example.com',
};

const PAGES = [
    ['guest', 'welcome', '/'],
    ['guest', 'login', '/login'],
    ['guest', 'register', '/register'],
    ['guest', 'forgot-password', '/forgot-password'],
    ['guest', 'reset-password', '/reset-password/sample-token?email=aishath%40example.com'],
    ['borrower', 'borrower-portal', '/borrower'],
    // The onboarding wizard (PB-53), one page per step and state.
    ['borrower', 'onboarding-borrower-profile', '/borrower/onboarding/profile'],
    ['borrower', 'onboarding-borrower-address', '/borrower/onboarding/address'],
    ['readyBorrower', 'onboarding-borrower-finances', '/borrower/onboarding/finances'],
    ['readyBorrower', 'onboarding-borrower-documents', '/borrower/onboarding/documents'],
    ['readyBorrower', 'onboarding-borrower-review', '/borrower/onboarding/review'],
    ['newInvestor', 'onboarding-investor-profile', '/investor/onboarding/profile'],
    ['onboardingInvestor', 'onboarding-investor-documents', '/investor/onboarding/documents'],
    ['borrower', 'change-password', '/account/password'],
    ['investor', 'investor-portal', '/investor'],
    ['investor', 'onboarding-investor-risk-accepted', '/investor/onboarding/risk'],
    ['investor', 'onboarding-investor-review-verified', '/investor/onboarding/review'],
    ['newInvestor', 'investor-portal-no-profile', '/investor'],
    ['admin', 'admin-portal', '/admin'],
    ['admin', 'admin-queue', '/admin/verifications'],
    ['admin', 'admin-review', null], // the first queue row's review page, found at run time
    ['borrower', 'borrower-campaign-create-unverified', '/borrower/campaigns/create'],
    ['verifiedBorrower', 'borrower-campaigns', '/borrower/campaigns'],
    ['verifiedBorrower', 'borrower-campaign-create', '/borrower/campaigns/create'],
    ['verifiedBorrower', 'borrower-campaign-show', null],
    ['investor', 'marketplace', '/investor/campaigns'],
    ['investor', 'marketplace-campaign', null], // PB-17: the first card's campaign page
    ['admin', 'admin-campaigns', '/admin/campaigns'],
    ['admin', 'admin-campaign-review', null],
    ['guest', 'design-preview', '/dev/design'],
];

// Pages whose URL is the first matching link on another page, found at run time.
const FIRST_LINK = {
    'admin-review': ['/admin/verifications', 'a[href*="/admin/verifications/"]'],
    'admin-campaign-review': ['/admin/campaigns', 'a[href*="/admin/campaigns/"]'],
    'borrower-campaign-show': ['/borrower/campaigns', 'a[href*="/borrower/campaigns/"]:not([href$="/create"])'],
    'marketplace-campaign': ['/investor/campaigns', 'a[href*="/investor/campaigns/"]'],
};

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

/**
 * Layout checks the eye would catch (PB-17 fix): text squeezed into a column so narrow it runs
 * one letter per line, and the funding ring overlapping the figures beside it. Run on every
 * page at every width and theme.
 */
async function checkLayout(page, where) {
    const problems = await page.evaluate(() => {
        const found = [];
        for (const el of document.querySelectorAll('body *')) {
            const text = [...el.childNodes].filter((n) => n.nodeType === Node.TEXT_NODE).map((n) => n.textContent).join('').trim();
            if (text.length < 4) continue;
            const box = el.getBoundingClientRect();
            if (box.height < 4) continue; // not displayed, or screen-reader-only text (1 × 1 px)
            // Faded out on purpose (the collapsed sidebar's labels): nothing to see, nothing to overlap.
            if (!el.checkVisibility({ opacityProperty: true, visibilityProperty: true })) continue;
            const style = getComputedStyle(el);
            const size = parseFloat(style.fontSize);
            const line = parseFloat(style.lineHeight) || size * 1.4;
            if (box.width < 1) {
                // A column squeezed to nothing: its text spills over whatever is beside it.
                found.push(`text "${text.slice(0, 30)}" is in a box 0 px wide`);
            } else if (box.width < size * 1.5 && box.height > line * 2.5) {
                found.push(`vertical text "${text.slice(0, 30)}" (${Math.round(box.width)}×${Math.round(box.height)}px)`);
            }
        }
        for (const ring of document.querySelectorAll('[role="progressbar"][aria-label^="Funding:"]')) {
            const details = ring.parentElement.querySelector(':scope > [data-funding-details]');
            if (!details) continue; // a ring on its own
            const a = ring.getBoundingClientRect();
            const b = details.getBoundingClientRect();
            const overlap = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left)) * Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
            if (overlap > 0) found.push(`the funding ring overlaps its details by ${Math.round(overlap)}px²`);
            if (b.width < 120) found.push(`the funding details are only ${Math.round(b.width)}px wide`);
        }
        return found;
    });
    for (const problem of problems) fail(where, problem);
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

        for (const [mode, theme, label] of THEMES) {
            const where = `${name} ${width} ${label}`;
            await page.evaluate(
                (m, t) => {
                    document.documentElement.dataset.mode = m;
                    document.documentElement.dataset.theme = t;
                },
                mode,
                theme,
            );
            await new Promise((done) => setTimeout(done, 650)); // let the 500 ms cross-fade finish

            const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
            if (overflow > 0) fail(where, `scrolls sideways by ${overflow}px`);
            await checkLayout(page, where);

            if (width === 1440 || width === 390) await runAxe(page, where);

            if (!AXE_ONLY) await page.screenshot({ path: `${OUT}${name}-${width}-${label}.png`, fullPage: true });
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

/**
 * The sidebar's theme switch (PB-55, PB-57). The most recent change wins:
 *   1. device light, switch to dark → dark at once, saved, and still dark after a reload
 *      (the choice holds while the device keeps the setting it had);
 *   2. switch back to light (saved with the device on light), then the device turns dark →
 *      the next load is dark before any app code runs (the device changed after the choice).
 */
async function checkThemeToggle(context) {
    const page = await context.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    const theme = () => page.evaluate(() => document.documentElement.dataset.theme);
    const toggle = 'aside [role="switch"][aria-label="Dark theme"]';
    const saved = () => page.waitForResponse((r) => r.url().endsWith('/account/theme') && r.request().method() === 'PATCH');

    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
    await page.goto(`${BASE}/investor`, { waitUntil: 'networkidle0' });
    const start = await theme();

    let patch = saved();
    await page.click(toggle);
    const immediately = await theme();
    const response = await patch;
    await page.goto(`${BASE}/investor`, { waitUntil: 'domcontentloaded' });
    const kept = await theme();

    const ok1 = start === 'light' && immediately === 'dark' && response.status() < 400 && kept === 'dark';
    console.log(`  ${ok1 ? 'ok  ' : 'FAIL'} theme switch: ${start} → ${immediately} at once, PATCH ${response.status()}, ${kept} after reload on the same device setting`);
    if (!ok1) fail('theme switch', 'did not switch and persist');

    await page.waitForNetworkIdle();
    patch = saved();
    await page.click(toggle);
    await patch;
    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'dark' }]);
    await page.goto(`${BASE}/investor`, { waitUntil: 'domcontentloaded' });
    const followed = await theme();
    console.log(`  ${followed === 'dark' ? 'ok  ' : 'FAIL'} device changed to dark after the light choice: ${followed} at DOMContentLoaded`);
    if (followed !== 'dark') fail('theme switch', 'a later device change did not win on first paint');

    await page.emulateMediaFeatures([{ name: 'prefers-color-scheme', value: 'light' }]);
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

// Page names must be real (a typo would otherwise check nothing and "pass").
const NAMES = [...PAGES.map(([, name]) => name), 'mode-switch', 'theme-toggle', 'focus'];
const unknown = ONLY.filter((name) => !NAMES.includes(name));
if (unknown.length || (AXE_ONLY && ONLY.length === 0)) {
    console.error(unknown.length ? `Unknown page name(s): ${unknown.join(', ')}.` : 'Name the pages to check: npm run check:a11y -- <page names>.');
    console.error(`Pages: ${NAMES.join(', ')}`);
    process.exit(2);
}

if (!AXE_ONLY) mkdirSync(OUT, { recursive: true });
console.log(AXE_ONLY ? `Axe-only check, ${themeNames.join(', ')}, no screenshots saved.` : `Full run, ${themeNames.join(', ')}, screenshots in ${OUT}`);
const browser = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
const contexts = {};

// The round trips and focus shots belong to the full run; with --axe-only they run only if named.
const runExtra = (name) => (AXE_ONLY ? ONLY.includes(name) : ONLY.length === 0 || ONLY.includes(name));

try {
    // Sign in only the accounts the chosen pages and checks need.
    const needed = new Set(PAGES.filter(([, name]) => ONLY.length === 0 || ONLY.includes(name)).map(([account]) => account));
    if (runExtra('mode-switch') || runExtra('theme-toggle')) needed.add('investor');
    if (runExtra('focus')) needed.add('guest');
    for (const account of needed) {
        contexts[account] = await signIn(browser, ACCOUNTS[account]);
    }

    if (runExtra('mode-switch')) await checkModeSwitch(contexts.investor);
    if (runExtra('theme-toggle')) await checkThemeToggle(contexts.investor);
    if (runExtra('focus') && !AXE_ONLY) await captureFocus(contexts.guest);

    for (const [account, name, path] of PAGES) {
        if (ONLY.length && !ONLY.includes(name)) continue;
        let url = path;
        if (!url) {
            const [from, selector] = FIRST_LINK[name];
            const page = await contexts[account].newPage();
            await page.goto(`${BASE}${from}`, { waitUntil: 'networkidle0' });
            url = new URL(await page.$eval(selector, (a) => a.href)).pathname;
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
