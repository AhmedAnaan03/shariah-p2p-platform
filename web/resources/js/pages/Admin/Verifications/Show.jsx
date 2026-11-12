import { useForm } from '@inertiajs/react';
import Alert from '../../../components/Alert';
import { StatusBadge } from '../../../components/Badge';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import DetailList from '../../../components/DetailList';
import DocumentList from '../../../components/DocumentList';
import PortalLayout from '../../../components/PortalLayout';
import RadioGroup from '../../../components/RadioGroup';
import Textarea from '../../../components/Textarea';
import TextLink from '../../../components/TextLink';

const DECISIONS = [
    { value: 'approve', label: 'Approve', description: 'The details match the documents. Verify this account.' },
    { value: 'reject', label: 'Reject', description: 'Something is wrong or missing. The user sees your reason.' },
];

/**
 * Review one account (PB-11): profile, documents (opened through the protected document route)
 * and the approve / reject decision.
 */
export default function VerificationShow({ applicant, profile, documents, missingDocuments, canDecide, canApprove, decideUrl }) {
    const form = useForm({ decision: '', reason: '' });

    function submit(event) {
        event.preventDefault();
        form.post(decideUrl, { preserveScroll: true });
    }

    const rejecting = form.data.decision === 'reject';

    return (
        <PortalLayout title={`Review ${applicant.name}`}>
            <p className="text-sm">
                <TextLink href="/admin/verifications">← Back to the queue</TextLink>
            </p>

            <Card
                title={applicant.name}
                titleAs="h1"
                description={`${applicant.role_label} · ${applicant.email} · registered ${new Date(applicant.registered_at).toLocaleDateString('en-GB')}`}
                actions={<StatusBadge status={applicant.status} />}
            >
                {applicant.status_reason && (
                    <Alert variant="error" role="note">
                        Reason given: {applicant.status_reason}
                    </Alert>
                )}
            </Card>

            <Card title="Profile">
                {profile ? <DetailList items={profile} /> : <Alert variant="warning">This user has not completed a profile.</Alert>}
            </Card>

            <Card title="Documents" description="Each document opens in a new tab. Compare it with the profile above.">
                <div className="space-y-4">
                    {missingDocuments.length > 0 && (
                        <Alert variant="warning" title="Missing required documents">
                            {missingDocuments.join(', ')}
                        </Alert>
                    )}
                    <DocumentList documents={documents} />
                </div>
            </Card>

            {canDecide && (
                <Card title="Decision">
                    <form onSubmit={submit} noValidate className="space-y-5">
                        {form.errors.decision && (
                            <Alert variant="error" role="alert">
                                {form.errors.decision}
                            </Alert>
                        )}

                        <RadioGroup
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
                                value={form.data.reason}
                                onChange={(e) => form.setData('reason', e.target.value)}
                                error={form.errors.reason}
                            />
                        )}

                        <Button type="submit" variant={rejecting ? 'danger' : 'primary'} processing={form.processing}>
                            {rejecting ? 'Reject and notify user' : 'Approve and notify user'}
                        </Button>
                    </form>
                </Card>
            )}
        </PortalLayout>
    );
}
