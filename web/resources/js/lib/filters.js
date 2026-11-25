/**
 * The query string for the shared search-and-filter bar (PB-54). Starts from the page's current
 * query, so anything else in it (the open tab) is kept, then sets or removes each filter.
 * "all" and empty values are left out: no parameter means no filter, which keeps URLs short.
 * The page number is dropped, so new results start at page 1 (PB-16).
 *
 *   filterParams('?tab=live&status=live', { search: 'van', status: 'all' })  →  { tab: 'live', search: 'van' }
 */
export const ALL = 'all';

/**
 * The paginator's parameter (PB-16). A change to the search or a filter always starts again at
 * page 1: page 3 of the old results may not exist in the new ones.
 */
export const PAGE_PARAM = 'page';

export function filterParams(currentQuery, values) {
    const params = Object.fromEntries(new URLSearchParams(currentQuery));
    delete params[PAGE_PARAM];

    for (const [name, value] of Object.entries(values)) {
        const clean = typeof value === 'string' ? value.trim() : value;
        if (clean === undefined || clean === null || clean === '' || clean === ALL) {
            delete params[name];
        } else {
            params[name] = clean;
        }
    }

    return params;
}

/** Whether any of the bar's own filters is set. */
export function hasActiveFilters(filters, names) {
    return names.some((name) => filters?.[name] !== undefined && filters[name] !== '' && filters[name] !== ALL);
}
