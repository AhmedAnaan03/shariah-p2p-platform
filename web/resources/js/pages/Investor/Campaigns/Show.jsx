import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../../components/Alert';
import Badge from '../../../components/Badge';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ConfirmDialog from '../../../components/ConfirmDialog';
import ContractBadge from '../../../components/ContractBadge';
import DescriptionList from '../../../components/DescriptionList';
import EmptyState from '../../../components/EmptyState';
import FundingRing from '../../../components/FundingRing';
import Money from '../../../components/Money';
import MoneyInput from '../../../components/MoneyInput';
import RiskGradeCard from '../../../components/RiskGradeCard';
import StatusBadge from '../../../components/StatusBadge';
import Table from '../../../components/Table';
import Term from '../../../components/Term';
import TextLink from '../../../components/TextLink';
import { CAMPAIGN_PAGE_TEXT as TEXT, investorsText } from '../../../lib/campaigns';
import { DEFINITIONS, isShariahContract, MURABAHAH, SHARIAH_DISCLAIMER } from '../../../lib/contracts';
import { daysLeft, daysLeftText, formatDate, formatMoney } from '../../../lib/format';
import { useModePreview } from '../../../lib/mode';
import { pledgeConsequence, pledgeLabel, pledgeProblem, toCents } from '../../../lib/wallet';

/**
 * A campaign's page for investors (PB-17): every disclosure an investor needs before pledging.
 * The purpose and business background, the borrower as investors may see them (never an
 * individual borrower's name), amount, tenure, contract and its terms, the projected return,
 * the borrower's credit history answers and repayment history on the platform, and funding.
 *
 * The risk grade area is reserved and labelled until the campaign is scored (PB-25, PB-26).
 * A Shariah-only investor can't reach a conventional campaign's page: the server sends them
 * back to the marketplace (PB-31). The exception is a campaign they pledged to before turning
 * Shariah-only on: it is their own investment, so it stays open to them with a note, shown in
 * conventional mode because its terms name an interest rate.
 *
 * Once a campaign is no longer open (funded, not funded, repaying, ...), only investors who
 * pledged to it can open it, and its status replaces the pledge form (`standing`).
 */
export default function CampaignShow({ campaign, creditHistory, repaymentHistory, risk, pledge, pledging, standing, myPledges, shariahOnlyNote, back }) {
    useModePreview(shariahOnlyNote ? 'conventional' : null);
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
                        {standing ? (
                            <StatusBadge status={standing.status} />
                        ) : (
                            <Badge tone="info" icon="clock">
                                {daysLeftText(days)}
                            </Badge>
                        )}
                    </>
                }
            />

            {shariahOnlyNote && (
                <Alert tone="info" role="note" title="Shown because you pledged to it">
                    {shariahOnlyNote}
                </Alert>
            )}

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
                            {standing ? (
                                <Alert tone={standing.tone} role="note" title={standing.title}>
                                    {standing.body}
                                </Alert>
                            ) : pledge.allowed ? (
                                <PledgeForm campaign={campaign} pledging={pledging} />
                            ) : (
                                <Alert tone="warning" role="note" title="Pledging is locked">
                                    {pledge.reason}
                                </Alert>
                            )}
                        </div>
                    </Card>

                    {myPledges.length > 0 && (
                        <Card title="Your pledges" description="Every pledge you made to this campaign and what happened to it.">
                            <Table
                                caption="Your pledges to this campaign"
                                columns={[
                                    { key: 'created_at', label: 'Date', render: (row) => formatDate(row.created_at) },
                                    { key: 'amount', label: 'Amount', align: 'right', mono: true, render: (row) => <Money value={row.amount} /> },
                                    { key: 'status', label: 'Status', render: (row) => <StatusBadge status={row.status} size="sm" /> },
                                ]}
                                rows={myPledges}
                            />
                        </Card>
                    )}

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

/**
 * PB-19: pledge from the wallet. The amount is checked as it is typed (the same rules as the
 * server), then a ConfirmDialog repeats the amount, what happens to it, and that the money is
 * simulated. After the pledge the page reloads its props, and the funding ring and figures
 * animate to the new total.
 */
function PledgeForm({ campaign, pledging }) {
    const form = useForm({ amount: null });
    const [confirming, setConfirming] = useState(false);
    const problem = pledgeProblem(form.data.amount, pledging);
    const label = pledgeLabel(form.data.amount);
    const hasMoney = toCents(pledging.balance) > 0;

    function review(event) {
        event.preventDefault();
        if (problem) {
            form.setError('amount', problem);
            return;
        }
        form.clearErrors();
        setConfirming(true);
    }

    function send() {
        form.post(pledging.url, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirming(false);
                form.reset();
            },
            onError: () => setConfirming(false),
        });
    }

    return (
        <form onSubmit={review} noValidate className="space-y-4">
            <DescriptionList
                columns={1}
                items={[
                    ['Your wallet', <Money key="balance" value={pledging.balance} />],
                    ['Left to raise', <Money key="remaining" value={pledging.remaining} />],
                    ...(toCents(pledging.mine) > 0 ? [['You have pledged', <Money key="mine" value={pledging.mine} />]] : []),
                ]}
            />
            {!hasMoney && (
                <Alert tone="info" role="note" action={<TextLink href={pledging.walletUrl}>Top up your wallet</TextLink>}>
                    Your wallet is empty. Top up first: it is simulated, no real money.
                </Alert>
            )}
            <MoneyInput
                id="amount"
                label="Amount to pledge"
                hint={`At least ${formatMoney(pledging.minimum)}.`}
                value={form.data.amount}
                onValueChange={(value) => form.setData('amount', value)}
                error={form.errors.amount}
                disabled={!hasMoney}
            />
            <Button type="submit" fullWidth iconLeft="hand-coins" disabled={!hasMoney}>
                {label}
            </Button>

            <ConfirmDialog
                open={confirming}
                onClose={() => setConfirming(false)}
                onConfirm={send}
                title={`Pledge to “${campaign.title}”?`}
                amount={form.data.amount}
                consequence={pledgeConsequence(campaign.funding_deadline_label)}
                rows={[
                    ['From', 'Your wallet'],
                    ['Into', 'The campaign’s escrow'],
                ]}
                simulated
                confirmLabel={`Confirm pledge of ${formatMoney(form.data.amount ?? 0)}`}
                loading={form.processing}
            />
        </form>
    );
}

// The breadcrumb's first link is the marketplace page the investor came from, with its filters (PB-54).
CampaignShow.layout = (props) => ({
    title: props.campaign.title,
    breadcrumbs: [{ label: props.back.crumb, href: props.back.url }, { label: props.campaign.title }],
});
