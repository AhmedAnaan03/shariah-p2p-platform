/**
 * Read-only label/value pairs, e.g. a profile on the admin review page.
 * `items` is a list of { label, value }; an empty value shows as a dash.
 */
export default function DetailList({ items }) {
    return (
        <dl className="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            {items.map((item) => (
                <div key={item.label}>
                    <dt className="text-slate-500">{item.label}</dt>
                    <dd className="mt-0.5 font-medium text-slate-900">{item.value === null || item.value === '' ? '—' : item.value}</dd>
                </div>
            ))}
        </dl>
    );
}
