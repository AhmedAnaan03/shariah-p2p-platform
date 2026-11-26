import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ConfirmDialog from '../../../components/ConfirmDialog';
import DescriptionList from '../../../components/DescriptionList';
import DocumentList from '../../../components/DocumentList';
import Money from '../../../components/Money';
import RadioCards from '../../../components/RadioCards';
import StatusBadge from '../../../components/StatusBadge';
import Textarea from '../../../components/Textarea';
import TextLink from '../../../components/TextLink';
import { formatMoney } from '../../../lib/format';
import { withReturn } from '../../../lib/returnUrl';

const DECISIONS = {
    approve: { label: 'Approve', description: 'The figures are right. The money is shared out at once.', icon: 'check-circle' },
    reject: { label: 'Reject', description: 'Something is wrong. The business sees your reason and can submit a new report.', icon: 'x-circle' },
};

/**
 * Review one Mudarabah business report (PB-29). The figures, the supporting document and the
 * capital account sit next to a preview of what each decision would do (worked out on the server
 * by MudarabahSettlement, the same code approval runs). For a loss the administrator also decides
 * its cause: a genuine business loss, borne by the investors, or negligence or misconduct, which
 * the business is responsible for and which needs a reason. Every decision is confirmed first.
 */
export default function AdminReportShow({ report, campaign, final, account, businessBalance, isLoss, previews, note, causes, decideUrl, back }) {
    const form = useForm({ decision: '', loss_cause: '', reason: '' });
    const [confirming, setConfirming] = useState(false);
    const { decision, loss_cause: cause } = form.data;
    const needsReason = decision === 'reject' || (decision === 'approve' && cause === 'negligence');
    const preview = isLoss ? previews[cause] : previews.approve;
    const open = report.status === 'submitted';

    function send() {
        form.post(decideUrl, { preserveScroll: true, onError: () => setConfirming(false), onSuccess: () => setConfirming(false) });
    }

    function submit(event) {
        event.preventDefault();
        if (!decision || (decision === 'approve' && isLoss && !cause)) {
            send(); // the server says what is missing
        } else {
            setConfirming(true);
        }
    }

    return (
        <>
            <TextLink href={back.url} icon="arrow-left">
                {back.label}
            </TextLink>

            <Card
                title={`${campaign.title}: ${report.periodLabel}`}
                description={`${campaign.business} · planned date ${report.plannedDateLabel} · submitted ${report.submittedLabel}`}
                actions={<StatusBadge status={report.status}>{report.statusLabel}</StatusBadge>}
            >
                <div className="space-y-4">
                    <DescriptionList
                        columns={4}
                        items={[
                            ['Revenue', <Money key="revenue" value={report.revenue} />],
                            ['Costs', <Money key="costs" value={report.costs} />],
                            [report.resultLabel, <Money key="result" value={report.result} />],
                            ['Capital returned', <Money key="capital" value={report.capitalReturned} />],
                            ['Profit-sharing ratio', campaign.ratio],
                            ['Business’s wallet', <Money key="balance" value={businessBalance} />],
                        ]}
                    />
                    {final && (
                        <Alert tone="info" role="note" title="Final period">
                            This report must settle the capital: everything still invested is returned, less any genuine business loss.
                        </Alert>
                    )}
                    <TextLink href={withReturn(campaign.url, back.url)}>Open the campaign review</TextLink>
                </div>
            </Card>

            <div className="grid gap-6 @bp-lg/shell:grid-cols-2">
                <Card title="Capital account" description={note}>
                    <DescriptionList columns={2} items={account.map((row) => [row.label, <Money key={row.key} value={row.value} />])} />
                </Card>

                <Card title="Supporting document">
                    <DocumentList documents={report.document ? [report.document] : []} emptyTitle="No document attached" emptyMessage="The business chose not to attach one." />
                </Card>
            </div>

            {!open ? (
                <Card title="Decision">
                    <DescriptionList
                        columns={2}
                        items={[
                            ['Decision', report.statusLabel],
                            ['Decided on', report.reviewedLabel],
                            ...(report.causeLabel ? [['Loss cause', report.causeLabel]] : []),
                            ...(report.reason ? [['Reason given', report.reason]] : []),
                            ...report.outcome.map(([label, value]) => [label, <Money key={label} value={value} />]),
                        ]}
                    />
                </Card>
            ) : (
                <Card title="Decision" className="max-w-3xl">
                    <form onSubmit={submit} noValidate className="space-y-5">
                        {form.errors.decision && (
                            <Alert tone="danger" role="alert">
                                {form.errors.decision}
                            </Alert>
                        )}

                        <RadioCards
                            name="decision"
                            legend="Your decision"
                            columns={2}
                            options={Object.entries(DECISIONS).map(([value, option]) => ({ value, ...option }))}
                            value={decision}
                            onChange={(value) => form.setData('decision', value)}
                        />

                        {decision === 'approve' && isLoss && (
                            <RadioCards
                                name="loss_cause"
                                legend="What caused the loss?"
                                columns={2}
                                options={causes.map((option) => ({ ...option, icon: option.value === 'business' ? 'scales' : 'warning-circle' }))}
                                value={cause}
                                onChange={(value) => form.setData('loss_cause', value)}
                                error={form.errors.loss_cause}
                            />
                        )}

                        {decision === 'approve' && preview && (
                            <Card title="What approving does" titleAs="h3">
                                {preview.allowed ? (
                                    <DescriptionList columns={2} items={preview.rows.map(([label, value]) => [label, <Money key={label} value={value} />])} />
                                ) : (
                                    <Alert tone="warning" role="note" title="This can’t be approved">
                                        {preview.reason} Reject it with a reason so the business can submit a new report.
                                    </Alert>
                                )}
                            </Card>
                        )}

                        {(needsReason || decision === 'approve') && (
                            <Textarea
                                id="reason"
                                label={decision === 'reject' ? 'Reason for rejecting' : needsReason ? 'Reason for finding negligence or misconduct' : 'Note'}
                                hint={needsReason ? '10 to 1,000 characters. The business sees this, and it is recorded in the audit log.' : 'Up to 1,000 characters. The business sees it.'}
                                maxLength={1000}
                                optional={!needsReason}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                error={form.errors.reason}
                            />
                        )}

                        <Button type="submit" variant={decision === 'reject' ? 'danger' : 'primary'} loading={form.processing && !confirming} disabled={decision === 'approve' && preview && !preview.allowed}>
                            {decision === 'reject' ? 'Reject report' : decision === 'approve' ? 'Approve report' : 'Record decision'}
                        </Button>
                    </form>

                    <ConfirmDialog
                        open={confirming}
                        onClose={() => setConfirming(false)}
                        onConfirm={send}
                        tone={decision === 'reject' ? 'danger' : 'primary'}
                        title={decision === 'reject' ? `Reject the report for ${report.periodLabel.toLowerCase()}?` : `Approve the report for ${report.periodLabel.toLowerCase()}?`}
                        consequence={
                            decision === 'reject'
                                ? 'No money moves. The business is told your reason and can submit a new report for this period.'
                                : 'The money moves at once from the business’s wallet and is shared out to the investors. The business and the investors are told. This can’t be undone.'
                        }
                        rows={decision === 'reject' || needsReason ? [['Reason', form.data.reason]] : preview?.rows.map(([label, value]) => [label, formatMoney(value)])}
                        confirmLabel={decision === 'reject' ? 'Reject and notify the business' : 'Approve and share out'}
                        loading={form.processing}
                    />
                </Card>
            )}
        </>
    );
}

AdminReportShow.layout = (props) => ({
    title: `Business report: ${props.campaign.title}`,
    breadcrumbs: [{ label: props.back.crumb, href: props.back.url }, { label: props.report.periodLabel }],
});
