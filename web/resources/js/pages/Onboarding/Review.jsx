import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import Card from '../../components/Card';
import DescriptionList from '../../components/DescriptionList';
import DocumentList from '../../components/DocumentList';
import OnboardingFrame from '../../components/OnboardingFrame';
import { formatDate } from '../../lib/format';

// Shown in mono so IDs and numbers line up.
const MONO_FIELDS = ['Phone', 'National ID card number', 'Business registration number', 'Years operating', 'Declared annual income', 'Declared annual revenue'];

/**
 * The last onboarding step, review and submit (PB-53): everything the administrator will check,
 * with a link back to each step, and "Submit for verification". After submitting, the user
 * stays here and sees that the account is waiting for review. A rejected account submits again
 * once the user has fixed what the administrator asked for.
 */
export default function OnboardingReview({ wizard, details, documents, complete, submittedAt, status, editLinks, submitUrl }) {
    const form = useForm({});
    const waiting = status === 'pending_verification' && submittedAt;
    const canSubmit = complete && !wizard.locked && !waiting;

    function submit() {
        form.post(submitUrl, { preserveScroll: true });
    }

    return (
        <OnboardingFrame
            wizard={wizard}
            actions={
                canSubmit && (
                    <Button iconRight="arrow-right" loading={form.processing} onClick={submit}>
                        {status === 'rejected' ? 'Submit again for verification' : 'Submit for verification'}
                    </Button>
                )
            }
        >
            <StatusAlert status={status} complete={complete} submittedAt={submittedAt} />

            {form.errors.submit && (
                <Alert tone="danger" role="alert">
                    {form.errors.submit}
                </Alert>
            )}

            <div className="grid gap-6 @bp-lg/shell:grid-cols-2">
                <Card
                    title="Your details"
                    actions={
                        !wizard.locked && (
                            <Button href={editLinks.profile} size="sm" variant="secondary" iconLeft="pencil-simple">
                                Edit details
                            </Button>
                        )
                    }
                >
                    {details ? (
                        <DescriptionList items={details.map((item) => [item.label, item.value, MONO_FIELDS.includes(item.label)])} />
                    ) : (
                        <Alert tone="warning">You have not completed your details yet.</Alert>
                    )}
                    {!wizard.locked && (
                        <div className="mt-4">
                            <Button href={editLinks.address} size="sm" variant="ghost" iconLeft="map-pin">
                                Edit address
                            </Button>
                        </div>
                    )}
                </Card>

                <Card
                    title="Documents"
                    actions={
                        !wizard.locked && (
                            <Button href={editLinks.documents} size="sm" variant="secondary" iconLeft="pencil-simple">
                                Change documents
                            </Button>
                        )
                    }
                >
                    <DocumentList documents={documents} emptyMessage="Upload your documents in the documents step." />
                </Card>
            </div>
        </OnboardingFrame>
    );
}

function StatusAlert({ status, complete, submittedAt }) {
    if (status === 'verified') {
        return (
            <Alert tone="success" title="Your account is verified">
                These details were checked by an administrator, so they can no longer be changed.
            </Alert>
        );
    }

    if (status === 'pending_verification' && submittedAt) {
        return (
            <Alert tone="success" title="Submitted for verification">
                You submitted your account on {formatDate(submittedAt)}. An administrator will check your details and documents, usually
                within two working days. You can still change anything until then.
            </Alert>
        );
    }

    if (!complete) {
        return (
            <Alert tone="warning" title="Some steps are not finished">
                Finish every step above before you submit your account for verification.
            </Alert>
        );
    }

    return (
        <Alert tone="info" role="note" title="Ready to submit">
            Check everything below. When you submit, an administrator compares your details with your documents.
        </Alert>
    );
}

OnboardingReview.layout = {
    title: 'Set up your account',
    description: 'A few steps, saved as you go. You can come back and finish later.',
};
