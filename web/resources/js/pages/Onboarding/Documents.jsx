import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Button from '../../components/Button';
import DocumentSlot from '../../components/DocumentSlot';
import OnboardingFrame from '../../components/OnboardingFrame';

/**
 * Onboarding step 3, documents (PB-53): one fixed slot per document the user needs, decided by
 * the kind of user (individual borrower, SME borrower or investor). Each slot uploads on its
 * own; Continue moves on once every slot is filled. There is no way to add other documents.
 */
export default function OnboardingDocuments({ wizard, slots, uploadUrl, continueUrl, maxBytes, tooLargeMessage }) {
    const form = useForm({});
    const filled = slots.filter((slot) => slot.document && slot.document.status !== 'rejected').length;

    function submit() {
        form.put(continueUrl, { preserveScroll: true });
    }

    // Each slot is its own upload form, so the step itself is not a form (forms can't nest):
    // Continue is a plain button that asks the server to move on.
    return (
        <OnboardingFrame
            wizard={wizard}
            actions={
                !wizard.locked && (
                    <Button iconRight="arrow-right" loading={form.processing} onClick={submit}>
                        Continue
                    </Button>
                )
            }
        >
            <Alert tone="info" role="note" title={`${filled} of ${slots.length} documents uploaded`}>
                Documents are stored privately and encrypted. Only you and the platform’s administrators can open them.
            </Alert>

            {form.errors.documents && (
                <Alert tone="danger" role="alert">
                    {form.errors.documents}
                </Alert>
            )}

            <ul className="space-y-4" aria-label="Document slots">
                {slots.map((slot) => (
                    <DocumentSlot
                        key={slot.type}
                        slot={slot}
                        uploadUrl={uploadUrl}
                        maxBytes={maxBytes}
                        tooLargeMessage={tooLargeMessage}
                        locked={wizard.locked}
                    />
                ))}
            </ul>
        </OnboardingFrame>
    );
}

OnboardingDocuments.layout = {
    title: 'Set up your account',
    description: 'A few steps, saved as you go. You can come back and finish later.',
};
