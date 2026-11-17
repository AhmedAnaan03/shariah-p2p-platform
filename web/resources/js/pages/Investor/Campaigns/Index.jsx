import Badge from '../../../components/Badge';
import Card from '../../../components/Card';
import ContractBadge from '../../../components/ContractBadge';
import EmptyState from '../../../components/EmptyState';
import Money from '../../../components/Money';
import Table from '../../../components/Table';
import { daysLeftLabel } from '../../../lib/format';

/**
 * The marketplace, first version (PB-15): every approved campaign still open, with its funding
 * deadline, closest deadline first. PB-16 adds search, filters and pages, PB-17 the campaign
 * page and PB-19 pledging.
 */
export default function MarketplaceIndex({ campaigns }) {
    const columns = [
        {
            key: 'title',
            label: 'Campaign',
            render: (row) => (row.business_name ? `${row.title} · ${row.business_name}` : row.title),
        },
        { key: 'purpose_label', label: 'Purpose' },
        { key: 'contract', label: 'Contract', render: (row) => <ContractBadge type={row.contract_type} size="sm" /> },
        { key: 'amount_requested', label: 'Target', align: 'right', render: (row) => <Money value={row.amount_requested} /> },
        { key: 'tenure_months', label: 'Tenure', align: 'right', mono: true, render: (row) => `${row.tenure_months} months` },
        { key: 'funding_deadline', label: 'Funding deadline', mono: true, render: (row) => row.funding_deadline_label },
        {
            key: 'time_left',
            label: 'Time left',
            render: (row) => (
                <Badge tone="info" icon="clock" size="sm">
                    {daysLeftLabel(row.funding_deadline)}
                </Badge>
            ),
        },
    ];

    return (
        <Card title="Open campaigns" description="Approved by an administrator and open until its funding deadline. Pledging arrives in a later sprint.">
            {campaigns.length === 0 ? (
                <EmptyState kind="campaigns" title="No campaigns are open right now">
                    Campaigns appear here as soon as an administrator approves them.
                </EmptyState>
            ) : (
                <Table columns={columns} rows={campaigns} caption="Open campaigns" stack="wide" />
            )}
        </Card>
    );
}

MarketplaceIndex.layout = {
    title: 'Marketplace',
    description: 'Financing campaigns open to investors.',
};
