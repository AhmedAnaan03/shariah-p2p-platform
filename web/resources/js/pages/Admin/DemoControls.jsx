import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../components/Alert';
import Badge from '../../components/Badge';
import Button from '../../components/Button';
import Card from '../../components/Card';
import ContractBadge from '../../components/ContractBadge';
import EmptyState from '../../components/EmptyState';
import ListFilters from '../../components/ListFilters';
import StatusBadge from '../../components/StatusBadge';
import Table from '../../components/Table';
import TextLink from '../../components/TextLink';
import { withReturn } from '../../lib/returnUrl';

const MUDARABAH = 'mudarabah';

/**
 * DEMO TOOL, local environment only (PB-34): move a repaying campaign's schedule so its next
 * row is due now or overdue, to demonstrate repayments and late payments without waiting a
 * month. The page and its routes only exist on a developer's machine (APP_ENV=local).
 *
 * The buttons are worded per contract: a Mudarabah's rows are planned capital returns, not
 * debts, so they are "planned for today" rather than "due", and never "overdue".
 */
export default function DemoControls({ campaigns, hasAny, filters, filterOptions, defaultOverdueDays }) {
    const { url } = usePage();
    const [busy, setBusy] = useState(null);

    function advance(campaign, mode) {
        setBusy(`${campaign.id}-${mode}`);
        router.post(campaign.url, { mode }, { preserveScroll: true, onFinish: () => setBusy(null) });
    }

    const columns = [
        {
            key: 'title',
            label: 'Campaign',
            render: (row) => <TextLink href={withReturn(row.reviewUrl, url)}>{row.title}</TextLink>,
        },
        { key: 'contract', label: 'Contract', render: (row) => <ContractBadge type={row.contract_type} size="sm" /> },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.badge_status} size="sm" /> },
        {
            key: 'next',
            label: 'Next row',
            render: (row) => (row.next ? `${row.next.label}: ${row.next.stateLabel}` : 'Every row is paid'),
        },
        {
            key: 'actions',
            label: <span className="sr-only">Demo actions</span>,
            render: (row) => {
                const mudarabah = row.contract_type === MUDARABAH;
                return (
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button size="sm" variant="secondary" disabled={!row.next} loading={busy === `${row.id}-due`} onClick={() => advance(row, 'due')}>
                            {mudarabah ? 'Plan for today' : 'Make due today'}
                            <span className="sr-only"> {row.title}</span>
                        </Button>
                        <Button size="sm" variant="secondary" disabled={!row.next} loading={busy === `${row.id}-overdue`} onClick={() => advance(row, 'overdue')}>
                            {mudarabah ? `Date ${defaultOverdueDays} days ago` : `Make ${defaultOverdueDays} days overdue`}
                            <span className="sr-only"> {row.title}</span>
                        </Button>
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Alert tone="warning" title="Demo tool" role="note">
                These controls change schedule dates so repayments and late payments can be shown without waiting a month. They exist only on a developer’s machine (the local environment), never in
                production. Every change is recorded in the audit log.
            </Alert>

            <Card
                title="Repaying campaigns"
                description="Move a schedule so its next unpaid row falls today or in the past. Every later row moves by the same number of days."
                actions={<Badge tone="warning" icon="clock">Demo only</Badge>}
            >
                {hasAny && (
                    <div className="mb-5">
                        <ListFilters
                            filters={filters}
                            only={['campaigns']}
                            searchLabel="Search repaying campaigns"
                            searchPlaceholder="Campaign title"
                            fields={[{ name: 'contract', label: 'Contract', allLabel: 'All contracts', options: filterOptions.contract }]}
                            resultCount={campaigns.length}
                            resultNoun={['campaign', 'campaigns']}
                        />
                    </div>
                )}
                {!hasAny ? (
                    <EmptyState kind="campaigns" title="No campaigns are repaying yet">
                        Fund a campaign by pledging its full target; it is paid out at once and appears here.
                    </EmptyState>
                ) : campaigns.length === 0 ? (
                    <EmptyState kind="search" title="No campaigns match these filters">
                        Try other words, or clear the filters to see every repaying campaign.
                    </EmptyState>
                ) : (
                    <Table columns={columns} rows={campaigns} caption="Repaying campaigns" stack="wide" />
                )}
            </Card>
        </>
    );
}

DemoControls.layout = {
    title: 'Demo controls',
    description: 'Demo tool: move schedules forward in time. Local environment only.',
};
