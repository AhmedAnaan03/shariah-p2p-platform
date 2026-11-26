import { useId, useState } from 'react';
import cx from '../lib/cx';
import Button from './Button';
import Table from './Table';

/**
 * The frame every chart sits in (README, "Data visualisation"; component-api.d.ts, ChartFrame):
 * a visible title, a one-line takeaway above the chart, a legend top-left with 8 px dots, and
 * "View as table", which swaps the chart for an accessible table of the same figures.
 *
 *   <ChartFrame title="Capital and markup by month" takeaway="MVR 11,000.00 over 3 months."
 *       legend={[{ label: 'Capital', color: 'var(--color-chart-1)' }]}
 *       table={{ columns: ['Month', 'Capital'], rows: [['Dec 2026', 'MVR 3,333.33']] }}>
 *       <RepaymentScheduleChart data={...} />
 *   </ChartFrame>
 *
 * The chart itself is hidden from screen readers (its takeaway is its accessible name); the
 * table is the way to read every figure.
 */
export default function ChartFrame({ title, takeaway, legend = [], table, actions, children }) {
    const [showTable, setShowTable] = useState(false);
    const titleId = useId();

    return (
        <figure aria-labelledby={titleId} className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 max-w-prose">
                    <h3 id={titleId} className="text-h4 text-text">
                        {title}
                    </h3>
                    <p className="mt-1 text-body-sm text-text-secondary">{takeaway}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                    {table && (
                        <Button variant="ghost" size="sm" aria-pressed={showTable} onClick={() => setShowTable(!showTable)}>
                            {showTable ? 'View as chart' : 'View as table'}
                        </Button>
                    )}
                </div>
            </div>

            {legend.length > 0 && !showTable && (
                <ul aria-label="Legend" className="flex flex-wrap gap-x-4 gap-y-2">
                    {legend.map((item) => (
                        <li key={item.label} className="flex items-center gap-2 text-body-sm text-text-secondary">
                            <span
                                aria-hidden="true"
                                className={cx('shrink-0', item.line ? 'h-0.5 w-3' : 'size-2 rounded-full', item.dashed && 'border border-dashed bg-transparent')}
                                style={item.dashed ? { borderColor: item.color } : { backgroundColor: item.color }}
                            />
                            {item.label}
                        </li>
                    ))}
                </ul>
            )}

            {showTable && table ? (
                <Table
                    caption={typeof title === 'string' ? title : undefined}
                    columns={table.columns.map((label, i) => ({ key: String(i), label, align: i > 1 ? 'right' : undefined, mono: i > 1 }))}
                    rows={table.rows.map((cells, r) => ({ id: r, ...Object.fromEntries(cells.map((cell, i) => [String(i), cell])) }))}
                />
            ) : (
                <div role="img" aria-label={typeof takeaway === 'string' ? takeaway : undefined}>
                    {children}
                </div>
            )}
        </figure>
    );
}
