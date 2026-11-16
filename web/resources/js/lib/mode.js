/**
 * The platform mode in the browser: conventional or Shariah (README, "Modes").
 *
 * The mode lives in one place, the data-mode attribute on <html>. The server writes it on the
 * first paint (app.blade.php) and ModeScope keeps it in step with the `mode` prop afterwards.
 * Changing the attribute is all a switch needs to do: the theme's CSS re-points every colour
 * token, and the @property registrations make the browser cross-fade them over 500 ms.
 */
import { useEffect, useState } from 'react';

export const MODES = ['conventional', 'shariah'];

/** "shariah" stays "shariah"; anything else is "conventional". */
export function normaliseMode(value) {
    return value === 'shariah' ? 'shariah' : 'conventional';
}

export function readDocumentMode(doc = globalThis.document) {
    return normaliseMode(doc?.documentElement.dataset.mode);
}

export function writeDocumentMode(mode, doc = globalThis.document) {
    if (doc) {
        doc.documentElement.dataset.mode = normaliseMode(mode);
    }
}

/**
 * [mode, setMode]. Starts from `initial` (or the page's current mode), and follows any later
 * change to <html data-mode>, wherever it comes from, so every component using it agrees.
 */
export function usePlatformMode(initial) {
    const [mode, setModeState] = useState(() => normaliseMode(initial ?? readDocumentMode()));

    useEffect(() => {
        const html = document.documentElement;
        const observer = new MutationObserver(() => setModeState(readDocumentMode()));
        observer.observe(html, { attributes: true, attributeFilter: ['data-mode'] });
        setModeState(readDocumentMode());
        return () => observer.disconnect();
    }, []);

    const setMode = (next) => writeDocumentMode(next);

    return [mode, setMode];
}
