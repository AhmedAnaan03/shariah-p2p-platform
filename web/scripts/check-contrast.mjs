// WCAG 2.2 AA contrast check for the design-system tokens, in all four themes.
//
//   node scripts/check-contrast.mjs            (from web/)
//
// Reads resources/css/tailwind-theme.css, resolves each theme's colour tokens (following
// var() references and the override order the CSS uses), and checks every text/background
// and focus-ring/background pair the components use. Exits with code 1 if a pair fails, so
// it can run in CI. Colours with an alpha channel are composited onto the background first.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const css = readFileSync(fileURLToPath(new URL('../resources/css/tailwind-theme.css', import.meta.url)), 'utf8');

/** All `--color-x: value;` declarations inside the first block that follows `selector`. */
function block(selector) {
    const start = css.indexOf(selector);
    const open = css.indexOf('{', start);
    const close = css.indexOf('}', open);
    const body = css.slice(open + 1, close);
    return Object.fromEntries([...body.matchAll(/--(color-[\w-]+):\s*([^;]+);/g)].map((m) => [m[1], m[2].trim()]));
}

const base = block('@theme {');
const shariah = block('[data-mode="shariah"] {');
const dark = block('[data-theme="dark"], [data-theme="dark"] [data-mode="conventional"] {');
const shariahDark = block('[data-theme="dark"][data-mode="shariah"]');

// Same cascade as the CSS: shariah-dark inherits the dark values it doesn't override.
export const THEMES = {
    conventional: { ...base },
    shariah: { ...base, ...shariah },
    dark: { ...base, ...dark },
    'shariah-dark': { ...base, ...dark, ...shariahDark },
};

function resolve(theme, name, seen = new Set()) {
    const value = theme[name];
    if (value === undefined) throw new Error(`Unknown token ${name}`);
    const ref = value.match(/^var\(--(color-[\w-]+)\)$/);
    if (!ref) return value;
    if (seen.has(name)) throw new Error(`Cycle at ${name}`);
    seen.add(name);
    return resolve(theme, ref[1], seen);
}

function parseHex(hex) {
    const h = hex.replace('#', '');
    const n = h.length === 3 || h.length === 4 ? [...h].map((c) => c + c).join('') : h;
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(n.slice(i, i + 2), 16));
    const a = n.length === 8 ? parseInt(n.slice(6, 8), 16) / 255 : 1;
    return { r, g, b, a };
}

function composite(fg, bg) {
    const mix = (f, b) => Math.round(f * fg.a + b * (1 - fg.a));
    return { r: mix(fg.r, bg.r), g: mix(fg.g, bg.g), b: mix(fg.b, bg.b), a: 1 };
}

