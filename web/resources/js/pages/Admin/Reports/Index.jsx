import { usePage } from '@inertiajs/react';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import EmptyState from '../../../components/EmptyState';
import ListFilters from '../../../components/ListFilters';
import Money from '../../../components/Money';
import StatusBadge from '../../../components/StatusBadge';
import Table from '../../../components/Table';
import { formatMoney } from '../../../lib/format';
import { withReturn } from '../../../lib/returnUrl';

/**
 * Mudarabah business reports (PB-29): waiting ones first, oldest first, then the decided ones.
 * The shared search-and-filter bar (PB-54) searches the campaign and the business, and filters by
 * status; the review page's back link returns here with the same filters.
 */
export default function AdminReportsIndex({ reports, hasAny, filters, filterOptions }) {
    const { url } = usePage();

    const columns = [
        { key: 'campaignTitle', label: 'Campaign' },
        { key: 'business', label: 'Business' },
        { key: 'periodLabel', label: 'Period' },
        { key: 'submittedLabel', label: 'Submitted', mono: true },
        { key: 'result', label: 'Result', align: 'right', mono: true, render: (row) => `${row.resultLabel}: ${formatMoney(row.result)}` },
        { key: 'capitalReturned', label: 'Capital returned', align: 'right', render: (row) => <Money value={row.capitalReturned} /> },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <StatusBadge status={row.status} size="sm">
                    {row.statusLabel}
                </StatusBadge>
            ),
        },
        {
            key: 'open',
            label: <span className="sr-only">Actions</span>,
            render: (row) => (
                <Button href={withReturn(row.url, url)} size="sm" variant="secondary" iconRight="arrow-right">
                    {row.status === 'submitted' ? 'Review' : 'View'}
                    <span className="sr-only">
                        {' '}
                        {row.campaignTitle}, {row.periodLabel}
                    </span>
                </Button>
            ),
        },
    ];

    return (
        <Card>
            {hasAny && (
                <div className="mb-5">
                    <ListFilters
                        filters={filters}
                        only={['reports']}
                        searchLabel="Search business reports"
                        searchPlaceholder="Campaign or business name"
                        fields={[{ name: 'status', label: 'Status', allLabel: 'All statuses', options: filterOptions.status }]}
                        resultCount={reports.length}
                        resultNoun={['report', 'reports']}
                    />
                </div>
            )}
            {!hasAny ? (
                <EmptyState kind="queue" title="No business reports yet">
                    A Mudarabah business reports each period on its planned capital return date. Its reports appear here for review.
                </EmptyState>
            ) : reports.length === 0 ? (
                <EmptyState kind="search" title="No reports match these filters">
                    Try other words, or clear the filters.
                </EmptyState>
            ) : (
                <Table columns={columns} rows={reports} caption="Business reports" stack="wide" />
            )}
        </Card>
    );
}

AdminReportsIndex.layout = {
    title: 'Business reports',
    description: 'Approve or reject each Mudarabah period report, and decide the cause of any loss.',
};
