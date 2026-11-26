import { router } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../../components/Alert';
import Badge from '../../../components/Badge';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ConfirmDialog from '../../../components/ConfirmDialog';
import ChartFrame from '../../../components/ChartFrame';
import ContractBadge from '../../../components/ContractBadge';
import DescriptionList from '../../../components/DescriptionList';
import BusinessReportTable from '../../../components/BusinessReportTable';
import DocumentList from '../../../components/DocumentList';
import EmptyState from '../../../components/EmptyState';
import FundingRing from '../../../components/FundingRing';
import Money from '../../../components/Money';
import RepaymentScheduleChart from '../../../components/RepaymentScheduleChart';
import RepaymentTimeline from '../../../components/RepaymentTimeline';
import StatusBadge from '../../../components/StatusBadge';
import TextLink from '../../../components/TextLink';
import { investorsText } from '../../../lib/campaigns';
import { formatDate, formatMoney } from '../../../lib/format';
import { chartData, chartLegend, chartTable, isCapitalReturn, SCHEDULE_STATES, scheduleTakeaway, timelineItems } from '../../../lib/schedule';
import { REPORT_TEXT } from '../../../lib/mudarabah';
import { toCents } from '../../../lib/wallet';

/**
 * One of the borrower's own requests (PB-13, PB-14): its review status and, after a decision,
 * the administrator's reason. The decision email links here.
 *
 * Once it is funded and paid out (PB-34, PB-28), the schedule: a summary, the months as a chart
 * of capital and return, and a timeline of every row. A Mudarabah's schedule is planned capital
 * returns, not a debt, and every label for it comes from the server already worded that way.
 *
 * PB-35: a conventional or Murabahah borrower pays the next instalment, or pays off early, from
 * here, each confirmed in a dialog that shows the amount and how it is worked out. PB-29: a
 * Mudarabah business sees its capital account and reports each period instead.
 */
export default function CampaignShow({ campaign, funding, schedule, repayment, mudarabah, finances, creditHistory, creditReport, back }) {
    return (
        <>
            <TextLink href={back.url} icon="arrow-left">
                {back.label}
            </TextLink>

            <Card
                title={campaign.title}
                description={`${campaign.purpose_label} · submitted ${formatDate(campaign.submitted_at)}`}
                actions={
                    <>
                        <ContractBadge type={campaign.contract_type} />
                        <StatusBadge status={campaign.badge_status} />
                    </>
                }
            >
                <StatusAlert campaign={campaign} schedule={schedule} />
            </Card>

            {repayment && <RepaymentCard repayment={repayment} />}

            {mudarabah && <BusinessReportsCard mudarabah={mudarabah} />}

            {schedule && <ScheduleCard schedule={schedule} />}

            {/* PB-20: funding progress, updated with every pledge. Once paid out, the schedule replaces it. */}
            {funding && !schedule && (
                <Card title="Funding" description="Pledges are held in escrow until the campaign closes. All or nothing: if the target isn't reached by the deadline, every pledge goes back to its investor.">
                    <FundingRing value={funding.raised} max={campaign.amount_requested}>
                        <DescriptionList
                            columns={1}
                            items={[
                                ['Raised', <Money key="raised" value={funding.raised} />],
                                ['Target', <Money key="target" value={campaign.amount_requested} />],
                                ['Left to raise', <Money key="left" value={funding.remaining} />],
                                ['Investors', investorsText(funding.investors)],
                            ]}
                        />
                    </FundingRing>
                </Card>
            )}

            <div className="grid gap-6 @bp-lg/shell:grid-cols-2">
                <Card title="Request">
                    <DescriptionList
                        items={[
                            [campaign.contract_type === 'murabahah' ? 'Asset cost' : 'Amount', <Money key="amount" value={campaign.amount_requested} />],
                            ['Tenure', `${campaign.tenure_months} months`, true],
                            ['Purpose', campaign.purpose_label],
                            ['Contract', campaign.contract_label],
                            ...campaign.terms.map((term) => [term.label, term.value, term.mono]),
                        ]}
                    />
                </Card>

                <Card title="Your finances">
                    <DescriptionList items={finances.map((row) => [row.label, row.value, row.mono])} />
                </Card>

                <Card title="What the money is for" className="@bp-lg/shell:col-span-2">
                    <DescriptionList
                        columns={1}
                        items={[
                            ['Description', campaign.description],
                            ['Business background', campaign.business_background],
                        ]}
                    />
                </Card>

                <Card title="Your credit history" description="Your own answers, checked against your MMA credit report.">
                    <DescriptionList columns={1} items={creditHistory.map((row) => [row.label, row.value, row.mono])} />
                </Card>

                <Card title="MMA credit report">
                    <DocumentList documents={creditReport ? [creditReport] : []} emptyTitle="No credit report attached" emptyMessage="You chose not to attach one to this request." />
                </Card>
            </div>
        </>
    );
}

