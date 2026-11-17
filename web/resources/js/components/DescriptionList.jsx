import cx from '../lib/cx';
import { EMPTY } from '../lib/format';

const COLUMNS = {
    1: '',
    2: '@bp-sm:grid-cols-2',
    3: '@bp-sm:grid-cols-3',
    4: '@bp-sm:grid-cols-2 @bp-md:grid-cols-4',
};

/**
 * Read-only label / value pairs, e.g. a profile on the admin review page
 * (component-api.d.ts, DescriptionList). `items` is a list of [label, value, mono?]; set mono
 * for figures, IDs and dates so they line up in Plex Mono. An empty value shows as a dash.
 *
 *   <DescriptionList items={[['Phone', '+960 774 2210', true], ['Sector', 'Fisheries']]} />
 *
 * Two pairs per row on phones, `columns` per row from 640 px.
 */
export default function DescriptionList({ items, columns = 2 }) {
    return (
        <div className="@container">
            <dl className={cx('grid grid-cols-2 gap-x-6 gap-y-4', columns === 1 && 'grid-cols-1', COLUMNS[columns] ?? COLUMNS[2])}>
                {items.map(([label, value, mono], index) => (
                    <div key={index} className="min-w-0">
                        <dt className="text-label text-text-muted">{label}</dt>
                        <dd className={cx('mt-1 break-words whitespace-pre-line text-text', mono ? 'font-mono text-figure' : 'text-body')}>
                            {value === null || value === undefined || value === '' ? EMPTY : value}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
