import { AnimatePresence, motion } from 'motion/react';
import cx from '../lib/cx';
import { rowExit, useReducedMotion } from '../lib/motion';

/**
 * A data table that turns into stacked cards when its container is narrower than 720 px
 * (README, "Layout across devices"), instead of scrolling sideways.
 *
 *   <Table
 *       columns={[{ key: 'name', label: 'Name' }, { key: 'amount', label: 'Amount', align: 'right', mono: true },
 *                 { key: 'open', label: <span className="sr-only">Actions</span>, render: (row) => <Button ... /> }]}
 *       rows={applicants}
 *       caption="Accounts waiting for verification"
 *   />
 *
 * A column shows row[key] unless it has render(row). `align: 'right'` and `mono` are for
 * figures. Header row in overline style on surface-subtle; cells 12 × 16 px, rows 52 px.
 * `stack="wide"` stacks below 960 px instead, for tables with many columns that would not fit
 * at 720 (the design system's rule is a minimum: every table narrower than 720 px stacks).
 *
 * A row that disappears from `rows` (an approved user leaving the queue) collapses its height
 * and fades out over 200 ms; with reduced motion it only fades.
 */
// Static class names, so Tailwind can find them.
const STACK = {
    default: { table: '@table-stack:block', list: '@table-stack:hidden' },
    wide: { table: '@table-wide:block', list: '@table-wide:hidden' },
};

export default function Table({ columns, rows, caption, stack = 'default', rowKey = (row) => row.id }) {
    const breakpoint = STACK[stack] ?? STACK.default;
    const reduced = useReducedMotion();
    const exit = rowExit(reduced);
    // Every cell's content collapses together, so the whole row closes up.
    const cell = { exit: { ...exit, paddingTop: 0, paddingBottom: 0 } };
    const actionColumn = columns.find((column) => column.render && typeof column.label !== 'string');

    return (
        <div className="@container">
            {/* Wide: a real table. `relative` keeps the sr-only header text inside the clip; if a
                table is still too wide for its space it scrolls inside its own border, never the page. */}
            <div className={cx('relative hidden overflow-x-auto rounded-lg border border-border', breakpoint.table)}>
                <table className="w-full border-collapse text-left">
                    {caption && <caption className="sr-only">{caption}</caption>}
                    <thead className="bg-surface-subtle">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    scope="col"
                                    className={cx('px-4 py-3 align-bottom text-overline text-text-muted', column.align === 'right' && 'text-right')}
                                >
                                    {column.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        <AnimatePresence initial={false}>
                            {rows.map((row) => (
                                <motion.tr key={rowKey(row)} exit="exit" className="border-t border-border-subtle bg-surface hover:bg-surface-subtle">
                                    {columns.map((column) => (
                                        <td key={column.key} className="p-0 align-middle">
                                            <motion.div
                                                variants={cell}
                                                className={cx(
                                                    'flex min-h-(--row) items-center overflow-hidden px-4 py-3 text-body break-words text-text-secondary',
                                                    column.align === 'right' && 'justify-end text-right',
                                                    column.mono && 'font-mono text-figure',
                                                )}
                                            >
                                                {column.render ? column.render(row) : row[column.key]}
                                            </motion.div>
                                        </td>
                                    ))}
                                </motion.tr>
                            ))}
                        </AnimatePresence>
                    </tbody>
                </table>
            </div>

            {/* Narrow: one card per row, label beside value. */}
            <ul aria-label={caption} className={cx('space-y-3', breakpoint.list)}>
                <AnimatePresence initial={false}>
                    {rows.map((row) => (
                        <motion.li key={rowKey(row)} exit={exit} className="overflow-hidden rounded-lg border border-border bg-surface p-4">
                            <dl className="space-y-2">
                                {columns
                                    .filter((column) => column !== actionColumn)
                                    .map((column) => (
                                        <div key={column.key} className="flex justify-between gap-4">
                                            <dt className="text-label text-text-muted">{column.label}</dt>
                                            <dd className={cx('text-right text-body text-text', column.mono && 'font-mono text-figure')}>
                                                {column.render ? column.render(row) : row[column.key]}
                                            </dd>
                                        </div>
                                    ))}
                            </dl>
                            {actionColumn && <div className="mt-3 flex justify-end">{actionColumn.render(row)}</div>}
                        </motion.li>
                    ))}
                </AnimatePresence>
            </ul>
        </div>
    );
}
