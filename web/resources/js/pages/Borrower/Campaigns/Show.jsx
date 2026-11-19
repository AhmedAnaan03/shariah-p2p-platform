import Alert from '../../../components/Alert';
import Card from '../../../components/Card';
import ContractBadge from '../../../components/ContractBadge';
import DescriptionList from '../../../components/DescriptionList';
import DocumentList from '../../../components/DocumentList';
import Money from '../../../components/Money';
import StatusBadge from '../../../components/StatusBadge';
import TextLink from '../../../components/TextLink';
import { formatDate } from '../../../lib/format';

/**
 * One of the borrower's own requests (PB-13, PB-14): its review status and, after a decision,
 * the administrator's reason. The decision email links here.
 */
export default function CampaignShow({ campaign, finances, creditHistory, creditReport, back }) {
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
                        <StatusBadge status={campaign.status} />
                    </>
                }
            >
                <StatusAlert campaign={campaign} />
            </Card>

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

function StatusAlert({ campaign }) {
    switch (campaign.status) {
        case 'submitted':
            return <Alert tone="info">An administrator is reviewing your request. You’ll be told the outcome by email and here.</Alert>;
        case 'live':
            return <Alert tone="success">Your campaign is open to investors until {campaign.funding_deadline_label}.</Alert>;
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
