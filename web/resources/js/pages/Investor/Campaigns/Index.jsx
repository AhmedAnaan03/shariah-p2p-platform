import Badge from '../../../components/Badge';
import Card from '../../../components/Card';
import ContractBadge from '../../../components/ContractBadge';
import EmptyState from '../../../components/EmptyState';
import Money from '../../../components/Money';
import ListFilters from '../../../components/ListFilters';
import Table from '../../../components/Table';
import { daysLeftLabel } from '../../../lib/format';

/**
 * The marketplace, first version (PB-15): every approved campaign still open, with its funding
 * deadline, closest deadline first. PB-54 added the shared search-and-filter bar (contract and
 * purpose); PB-16 adds its remaining filters and pages, PB-17 the campaign page and PB-19 pledging.
 */
export default function MarketplaceIndex({ campaigns, hasAny, filters, filterOptions }) {
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
            {hasAny && (
                <div className="mb-5">
                    <ListFilters
                        filters={filters}
                        only={['campaigns']}
                        searchLabel="Search campaigns"
                        searchPlaceholder="Campaign or business name"
                        fields={[
                            { name: 'contract', label: 'Contract', allLabel: 'All contracts', options: filterOptions.contract },
                            { name: 'purpose', label: 'Purpose', allLabel: 'All purposes', options: filterOptions.purpose },
                        ]}
                        resultCount={campaigns.length}
                        resultNoun={['campaign', 'campaigns']}
                    />
                </div>
            )}
            {!hasAny ? (
                <EmptyState kind="campaigns" title="No campaigns are open right now">
                    Campaigns appear here as soon as an administrator approves them.
                </EmptyState>
            ) : campaigns.length === 0 ? (
                <EmptyState kind="search" title="No campaigns match these filters">
                    Try other words, or clear the filters to see every open campaign.
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
