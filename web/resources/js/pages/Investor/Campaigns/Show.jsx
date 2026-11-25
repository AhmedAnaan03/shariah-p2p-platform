import Alert from '../../../components/Alert';
import Badge from '../../../components/Badge';
import Card from '../../../components/Card';
import ContractBadge from '../../../components/ContractBadge';
import DescriptionList from '../../../components/DescriptionList';
import EmptyState from '../../../components/EmptyState';
import FundingRing from '../../../components/FundingRing';
import Money from '../../../components/Money';
import RiskGradeCard from '../../../components/RiskGradeCard';
import Term from '../../../components/Term';
import TextLink from '../../../components/TextLink';
import { CAMPAIGN_PAGE_TEXT as TEXT, investorsText } from '../../../lib/campaigns';
import { DEFINITIONS, isShariahContract, MURABAHAH, SHARIAH_DISCLAIMER } from '../../../lib/contracts';
import { daysLeft, daysLeftText } from '../../../lib/format';

/**
 * A campaign's page for investors (PB-17): every disclosure an investor needs before pledging.
 * The purpose and business background, the borrower as investors may see them (never an
 * individual borrower's name), amount, tenure, contract and its terms, the projected return,
 * the borrower's credit history answers and repayment history on the platform, and funding.
 *
 * The risk grade area is reserved and labelled until the campaign is scored (PB-25, PB-26).
 * A Shariah-only investor can't reach a conventional campaign's page: the server sends them
 * back to the marketplace (PB-31).
 */
export default function CampaignShow({ campaign, creditHistory, repaymentHistory, risk, pledge, back }) {
    const shariah = isShariahContract(campaign.contract_type);
    const days = daysLeft(campaign.funding_deadline);
    const { borrower, projected_return: projected } = campaign;
    const contract = DEFINITIONS[campaign.contract_type] ? (
        <Term definition={DEFINITIONS[campaign.contract_type]}>{campaign.contract_label}</Term>
    ) : (
        campaign.contract_label
    );

    return (
        <>
            <TextLink href={back.url} icon="arrow-left">
                {back.label}
            </TextLink>

            <Card
                title={campaign.title}
                description={[campaign.business_name, campaign.sector_label, campaign.purpose_label].filter(Boolean).join(' · ')}
                actions={
                    <>
                        <ContractBadge type={campaign.contract_type} />
                        <Badge tone="info" icon="clock">
                            {daysLeftText(days)}
                        </Badge>
                    </>
                }
            />

            <div className="grid gap-6 @bp-lg/shell:grid-cols-3">
                {/* Funding and the risk grade come first on phones and tablets (README, "Layout across devices"). */}
                <div className="order-first space-y-6 @bp-lg/shell:order-last">
                    <Card title="Funding">
                        <div className="space-y-5">
                            <FundingRing value={campaign.raised} max={campaign.amount_requested}>
                                <DescriptionList
                                    columns={1}
                                    items={[
                                        ['Raised', <Money key="raised" value={campaign.raised} />],
                                        ['Target', <Money key="target" value={campaign.amount_requested} />],
                                        ['Investors', investorsText(campaign.investors)],
                                    ]}
                                />
                            </FundingRing>
                            <DescriptionList items={[['Funding deadline', campaign.funding_deadline_label, true]]} columns={1} />
                            {pledge.allowed ? (
                                <Alert tone="info" role="note">
                                    {TEXT.fundingNote}
                                </Alert>
                            ) : (
                                <Alert tone="warning" role="note" title="Pledging is locked">
                                    {pledge.reason}
                                </Alert>
                            )}
                        </div>
                    </Card>

                    {/* Reserved for the AI-assisted risk grade (PB-25) and its explanation (PB-26). */}
                    <Card title="Risk grade" description="An AI-assisted estimate of the chance the borrower misses repayments.">
                        <RiskGradeCard
                            grade={risk?.grade}
                            probability={risk?.probability}
                            model={risk?.model}
                            title={TEXT.riskReservedTitle}
                            sentence={risk ? undefined : TEXT.riskReserved}
                        >
                            <EmptyState kind="search" title={TEXT.riskExplanationTitle} compact>
                                {TEXT.riskExplanationReserved}
                            </EmptyState>
                        </RiskGradeCard>
                    </Card>
                </div>

                <div className="space-y-6 @bp-lg/shell:col-span-2">
                    <Card title="Terms and expected return">
                        <div className="space-y-5">
                            <DescriptionList
                                items={[
                                    [campaign.contract_type === MURABAHAH ? 'Asset cost' : 'Amount', <Money key="amount" value={campaign.amount_requested} />],
                                    ['Tenure', `${campaign.tenure_months} months`, true],
                                    ['Contract', contract],
                                    ['Purpose', campaign.purpose_label],
                                    ...campaign.terms.map((term) => [term.label, term.value, term.mono]),
                                    [projected.label, projected.value, true],
                                ]}
                            />
                            <Alert tone="info" role="note">
                                {projected.note}
                            </Alert>
                            {shariah && (
                                <Alert tone="info" role="note">
                                    {SHARIAH_DISCLAIMER}
                                </Alert>
                            )}
                            <Alert tone="warning" role="note" title="Your money is at risk">
                                {TEXT.riskWarning}
                            </Alert>
                        </div>
                    </Card>

                    <Card title="About this campaign">
                        <DescriptionList
                            columns={1}
                            items={[
                                ['What the money is for', campaign.description],
                                ['Business background', campaign.business_background],
                            ]}
                        />
                    </Card>

                    <Card title="Borrower" description="Investors never see an individual borrower’s name, contact details or documents.">
                        <DescriptionList
                            items={[
                                ['Borrowing as', borrower.borrowing_as],
                                ...(borrower.business_name
                                    ? [
                                          ['Business name', borrower.business_name],
                                          ['Sector', borrower.sector_label],
                                          ['Years operating', borrower.years_operating, true],
                                      ]
                                    : []),
                            ]}
                        />
                    </Card>

                    <Card title={TEXT.repaymentTitle} description={repaymentHistory.summary}>
                        {repaymentHistory.fundedCampaigns > 0 && (
                            <DescriptionList
                                columns={4}
                                items={[
                                    ['Campaigns funded', repaymentHistory.fundedCampaigns, true],
                                    ['Instalments due so far', repaymentHistory.instalmentsDue, true],
                                    ['Paid on time', repaymentHistory.paidOnTime, true],
                                    ['Paid late', repaymentHistory.paidLate, true],
                                    ['Overdue now', repaymentHistory.overdue, true],
                                    ['Campaigns fully repaid', repaymentHistory.completedCampaigns, true],
                                ]}
                            />
                        )}
                    </Card>

                    {creditHistory && (
                        <Card title={TEXT.creditTitle} description={TEXT.creditNote}>
                            <DescriptionList columns={1} items={creditHistory.map((row) => [row.label, row.value, row.mono])} />
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

// The breadcrumb's first link is the marketplace page the investor came from, with its filters (PB-54).
CampaignShow.layout = (props) => ({
    title: props.campaign.title,
    breadcrumbs: [{ label: props.back.crumb, href: props.back.url }, { label: props.campaign.title }],
});
