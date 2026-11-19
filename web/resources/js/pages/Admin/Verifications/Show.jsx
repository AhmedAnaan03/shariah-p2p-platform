import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../../components/Alert';
import BulletList from '../../../components/BulletList';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import ConfirmDialog from '../../../components/ConfirmDialog';
import DescriptionList from '../../../components/DescriptionList';
import DocumentList from '../../../components/DocumentList';
import RadioCards from '../../../components/RadioCards';
import StatusBadge from '../../../components/StatusBadge';
import Textarea from '../../../components/Textarea';
import TextLink from '../../../components/TextLink';
import { formatDate } from '../../../lib/format';

const DECISIONS = [
    { value: 'approve', label: 'Approve', icon: 'check-circle', description: 'The details match the documents. Verify this account.' },
    { value: 'reject', label: 'Reject', icon: 'x-circle', description: 'Something is wrong or missing. The user sees your reason.' },
];

// Shown in mono so IDs and numbers line up.
const MONO_FIELDS = ['Phone', 'National ID card number', 'Business registration number', 'Years operating', 'Declared annual income', 'Declared annual revenue'];

/**
 * Review one account (PB-11): profile, documents (opened through the protected document route)
 * and the approve / reject decision. Rejecting is a Danger action, so it asks for confirmation
 * first (PB-51).
 */
export default function VerificationShow({ applicant, profile, documents, missingDocuments, missingSteps = [], canDecide, canApprove, decideUrl, back }) {
    // After the decision the server goes back to where the record was opened from (PB-54).
    const form = useForm({ decision: '', reason: '', return_to: back.fromLink ? back.url : '' });
    const [confirming, setConfirming] = useState(false);
    const rejecting = form.data.decision === 'reject';

    function send() {
        form.post(decideUrl, {
            preserveScroll: true,
            // On a validation error, close the dialog so the message under the field is visible.
            onError: () => setConfirming(false),
        });
    }

    function submit(event) {
        event.preventDefault();
        if (rejecting) {
            setConfirming(true);
        } else {
            send();
        }
    }

    return (
        <>
            <TextLink href={back.url} icon="arrow-left">
                {back.label}
            </TextLink>

            <Card
                title={applicant.name}
                description={`${applicant.role_label} · ${applicant.email} · registered ${formatDate(applicant.registered_at)}`}
                actions={<StatusBadge status={applicant.status} />}
            >
                {applicant.status_reason && (
                    <Alert tone="danger" role="note">
                        Reason given: {applicant.status_reason}
                    </Alert>
                )}
            </Card>

            <div className="grid gap-6 @bp-lg/shell:grid-cols-2">
                <Card title="Profile">
                    {profile ? (
                        <DescriptionList items={profile.map((item) => [item.label, item.value, MONO_FIELDS.includes(item.label)])} />
                    ) : (
                        <Alert tone="warning">This user has not completed a profile.</Alert>
                    )}
                </Card>

                <Card title="Documents" description="Each document opens in a new tab. Compare it with the profile.">
                    <div className="space-y-4">
                        {missingDocuments.length > 0 && (
                            <Alert tone="warning" title="Missing required documents">
                                {missingDocuments.join(', ')}
                            </Alert>
                        )}
                        <DocumentList documents={documents} />
                    </div>
                </Card>
            </div>

            {missingSteps.length > 0 && (
                <Alert tone="warning" title="Account setup is not finished">
                    <BulletList items={missingSteps} />
                </Alert>
            )}

            {canDecide && (
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
                            options={canApprove ? DECISIONS : DECISIONS.filter((option) => option.value === 'reject')}
                            value={form.data.decision}
                            onChange={(value) => form.setData('decision', value)}
                        />

                        {rejecting && (
                            <Textarea
                                id="reason"
                                label="Reason for rejecting"
                                hint="10 to 255 characters. Say what the user needs to fix, e.g. “The ID card photo is too blurred to read.”"
                                maxLength={255}
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                error={form.errors.reason}
                            />
                        )}

                        <Button type="submit" variant={rejecting ? 'danger' : 'primary'} loading={form.processing && !confirming}>
                            {rejecting ? 'Reject and notify user' : 'Approve and notify user'}
                        </Button>
                    </form>

                    <ConfirmDialog
                        open={confirming}
                        onClose={() => setConfirming(false)}
                        onConfirm={send}
                        tone="danger"
                        title={`Reject ${applicant.name}?`}
                        consequence={`${applicant.name} will be told the account was not approved, with your reason. They can then fix their details or documents and submit again.`}
                        rows={[['Reason', form.data.reason]]}
                        confirmLabel="Reject and notify user"
                        loading={form.processing}
                    />
                </Card>
            )}
        </>
    );
}

// The breadcrumb's first link is the page the admin came from: the queue, or a campaign review (PB-54).
VerificationShow.layout = (props) => ({
    title: `Review ${props.applicant.name}`,
    breadcrumbs: [{ label: props.back.crumb, href: props.back.url }, { label: props.applicant.name }],
});