/**
 * PB-35: pay the next instalment, or everything left at once. Each payment is confirmed first,
 * and the dialog's button shows a spinner and can't be pressed again while it is sent; the server
 * also refuses a second payment of the same instalment, so a double click never pays twice.
 */
function RepaymentCard({ repayment }) {
    const [confirming, setConfirming] = useState(null);
    const [processing, setProcessing] = useState(false);
    const { next, payoff } = repayment;

    function send(url, data) {
        setProcessing(true);
        router.post(url, data, {
            preserveScroll: true,
            onFinish: () => {
                setProcessing(false);
                setConfirming(null);
            },
        });
    }

    if (repayment.completed || !next) {
        return (
            <Card title="Repayments">
                <Alert tone="success" title="Paid in full" role="note">
                    Every instalment of this campaign is paid, and the investors have received their shares. {repayment.noFeesNote}
                </Alert>
            </Card>
        );
    }

    const short = toCents(repayment.balance) < toCents(next.amount);
    const state = SCHEDULE_STATES[next.state] ?? SCHEDULE_STATES.upcoming;

    return (
        <Card title="Repayments" description={repayment.note}>
            <div className="space-y-5">
                <DescriptionList
                    columns={4}
                    items={[
                        ['Your wallet', <Money key="balance" value={repayment.balance} />],
                        ['Next instalment', `${next.label}, ${next.dateLabel}`],
                        ['Amount', <Money key="amount" value={next.amount} />],
                        [
                            'Status',
                            <Badge key="state" tone={state.tone} icon={state.icon} size="sm">
                                {next.stateLabel}
                            </Badge>,
                        ],
                    ]}
                />
                {short && (
                    <Alert tone="warning" role="note" action={<TextLink href="/borrower/wallet">Top up your wallet</TextLink>}>
                        Your wallet holds less than this instalment. Top up first: it is simulated, no real money.
                    </Alert>
                )}
                <div className="flex flex-wrap gap-3">
                    <Button iconLeft="coins" disabled={short} onClick={() => setConfirming('next')}>
                        Pay {next.label.toLowerCase()}: {formatMoney(next.amount)}
                    </Button>
                    {payoff && (
                        <Button variant="secondary" onClick={() => setConfirming('payoff')}>
                            Pay off in full: {formatMoney(payoff.total)}
                        </Button>
                    )}
                </div>
                <Alert tone="info" role="note">
                    {repayment.noFeesNote}
                </Alert>
            </div>

            <ConfirmDialog
                open={confirming === 'next'}
                onClose={() => setConfirming(null)}
                onConfirm={() => send(repayment.payUrl, { schedule_id: next.id })}
                title={`Pay ${next.label.toLowerCase()}?`}
                amount={next.amount}
                rows={[
                    ['From', 'Your wallet'],
                    ['Capital', formatMoney(next.capital)],
                    [repayment.returnLabel, formatMoney(next.return)],
                    ['Due', next.dateLabel],
                ]}
                consequence="The money leaves your wallet and is shared out to the investors straight away, in proportion to what each pledged."
                simulated
                confirmLabel={`Confirm payment of ${formatMoney(next.amount)}`}
                loading={processing}
            />
            {payoff && (
                <ConfirmDialog
                    open={confirming === 'payoff'}
                    onClose={() => setConfirming(null)}
                    onConfirm={() => send(payoff.url, { amount: payoff.total })}
                    title="Pay off everything that is left?"
                    amount={payoff.total}
                    rows={payoff.rows}
                    consequence={payoff.explanation}
                    simulated
                    confirmLabel={`Confirm payoff of ${formatMoney(payoff.total)}`}
                    loading={processing}
                />
            )}
        </Card>
    );
}

/**
 * PB-29: a Mudarabah's capital account, the next period to report and every report so far.
 * Every label comes from the server (MudarabahPresenter), worded so nothing reads as a debt.
 */
function BusinessReportsCard({ mudarabah }) {
    const { next, account, reports } = mudarabah;

    return (
        <Card title={REPORT_TEXT.cardTitle} description={mudarabah.note}>
            <div className="space-y-5">
                <DescriptionList columns={4} items={account.map((row) => [row.label, <Money key={row.key} value={row.value} />])} />
                {next &&
                    (next.allowed ? (
                        <div className="flex flex-wrap items-center gap-3">
                            <Button href={next.createUrl} iconLeft="clipboard-text">
                                Report {next.label.toLowerCase()}
                            </Button>
                            <p className="text-body-sm text-text-secondary">Planned date: {next.dateLabel}. Your wallet: {formatMoney(mudarabah.balance)}.</p>
                        </div>
                    ) : (
                        <Alert tone="info" role="note" title={`Next: ${next.label}, ${next.dateLabel}`}>
                            {next.reason}
                        </Alert>
                    ))}
                {reports.length > 0 ? (
                    <BusinessReportTable reports={reports} />
                ) : (
                    <EmptyState kind="documents" title={REPORT_TEXT.noReportsTitle} compact>
                        {REPORT_TEXT.noReports}
                    </EmptyState>
                )}
            </div>
        </Card>
    );
}

