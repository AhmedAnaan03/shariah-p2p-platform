import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ContractBadge from '../../../components/ContractBadge';
import EmptyState from '../../../components/EmptyState';
import Money from '../../../components/Money';
import Table from '../../../components/Table';
import Tabs from '../../../components/Tabs';
import { formatDate } from '../../../lib/format';

/**
 * Campaign review (PB-15): requests waiting for a decision (oldest first) and live campaigns
 * (soonest deadline first), which can be suspended from their review page.
 */
export default function AdminCampaignsIndex({ toReview, live }) {
    const columns = (dateColumn) => [
        { key: 'title', label: 'Request' },
        { key: 'borrower_name', label: 'Borrower' },
        { key: 'contract', label: 'Contract', render: (row) => <ContractBadge type={row.contract_type} size="sm" /> },
        { key: 'amount_requested', label: 'Amount', align: 'right', render: (row) => <Money value={row.amount_requested} /> },
        { key: 'tenure_months', label: 'Tenure', align: 'right', mono: true, render: (row) => `${row.tenure_months} months` },
        dateColumn,
        {
            key: 'review',
            label: <span className="sr-only">Actions</span>,
            render: (row) => (
                <Button href={row.url} size="sm" variant="secondary" iconRight="arrow-right">
                    Review<span className="sr-only"> {row.title}</span>
                </Button>
            ),
        },
    ];

    const submittedColumn = { key: 'submitted_at', label: 'Submitted', mono: true, render: (row) => formatDate(row.submitted_at) };
    const deadlineColumn = { key: 'funding_deadline', label: 'Funding deadline', mono: true, render: (row) => row.funding_deadline_label };

    return (
        <Card>
            <Tabs
                aria-label="Campaigns"
                tabs={[
                    { value: 'review', label: 'To review', count: toReview.length },
                    { value: 'live', label: 'Live', count: live.length },
                ]}
            >
                {(active) =>
                    active === 'review' ? (
                        toReview.length === 0 ? (
                            <EmptyState kind="queue" title="No requests are waiting for review">
                                Requests appear here as soon as a verified borrower submits them.
                            </EmptyState>
                        ) : (
                            <Table columns={columns(submittedColumn)} rows={toReview} caption="Requests waiting for review" stack="wide" />
                        )
                    ) : live.length === 0 ? (
                        <EmptyState kind="campaigns" title="No campaigns are live">
                            Approved requests appear here until their funding deadline.
                        </EmptyState>
                    ) : (
                        <Table columns={columns(deadlineColumn)} rows={live} caption="Live campaigns" stack="wide" />
                    )
                }
            </Tabs>
        </Card>
    );
}

AdminCampaignsIndex.layout = {
    title: 'Campaigns',
    description: 'Approve, reject or suspend financing campaigns, so only suitable ones reach investors.',
};
