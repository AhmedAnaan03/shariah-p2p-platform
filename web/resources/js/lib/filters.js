/**
 * The query string for the shared search-and-filter bar (PB-54). Starts from the page's current
 * query, so anything else in it (the open tab) is kept, then sets or removes each filter.
 * "all" and empty values are left out: no parameter means no filter, which keeps URLs short.
 *
 *   filterParams('?tab=live&status=live', { search: 'van', status: 'all' })  →  { tab: 'live', search: 'van' }
 */
export const ALL = 'all';

export function filterParams(currentQuery, values) {
    const params = Object.fromEntries(new URLSearchParams(currentQuery));

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
