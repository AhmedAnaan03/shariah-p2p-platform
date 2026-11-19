import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { debounce, SEARCH_DEBOUNCE_MS } from '../lib/debounce';
import { ALL, filterParams, hasActiveFilters } from '../lib/filters';
import Button from './Button';
import Input from './Input';
import Select from './Select';

/**
 * The shared search-and-filter bar for every list (PB-54; CLAUDE.md: every list uses it).
 *
 *   <ListFilters
 *       filters={filters}                      // what the server says the list is filtered by
 *       fields={[{ name: 'status', label: 'Status', allLabel: 'All statuses', options: filterOptions.status }]}
 *       only={['campaigns']}                    // the props a reload fetches
 *       searchLabel="Search requests" searchPlaceholder="Delivery van"
 *       resultCount={campaigns.length} resultNoun={['request', 'requests']}
 *   />
 *
 * - Search as you type: the reload waits until typing has paused for 300 ms.
 * - A dropdown reloads at once.
 * - Filtering happens on the server: each change is an Inertia partial reload of only the list
 *   props (and `filters`), so the page, its scroll position and the focus stay where they are.
 * - The values live in the URL (replacing the history entry, so Back still leaves the page),
 *   so a filtered list can be bookmarked, reloaded, or returned to from a detail page.
 * - "Clear filters" removes them all. The number of results is announced to screen readers.
 */
export default function ListFilters({ filters = {}, fields = [], only = [], searchLabel = 'Search', searchPlaceholder, resultCount, resultNoun = ['result', 'results'] }) {
    const names = ['search', ...fields.map((field) => field.name)];
    const fromServer = () => Object.fromEntries(names.map((name) => [name, filters[name] ?? (name === 'search' ? '' : ALL)]));
    const [values, setValues] = useState(fromServer);

    const reload = useMemo(
        () => (next) =>
            router.get(window.location.pathname, filterParams(window.location.search, next), {
                only: [...only, 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }),
        // `only` is a fresh array on every render; its contents never change for a page.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [only.join(',')],
    );
    const debouncedReload = useRef(null);
    if (debouncedReload.current === null) {
        debouncedReload.current = debounce((next) => reload(next), SEARCH_DEBOUNCE_MS);
    }
    useEffect(() => () => debouncedReload.current.cancel(), []);

    // When the server's filters change for another reason (browser Back, a link), show them,
    // unless the user is in the middle of typing a search.
    const serverKey = JSON.stringify(filters);
    useEffect(() => {
        if (!debouncedReload.current.pending()) {
            setValues(fromServer());
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverKey]);

    function change(name, value) {
        const next = { ...values, [name]: value };
        setValues(next);
        if (name === 'search') {
            debouncedReload.current(next);
        } else {
            debouncedReload.current.cancel();
            reload(next);
        }
    }

    function clear() {
        const empty = Object.fromEntries(names.map((name) => [name, name === 'search' ? '' : ALL]));
        debouncedReload.current.cancel();
        setValues(empty);
        reload(empty);
    }

    const active = hasActiveFilters(values, names);

    return (
        <div role="search" className="space-y-3">
            <div className="flex flex-wrap items-end gap-3">
                <Input
                    id="list-search"
                    type="search"
                    icon="magnifying-glass"
                    label={searchLabel}
                    placeholder={searchPlaceholder}
                    value={values.search}
                    onChange={(event) => change('search', event.target.value)}
                    autoComplete="off"
                    className="min-w-0 flex-1 basis-64"
                />
                {fields.map((field) => (
                    <Select
                        key={field.name}
                        id={`filter-${field.name}`}
                        label={field.label}
                        options={[{ value: ALL, label: field.allLabel }, ...field.options]}
                        value={values[field.name]}
                        onChange={(value) => change(field.name, value)}
                        className="min-w-0 grow basis-44"
                    />
                ))}
                {active && (
                    <Button variant="ghost" iconLeft="x" onClick={clear}>
                        Clear filters
                    </Button>
                )}
            </div>
            {resultCount !== undefined && (
                <p className="text-body-sm text-text-muted" aria-live="polite">
                    {active ? `${resultCount} ${resultCount === 1 ? resultNoun[0] : resultNoun[1]} match these filters.` : `${resultCount} ${resultCount === 1 ? resultNoun[0] : resultNoun[1]}.`}
                </p>
            )}
        </div>
    );
}