function luminance({ r, g, b }) {
    const lin = (c) => {
        const s = c / 255;
        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
}

export function ratio(fgHex, bgHex, pageHex = '#ffffff') {
    // A translucent background sits on the page; a translucent foreground sits on that.
    const bg = composite(parseHex(bgHex), parseHex(pageHex));
    const fg = composite(parseHex(fgHex), bg);
    const [l1, l2] = [luminance(fg), luminance(bg)].sort((a, b) => b - a);
    return (l1 + 0.05) / (l2 + 0.05);
}

const TEXT = 4.5;
const NON_TEXT = 3;
const SURFACES = ['bg', 'surface', 'surface-raised', 'surface-subtle', 'band'];

// [foreground, backgrounds, minimum, what it is]
const PAIRS = [
    ['text', SURFACES, TEXT, 'primary text'],
    ['text-secondary', SURFACES, TEXT, 'values, sidebar items'],
    ['text-muted', SURFACES, TEXT, 'helper text, labels'],
    ['link', ['bg', 'surface', 'surface-subtle', 'band'], TEXT, 'links'],
    ['primary-fg', ['primary', 'primary-hover', 'primary-pressed'], TEXT, 'primary button label'],
    ['primary-ink', ['primary-subtle', 'primary-muted'], TEXT, 'text on tinted areas'],
    ['nav-active-ink', ['nav-active'], TEXT, 'active sidebar item', 'band'],
    ['text-secondary', ['band-hover'], TEXT, 'hovered sidebar item', 'band'],
    ['primary', ['nav-active'], NON_TEXT, 'active nav indicator and bold icon', 'band'],
    ['chart-axis', ['surface'], TEXT, 'chart ticks'],
    ...['success', 'warning', 'danger', 'info', 'neutral'].flatMap((tone) => [
        [tone, [`${tone}-bg`], TEXT, `${tone} badge/alert text`],
        [tone, ['surface'], TEXT, `${tone} text on white (field errors, counts)`],
    ]),
    ['danger', ['surface-subtle'], TEXT, 'error text in a table row'],
    ...['a', 'b', 'c', 'd', 'e'].flatMap((g) => [
        [`risk-${g}-ink`, [`risk-${g}-soft`], TEXT, `grade ${g.toUpperCase()} tinted chip`],
        ['surface', [`risk-${g}-ink`], TEXT, `grade ${g.toUpperCase()} solid chip`],
    ]),
    // Pairings the components add (PB-51).
    ['text', ['primary-subtle'], TEXT, 'selected radio card title'],
    ['text-secondary', ['primary-subtle'], TEXT, 'selected radio card description'],
    ['primary', ['primary-subtle'], NON_TEXT, 'selected radio card border and icon'],
    ...['success', 'warning', 'danger', 'info'].flatMap((tone) => [
        ['text', [`${tone}-bg`], TEXT, `${tone} alert title`],
        ['text-secondary', [`${tone}-bg`], TEXT, `${tone} alert body`],
    ]),
    ['primary-fg', ['danger'], TEXT, 'danger button label'],
    ['surface', ['text'], TEXT, 'tooltip (inverted)'],
    ['border-control', ['surface'], NON_TEXT, 'switch track when off'],
    ['surface', ['primary'], NON_TEXT, 'switch thumb when on'],
    ['primary-ink', ['primary-muted'], TEXT, 'avatar initials'],
    ['danger', ['surface'], NON_TEXT, 'unread count dot'],
    ['focus-ring', [...SURFACES, 'primary-subtle'], NON_TEXT, 'focus ring'],
    ['border-control', ['surface', 'surface-subtle'], NON_TEXT, 'input, checkbox, switch outline'],
    ['primary', ['surface', 'surface-subtle'], NON_TEXT, 'checked controls, progress'],
    ['icon', ['surface', 'bg', 'band'], NON_TEXT, 'icons at rest'],
];

// Text on a patterned band sits over pattern lines: the band with color-pattern laid on top at
// the pattern's opacity (--opacity-pattern, --opacity-pattern-dark in the theme). Checked against
// that worst pixel, not the plain band.
const PATTERNED = [
    ['text', TEXT, 'heading on a patterned band'],
    ['text-secondary', TEXT, 'body and labels on a patterned band'],
    ['link', TEXT, 'link on a patterned band'],
    ['primary-ink', TEXT, 'eyebrow on a page band'],
    ['focus-ring', NON_TEXT, 'focus ring on a patterned band'],
];
// Pairs the components must never use. Printed for the record, not counted as failures.
const BANNED = [['text-muted', 'text-muted on a patterned band: use text-secondary']];

const opacityToken = (name) => Number(css.match(new RegExp(`--${name}:\\s*([0-9.]+)`))[1]);
const PATTERN_OPACITY = { light: opacityToken('opacity-pattern'), dark: opacityToken('opacity-pattern-dark') };

function patternedBand(themeName, theme) {
    const strength = themeName.includes('dark') ? PATTERN_OPACITY.dark : PATTERN_OPACITY.light;
    const ink = parseHex(resolve(theme, 'color-pattern'));
    const band = parseHex(resolve(theme, 'color-band'));
    const mixed = composite({ ...ink, a: strength }, band);
    return `#${[mixed.r, mixed.g, mixed.b].map((c) => c.toString(16).padStart(2, '0')).join('')}`;
}

let failures = 0;
const rows = [];

for (const [themeName, theme] of Object.entries(THEMES)) {
    const page = resolve(theme, 'color-bg');
    for (const [fg, bgs, min, what, under] of PAIRS) {
        for (const bg of bgs) {
            const r = ratio(resolve(theme, `color-${fg}`), resolve(theme, `color-${bg}`), under ? resolve(theme, `color-${under}`) : page);
            const ok = r >= min;
            if (!ok) failures++;
            rows.push({ theme: themeName, pair: `${fg} on ${bg}`, ratio: r.toFixed(2), min, ok, what });
        }
    }

    const patterned = patternedBand(themeName, theme);
    for (const [fg, min, what] of PATTERNED) {
        const r = ratio(resolve(theme, `color-${fg}`), patterned);
        const ok = r >= min;
        if (!ok) failures++;
        rows.push({ theme: themeName, pair: `${fg} on band+pattern`, ratio: r.toFixed(2), min, ok, what });
    }
    for (const [fg, what] of BANNED) {
        const r = ratio(resolve(theme, `color-${fg}`), patterned);
        rows.push({ theme: themeName, pair: `${fg} on band+pattern`, ratio: r.toFixed(2), min: TEXT, ok: true, banned: r < TEXT, what });
    }
}

const onlyFailures = process.argv.includes('--failures');
for (const row of rows) {
    if (onlyFailures && row.ok && !row.banned) continue;
    const status = row.banned ? 'avoid' : row.ok ? 'pass' : 'FAIL';
    console.log(`${status.padEnd(5)} ${row.theme.padEnd(13)} ${row.pair.padEnd(36)} ${row.ratio.padStart(5)} (min ${row.min})  ${row.what}`);
}
console.log(`\n${rows.length} pairs checked in 4 themes, ${failures} below WCAG AA.`);
process.exit(failures ? 1 : 0);
