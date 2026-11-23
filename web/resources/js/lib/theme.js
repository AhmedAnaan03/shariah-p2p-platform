/**
 * The light / dark theme in the browser (PB-55; a two-way switch with "the most recent change
 * wins" since PB-57). data-theme on <html> is what the CSS uses. The server sends the user's
 * last choice (light, dark, or null for none) and the device's setting when they made it; the
 * head script in app.blade.php and these helpers apply the same rule:
 *
 *   no choice, or the device's setting has changed since the choice  → follow the device
 *   otherwise                                                         → keep the choice
 *
 * While a page is open, a change of the device's setting is followed at once (it is the most
 * recent change). Using the switch makes a new choice, saved with the device's setting then.
 */
import { useEffect, useState } from 'react';

export const THEMES = ['light', 'dark'];

export const DARK_QUERY = '(prefers-color-scheme: dark)';

/** "light" or "dark": the device's own setting. */
export function systemTheme(win = globalThis.window) {
    return win?.matchMedia?.(DARK_QUERY).matches ? 'dark' : 'light';
}

/**
 * The theme to show for a saved { choice, systemAtChoice } on a device currently set to `system`.
 */
export function resolveTheme(saved, system) {
    const choice = THEMES.includes(saved?.choice) ? saved.choice : null;
    const atChoice = THEMES.includes(saved?.systemAtChoice) ? saved.systemAtChoice : null;

    if (choice === null) {
        return system;
    }
    return atChoice === null || atChoice === system ? choice : system;
}

/** Put a theme on <html>. The palette cross-fades through the @property registrations. */
export function applyTheme(theme, doc = globalThis.document) {
    if (doc && THEMES.includes(theme)) {
        doc.documentElement.dataset.theme = theme;
    }
}

/**
 * Call `onChange(theme)` whenever the device's setting changes. Returns a function that stops
 * listening.
 */
export function onSystemThemeChange(onChange, win = globalThis.window) {
    if (!win?.matchMedia) {
        return () => {};
    }
    const query = win.matchMedia(DARK_QUERY);
    const listener = () => onChange(systemTheme(win));
    query.addEventListener('change', listener);
    return () => query.removeEventListener('change', listener);
}

/** The theme on <html> right now, kept in step as it changes. */
export function useDocumentTheme() {
    const read = () => (globalThis.document?.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');
    const [theme, setTheme] = useState(read);

    useEffect(() => {
        const observer = new MutationObserver(() => setTheme(read()));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        setTheme(read());
        return () => observer.disconnect();
    }, []);

    return theme;
}
