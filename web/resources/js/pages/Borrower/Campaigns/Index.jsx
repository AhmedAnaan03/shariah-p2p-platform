import { usePage } from '@inertiajs/react';
import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ContractBadge from '../../../components/ContractBadge';
import EmptyState from '../../../components/EmptyState';
import ListFilters from '../../../components/ListFilters';
import Money from '../../../components/Money';
import StatusBadge from '../../../components/StatusBadge';
import Table from '../../../components/Table';
import { formatDate } from '../../../lib/format';
import { withReturn } from '../../../lib/returnUrl';

/**
 * The borrower's financing requests, newest first (PB-13), with the shared search-and-filter bar
 * (PB-54). Only a verified borrower gets the "New request" button; the create page explains why
 * to anyone else.
 */
export default function CampaignsIndex({ campaigns, hasAny, canCreate, filters, filterOptions }) {
    const { url } = usePage();
    const newRequest = (
        <Button href="/borrower/campaigns/create" iconLeft="plus">
            New request
        </Button>
    );

    const columns = [
        { key: 'title', label: 'Request' },
        { key: 'contract', label: 'Contract', render: (row) => <ContractBadge type={row.contract_type} size="sm" /> },
        { key: 'amount_requested', label: 'Amount', align: 'right', render: (row) => <Money value={row.amount_requested} /> },
        { key: 'tenure_months', label: 'Tenure', align: 'right', mono: true, render: (row) => `${row.tenure_months} months` },
        { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} size="sm" /> },
        { key: 'submitted_at', label: 'Submitted', mono: true, render: (row) => formatDate(row.submitted_at) },
        {
            key: 'view',
            label: <span className="sr-only">Actions</span>,
            render: (row) => (
                <Button href={withReturn(row.url, url)} size="sm" variant="secondary" iconRight="arrow-right">
                    View<span className="sr-only"> {row.title}</span>
                </Button>
            ),
        },
    ];

    return (
        <>
            {!canCreate && (
                <Alert
                    tone="info"
                    role="note"
                    action={
                        <Button href="/borrower" variant="secondary" size="sm">
                            See your verification steps
                        </Button>
                    }
                >
                    Your account must be verified before you can submit a financing request.
                </Alert>
            )}

            <Card title="Your requests" actions={canCreate && hasAny ? newRequest : null}>
                {hasAny && (
                    <div className="mb-5">
                        <ListFilters
                            filters={filters}
                            only={['campaigns']}
                            searchLabel="Search your requests"
                            searchPlaceholder="Request title"
                            fields={[
                                { name: 'status', label: 'Status', allLabel: 'All statuses', options: filterOptions.status },
                                { name: 'contract', label: 'Contract', allLabel: 'All contracts', options: filterOptions.contract },
                            ]}
                            resultCount={campaigns.length}
                            resultNoun={['request', 'requests']}
                        />
                    </div>
                )}
                {!hasAny ? (
                    <EmptyState kind="campaigns" title="No financing requests yet" action={canCreate ? newRequest : null}>
                        Requests you submit appear here with their review status.
                    </EmptyState>
                ) : campaigns.length === 0 ? (
                    <EmptyState kind="search" title="No requests match these filters">
                        Try other words, or clear the filters to see all your requests.
                    </EmptyState>
                ) : (
                    <Table columns={columns} rows={campaigns} caption="Your financing requests" stack="wide" />
                )}
            </Card>
        </>
    );
}

CampaignsIndex.layout = {
    title: 'Financing requests',
    description: 'Raise financing from investors on the platform.',
};
