/**
 * When the mobile menu (MobileNav) closes on its own. It closes when a visit starts, so following a
 * link or submitting a form never leaves it covering the next page, but not for a setting saved
 * from inside it: the theme toggle and the Shariah-only switch save in the background (a PATCH
 * that keeps the page's state). Closing then slid the drawer away in the middle of the 500 ms
 * palette cross-fade, uncovering the top-left of the page at a different shade from the rest, so
 * the card there seemed to flash (PB-16 review).
 *
 *   router.on('start', (event) => closesMenu(event.detail.visit) && setMenuOpen(false));
 *
 * Plain functions with no React, so they are unit-tested on their own (visits.test.js).
 */
export function closesMenu(visit) {
    const method = String(visit?.method ?? 'get').toLowerCase();
    return method === 'get' || !visit?.preserveState;
}

/** Whether two Inertia page URLs are different pages (the path differs; a new query string or hash is the same page). */
export function isOtherPage(fromUrl, toUrl) {
    const path = (url) => new URL(url ?? '/', 'http://app.invalid').pathname;
    return path(fromUrl) !== path(toUrl);
}
