import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ConfirmDialog from '../../../components/ConfirmDialog';
import ContractBadge from '../../../components/ContractBadge';
import DescriptionList from '../../../components/DescriptionList';
import DocumentList from '../../../components/DocumentList';
import EmptyState from '../../../components/EmptyState';
import Money from '../../../components/Money';
import RadioCards from '../../../components/RadioCards';
import StatusBadge from '../../../components/StatusBadge';
import Textarea from '../../../components/Textarea';
import TextLink from '../../../components/TextLink';
import { formatDate } from '../../../lib/format';

const DECISIONS = {
    approve: { label: 'Approve', icon: 'check-circle', description: 'Open it to investors until the funding deadline.' },
    reject: { label: 'Reject', icon: 'x-circle', description: 'Not suitable for investors. The borrower sees your reason.' },
    suspend: { label: 'Suspend', icon: 'pause-circle', description: 'Take it off the marketplace now. The borrower sees your reason.' },
};

const BUTTONS = {
    approve: 'Approve and notify borrower',
    reject: 'Reject and notify borrower',
    suspend: 'Suspend and notify borrower',
};

/**
 * Review one campaign (PB-15). The borrower's self-declared credit history is shown next to
 * the MMA credit report they attached (PB-14), so the two can be compared before deciding.
 * Every decision is confirmed first; rejecting and suspending need a reason.
 */
export default function AdminCampaignShow({ campaign, borrower, finances, creditHistory, creditReport, ratios, tenureOutsideTrainingRange, decisions, fundingDays, deadlineIfApproved, decideUrl }) {
    const form = useForm({ decision: '', reason: '' });
    const [confirming, setConfirming] = useState(false);
    const decision = form.data.decision;
    const needsReason = decision === 'reject' || decision === 'suspend';

    function send() {
        form.post(decideUrl, {
            preserveScroll: true,
            // On a validation error, close the dialog so the message under the field is visible.
            onError: () => setConfirming(false),
        });
    }

    function submit(event) {
        event.preventDefault();
        if (!decision) {
            send(); // the server says "Choose a decision."
        } else {
            setConfirming(true);
        }
    }

    return (
        <>
            <TextLink href="/admin/campaigns" icon="arrow-left">
                Back to campaigns
            </TextLink>

            <Card
                title={campaign.title}
                description={`${borrower.name} · ${campaign.purpose_label} · submitted ${formatDate(campaign.submitted_at)}`}
                actions={
                    <>
                        <ContractBadge type={campaign.contract_type} />
                        <StatusBadge status={campaign.status} />
                    </>
                }
            >
                {campaign.status === 'live' && <Alert tone="info">Open to investors until {campaign.funding_deadline_label}.</Alert>}
                {campaign.status_reason && (
                    <Alert tone="danger" role="note">
                        Reason given: {campaign.status_reason}
                    </Alert>
                )}
            </Card>

            <Card title="Credit history: check the answers against the report" description="The borrower’s own answers. Compare each one with the MMA Credit Information System report.">
                <div className="grid gap-6 @bp-md/shell:grid-cols-2">
                    <DescriptionList columns={1} items={creditHistory.map((row) => [row.label, row.value, row.mono])} />
                    {creditReport ? (
                        <DocumentList documents={[creditReport]} />
                    ) : (
                        <EmptyState kind="documents" title="No credit report attached" compact>
                            The answers can’t be checked. Ask the borrower for the report, or take that into account.
                        </EmptyState>
                    )}
                </div>
            </Card>

            <div className="grid gap-6 @bp-lg/shell:grid-cols-2">
                <Card title="Request">
                    <div className="space-y-5">
                        <DescriptionList
                            items={[
                                [campaign.contract_type === 'murabahah' ? 'Asset cost' : 'Amount', <Money key="amount" value={campaign.amount_requested} />],
                                ['Tenure', `${campaign.tenure_months} months`, true],
                                ['Purpose', campaign.purpose_label],
                                ['Contract', campaign.contract_label],
                                ...campaign.terms.map((term) => [term.label, term.value, term.mono]),
                            ]}
                        />
                        {tenureOutsideTrainingRange && (
                            <Alert tone="info" role="note">
                                The risk model learned from 36 and 60 month loans only. A {campaign.tenure_months} month tenure is outside that range, so treat its risk grade with care.
                            </Alert>
                        )}
                    </div>
                </Card>

                <Card title="Borrower" actions={<StatusBadge status={borrower.status} size="sm" />}>
                    <div className="space-y-5">
                        <DescriptionList
                            items={[
                                ['Name', borrower.name],
                                ['Email', borrower.email],
                                ['Borrowing as', borrower.borrowing_as],
                                ['Business name', borrower.business_name],
                            ]}
                        />
                        <TextLink href={borrower.verification_url} icon="identification-card">
                            Open the borrower’s verification record
                        </TextLink>
                    </div>
                </Card>

                <Card title="Finances">
                    <DescriptionList items={finances.map((row) => [row.label, row.value, row.mono])} />
                </Card>

                <Card title="What the risk model will see" description="Worked out from the figures on the left.">
                    <DescriptionList columns={1} items={ratios.map((row) => [row.label, row.value, row.mono])} />
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
            </div>

            {decisions.length > 0 && (
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
                            columns={decisions.length}
                            options={decisions.map((value) => ({ value, ...DECISIONS[value] }))}
                            value={decision}
                            onChange={(value) => form.setData('decision', value)}
                        />

                        {needsReason && (
                            <Textarea
                                id="reason"
                                label={decision === 'reject' ? 'Reason for rejecting' : 'Reason for suspending'}
                                hint="10 to 255 characters. The borrower sees this, so say what is wrong."
                                maxLength={255}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                error={form.errors.reason}
                            />
                        )}

                        <Button type="submit" variant={needsReason ? 'danger' : 'primary'} loading={form.processing && !confirming}>
                            {BUTTONS[decision] ?? 'Record decision'}
                        </Button>
                    </form>

                    <ConfirmDialog
                        open={confirming}
                        onClose={() => setConfirming(false)}
                        onConfirm={send}
                        tone={needsReason ? 'danger' : 'primary'}
                        title={`${DECISIONS[decision]?.label ?? 'Decide'} “${campaign.title}”?`}
                        consequence={
                            decision === 'approve'
                                ? `It will be listed in the marketplace for ${fundingDays} days, until ${deadlineIfApproved}. The borrower will be notified.`
                                : decision === 'reject'
                                  ? 'The borrower will be told the request was not approved, with your reason.'
                                  : 'It leaves the marketplace straight away and investors can no longer pledge. The borrower will be told, with your reason.'
                        }
                        rows={needsReason ? [['Reason', form.data.reason]] : undefined}
                        confirmLabel={BUTTONS[decision]}
                        loading={form.processing}
                    />
                </Card>
            )}
        </>
    );
}

AdminCampaignShow.layout = (props) => ({ title: `Review ${props.campaign.title}` });
