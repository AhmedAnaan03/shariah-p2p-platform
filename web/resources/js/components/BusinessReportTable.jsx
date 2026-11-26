import BulletList from './BulletList';
import Money from './Money';
import StatusBadge from './StatusBadge';
import Table from './Table';
import { formatMoney } from '../lib/format';
import { REPORT_TEXT } from '../lib/mudarabah';

/**
 * A Mudarabah's business reports (component-api.d.ts, BusinessReportTable; PB-29): one row per
 * report with its period, result (profit or loss), the capital returned, its status and what the
 * decision did. Every label comes from the server (MudarabahPresenter), worded so that nothing
 * reads as a debt. With `yours`, each approved report also shows the investor's own part.
 *
 *   <BusinessReportTable reports={mudarabah.reports} />
 *   <BusinessReportTable reports={mudarabah.reports} yours caption="Reports and your part" />
 */
export default function BusinessReportTable({ reports, yours = false, caption = 'Business reports' }) {
    const outcome = (row) => {
        if (row.status === 'rejected') return `Reason given: ${row.reason}`;
        if (row.status !== 'approved') return REPORT_TEXT.waiting;
        const parts = yours
            ? [
                  ['Your capital returned', row.yours.capital],
                  ['Your losses restored', row.yours.restored],
                  ['Your profit share', row.yours.profit],
                  ['Your loss borne', row.yours.loss],
              ].filter(([label, value], index) => index === 0 || Number(value) > 0)
            : row.outcome;
        // A plain list, not a DescriptionList: its container query would give a table cell no width.
        return (
            <BulletList
                items={[
                    ...(row.causeLabel ? [{ title: 'Loss cause:', text: row.causeLabel }] : []),
                    ...parts.map(([label, value]) => ({ title: `${label}:`, text: formatMoney(value) })),
                    ...(row.reason ? [{ title: 'Reason given:', text: row.reason }] : []),
                ]}
            />
        );
    };

    return (
        <Table
            caption={caption}
            stack="wide"
            columns={[
                { key: 'periodLabel', label: 'Period', render: (row) => `${row.periodLabel} (${row.plannedDateLabel})` },
                { key: 'result', label: 'Result', align: 'right', mono: true, render: (row) => `${row.resultLabel}: ${formatMoney(row.result)}` },
                { key: 'capitalReturned', label: 'Capital returned', align: 'right', mono: true, render: (row) => <Money value={row.capitalReturned} /> },
                {
                    key: 'status',
                    label: 'Status',
                    render: (row) => (
                        <StatusBadge status={row.status} size="sm">
                            {row.statusLabel}
                        </StatusBadge>
                    ),
                },
                { key: 'outcome', label: yours ? 'Your part' : 'Outcome', render: outcome },
            ]}
            rows={reports}
        />
    );
}
