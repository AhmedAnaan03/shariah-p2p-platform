/**
 * Return navigation in the browser (PB-54). A link to another page carries the page it was
 * opened from as ?return_to=, so that page's back link and breadcrumb can go back to it, with
 * its filters and tab. The server checks the value (App\Support\ReturnUrl) before using it;
 * this only builds the link.
 *
 *   <Button href={withReturn(row.url, page.url)}>Review</Button>
 */
export const RETURN_PARAM = 'return_to';

/**
 * `href` (a path or a full URL from the server's route()) with `from` (the current page's path
 * and query, Inertia's page.url) as its return_to. Returns a path, never a full URL.
 */
export function withReturn(href, from) {
    const url = new URL(href, 'http://app.invalid');
    if (from) {
        url.searchParams.set(RETURN_PARAM, from);
    }
    return url.pathname + url.search;
}
