import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { debounce, SEARCH_DEBOUNCE_MS } from '../lib/debounce';
import { ALL, filterParams, hasActiveFilters } from '../lib/filters';
import { positionsFor, valuesFor } from '../lib/rangeSlider';
import Button from './Button';
import Input from './Input';
import RangeSlider from './RangeSlider';
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
 *
 * A field with `type: 'range'` is a two-handle slider (PB-16, the marketplace amount):
 *
 *   { type: 'range', label: 'Amount', minName: 'amount_min', maxName: 'amount_max', steps: [10000, ...] }
 *
 * It is as tall as a dropdown, reloads when a handle is released (after a 300 ms pause for key
 * presses), and an end at the edge of the track is left out of the URL.
 */
export default function ListFilters({ filters = {}, fields = [], only = [], searchLabel = 'Search', searchPlaceholder, resultCount, resultNoun = ['result', 'results'] }) {
    const ranges = fields.filter((field) => field.type === 'range');
    const rangeNames = ranges.flatMap((field) => [field.minName, field.maxName]);
    const names = ['search', ...fields.filter((field) => field.type !== 'range').map((field) => field.name), ...rangeNames];
    // A search or a range end is '' when unset; a dropdown is "all".
    const blank = (name) => (name === 'search' || rangeNames.includes(name) ? '' : ALL);
    const fromServer = () => Object.fromEntries(names.map((name) => [name, filters[name] !== undefined ? String(filters[name]) : blank(name)]));
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

    function changeRange(field, positions, how) {
        const { min, max } = valuesFor(positions, field.steps);
        const next = { ...values, [field.minName]: min, [field.maxName]: max };
        setValues(next);
        if (how === 'keyboard') {
            debouncedReload.current(next);
        } else {
            debouncedReload.current.cancel();
            reload(next);
        }
    }

    function clear() {
        const empty = Object.fromEntries(names.map((name) => [name, blank(name)]));
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
                {fields.map((field) =>
                    field.type === 'range' ? (
                        <RangeSlider
                            key={field.minName}
                            id={`filter-${field.minName}`}
                            label={field.label}
                            steps={field.steps}
                            value={positionsFor(values[field.minName], values[field.maxName], field.steps)}
                            onCommit={(positions, how) => changeRange(field, positions, how)}
                            className="grow basis-80"
                        />
                    ) : (
                        <Select
                            key={field.name}
                            id={`filter-${field.name}`}
                            label={field.label}
                            options={[{ value: ALL, label: field.allLabel }, ...field.options]}
                            value={values[field.name]}
                            onChange={(value) => change(field.name, value)}
                            className="min-w-0 grow basis-44"
                        />
                    ),
                )}
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
