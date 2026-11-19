/**
 * The light / dark / system theme in the browser (PB-55). Like the mode (lib/mode.js), it lives
 * on <html>: data-theme is what the CSS uses ("light" or "dark"), data-theme-preference is what
 * the user chose ("light", "dark" or "system"). The server writes both on the first paint
 * (app.blade.php); these helpers keep them right afterwards.
 */
export const THEMES = ['light', 'dark', 'system'];

export const DARK_QUERY = '(prefers-color-scheme: dark)';

/** A known preference, or "system". */
export function normaliseTheme(value) {
    return THEMES.includes(value) ? value : 'system';
}

/** The theme to show: the user's choice, or the device's for "system". */
export function resolveTheme(preference, prefersDark) {
    const choice = normaliseTheme(preference);
    if (choice === 'system') {
        return prefersDark ? 'dark' : 'light';
    }
    return choice;
}

function prefersDark(win = globalThis.window) {
    return Boolean(win?.matchMedia?.(DARK_QUERY).matches);
}

/** Put a preference on <html>. The palette cross-fades through the @property registrations. */
export function applyTheme(preference, doc = globalThis.document, win = globalThis.window) {
    if (!doc) {
        return;
    }
    const choice = normaliseTheme(preference);
    doc.documentElement.dataset.themePreference = choice;
    doc.documentElement.dataset.theme = resolveTheme(choice, prefersDark(win));
}

/**
 * While the preference is "system", follow the device when its setting changes (e.g. the OS
 * switching to dark at sunset). Returns a function that stops listening.
 */
export function followSystemTheme(preference, doc = globalThis.document, win = globalThis.window) {
    if (normaliseTheme(preference) !== 'system' || !win?.matchMedia) {
        return () => {};
    }
    const query = win.matchMedia(DARK_QUERY);
    const update = () => applyTheme('system', doc, win);
    query.addEventListener('change', update);
    return () => query.removeEventListener('change', update);
}