/** PB-34: the schedule's figures, its chart and its timeline. */
function ScheduleCard({ schedule }) {
    const { labels } = schedule;
    const capitalReturn = isCapitalReturn(schedule);
    const summary = capitalReturn
        ? [
              ['Paid out on', schedule.disbursedOnLabel, true],
              [labels.rows, schedule.count, true],
              ['Capital planned to be returned', <Money key="capital" value={schedule.capitalTotal} />],
              ['First planned date', schedule.firstDateLabel, true],
          ]
        : [
              ['Paid out on', schedule.disbursedOnLabel, true],
              [labels.rows, schedule.count, true],
              ['Capital', <Money key="capital" value={schedule.capitalTotal} />],
              [labels.return, <Money key="return" value={schedule.returnTotal} />],
              ['Total to repay', <Money key="total" value={schedule.total} />],
              ['First due date', schedule.firstDateLabel, true],
          ];

    return (
        <Card title={labels.title} description={schedule.note}>
            <div className="space-y-6">
                <DescriptionList columns={capitalReturn ? 4 : 3} items={summary} />
                <div className="grid gap-6 @bp-lg/shell:grid-cols-5">
                    <div className="@bp-lg/shell:col-span-3">
                        <ChartFrame title={labels.chartTitle} takeaway={scheduleTakeaway(schedule)} legend={chartLegend(labels)} table={chartTable(schedule)}>
                            <RepaymentScheduleChart data={chartData(schedule.rows)} capitalLabel={labels.capital} returnLabel={labels.return} />
                        </ChartFrame>
                    </div>
                    <div className="@bp-lg/shell:col-span-2">
                        <RepaymentTimeline items={timelineItems(schedule.rows)} label={labels.title} />
                    </div>
                </div>
            </div>
        </Card>
    );
}

function StatusAlert({ campaign, schedule }) {
    switch (campaign.status) {
        case 'submitted':
            return <Alert tone="info">An administrator is reviewing your request. You’ll be told the outcome by email and here.</Alert>;
        case 'live':
            return <Alert tone="success">Your campaign is open to investors until {campaign.funding_deadline_label}.</Alert>;
        case 'funded':
            return (
                <Alert tone="success" title="Fully funded" role="note">
                    Investors pledged the full amount. It is being paid out to your wallet, and your schedule will appear here.
                </Alert>
            );
        case 'repaying':
            return isCapitalReturn(schedule) ? (
                <Alert tone="success" title="Funded and paid out" role="note">
                    Investors’ capital of {formatMoney(campaign.amount_requested)} was paid into your wallet on {schedule.disbursedOnLabel}. The first planned capital return is on {schedule.firstDateLabel}.
                </Alert>
            ) : (
                <Alert tone="success" title="Funded and paid out" role="note">
                    {formatMoney(campaign.amount_requested)} was paid into your wallet on {schedule?.disbursedOnLabel}. Your first instalment is due on {schedule?.firstDateLabel}.
                </Alert>
            );
        case 'completed':
            return isCapitalReturn(schedule) ? (
                <Alert tone="success" title={REPORT_TEXT.settledTitle} role="note">
                    {REPORT_TEXT.settled}
                </Alert>
            ) : (
                <Alert tone="success" title="Paid in full" role="note">
                    Every instalment is paid. There were no late fees or penalty charges.
                </Alert>
            );
        case 'failed':
            return (
                <Alert tone="danger" title="Not funded" role="note">
                    The funding deadline passed before the target was reached, so every pledge was returned to its investor. You can submit a new request.
                </Alert>
            );
        case 'rejected':
            return (
                <Alert tone="danger" title="Not approved" role="note">
                    Reason given: {campaign.status_reason}
                </Alert>
            );
        case 'suspended':
            return (
                <Alert tone="danger" title="Suspended" role="note">
                    Investors can no longer see or pledge to this campaign. Reason given: {campaign.status_reason}
                </Alert>
            );
        default:
            return null;
    }
}

// The breadcrumb's first link is the page the borrower came from (PB-54).
CampaignShow.layout = (props) => ({
    title: props.campaign.title,
    breadcrumbs: [{ label: props.back.crumb, href: props.back.url }, { label: props.campaign.title }],
});
