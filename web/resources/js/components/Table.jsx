/**
 * A data table that scrolls sideways on a narrow screen instead of breaking the page.
 *
 *   <Table
 *       columns={[{ key: 'name', label: 'Name' }, { key: 'open', label: '', render: (row) => <Button ... /> }]}
 *       rows={applicants}
 *       caption="Accounts waiting for verification"
 *   />
 *
 * A column shows row[key] unless it has a render(row) function.
 */
export default function Table({ columns, rows, caption, rowKey = (row) => row.id }) {
    return (
        <div className="overflow-x-auto rounded-md border border-slate-200">
            <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
                {caption && <caption className="sr-only">{caption}</caption>}
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} scope="col" className="whitespace-nowrap px-4 py-3">
                                {column.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 bg-white">
                    {rows.map((row) => (
                        <tr key={rowKey(row)}>
                            {columns.map((column) => (
                                <td key={column.key} className="whitespace-nowrap px-4 py-3 text-slate-700">
                                    {column.render ? column.render(row) : row[column.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
