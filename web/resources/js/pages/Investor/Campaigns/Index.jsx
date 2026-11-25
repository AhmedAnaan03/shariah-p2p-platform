import { usePage } from '@inertiajs/react';
import Alert from '../../../components/Alert';
import CampaignCard from '../../../components/CampaignCard';
import Card from '../../../components/Card';
import EmptyState from '../../../components/EmptyState';
import ListFilters from '../../../components/ListFilters';
import Pagination from '../../../components/Pagination';
import { daysLeft } from '../../../lib/format';
import { withReturn } from '../../../lib/returnUrl';

/**
 * The marketplace (PB-15, PB-16): open campaigns as cards, closest deadline first, nine to a
 * page. The shared search-and-filter bar (PB-54) searches the title and business name and
 * filters by sector, purpose, amount (a two-handle slider), tenure, contract and risk grade; all of it happens on the
 * server and stays in the URL, so a filtered page can be bookmarked and returned to.
 *
 * With Shariah-only on (PB-31) the server sends only Murabahah and Mudarabah campaigns and only
 * those two contract options; this page just says so.
 */
export default function MarketplaceIndex({ campaigns, hasAny, shariahOnly, filters, filterOptions, amountSlider, pledge }) {
    const { url } = usePage();
    const only = ['campaigns'];

    return (
        <>
            {!pledge.allowed && (
                <Alert tone="warning" role="note" title="Pledging is locked">
                    {pledge.reason}
                </Alert>
            )}
            {shariahOnly && (
                <Alert tone="info" role="note" title="Showing Shariah campaigns only">
                    Shariah-only mode is on, so only Murabahah and Mudarabah campaigns are listed. Turn it off with the switch in the top bar to see every campaign.
                </Alert>
            )}
            <Card title="Open campaigns" description="Approved by an administrator and open until their funding deadline. Open one to pledge from your simulated wallet.">
                {hasAny && (
                    <div className="mb-6">
                        <ListFilters
                            filters={filters}
                            only={only}
                            searchLabel="Search campaigns"
                            searchPlaceholder="Campaign or business name"
                            fields={[
                                { name: 'sector', label: 'Sector', allLabel: 'All sectors', options: filterOptions.sector },
                                { name: 'purpose', label: 'Purpose', allLabel: 'All purposes', options: filterOptions.purpose },
                                { name: 'tenure', label: 'Tenure', allLabel: 'Any tenure', options: filterOptions.tenure },
                                { name: 'contract', label: 'Contract', allLabel: shariahOnly ? 'Murabahah and Mudarabah' : 'All contracts', options: filterOptions.contract },
                                { name: 'grade', label: 'Risk grade', allLabel: 'Any grade', options: filterOptions.grade },
                                { type: 'range', label: 'Amount', minName: 'amount_min', maxName: 'amount_max', steps: amountSlider.steps },
                            ]}
                            resultCount={campaigns.total}
                            resultNoun={['campaign', 'campaigns']}
                        />
                    </div>
                )}
                {!hasAny ? (
                    <EmptyState kind="campaigns" title="No campaigns are open right now">
                        {shariahOnly ? 'No Murabahah or Mudarabah campaigns are open. They appear here as soon as an administrator approves them.' : 'Campaigns appear here as soon as an administrator approves them.'}
                    </EmptyState>
                ) : campaigns.data.length === 0 ? (
                    <EmptyState kind="search" title="No campaigns match these filters">
                        Try other words, or clear the filters to see every open campaign.
                    </EmptyState>
                ) : (
                    <div className="space-y-6">
                        <ul className="grid gap-6 @bp-md/shell:grid-cols-2 @bp-xl/shell:grid-cols-3">
                            {campaigns.data.map((campaign) => (
                                <li key={campaign.id}>
                                    {/* Carries this page's address, so the campaign's back link returns here with the filters and page (PB-54). */}
                                    <CampaignCard
                                        name={campaign.title}
                                        business={campaign.business_name}
                                        sector={campaign.sector_label}
                                        purpose={campaign.purpose_label}
                                        contract={campaign.contract_type}
                                        grade={campaign.grade}
                                        amount={campaign.amount_requested}
                                        raised={campaign.raised}
                                        tenure={campaign.tenure_months}
                                        rate={campaign.rate}
                                        rateUnit={campaign.rate_unit}
                                        investors={campaign.investors}
                                        daysLeft={daysLeft(campaign.funding_deadline)}
                                        href={withReturn(campaign.url, url)}
                                    />
                                </li>
                            ))}
                        </ul>
                        <Pagination page={campaigns} only={only} noun={['campaign', 'campaigns']} />
                    </div>
                )}
            </Card>
        </>
    );
}

MarketplaceIndex.layout = {
    title: 'Marketplace',
    description: 'Financing campaigns open to investors.',
};
