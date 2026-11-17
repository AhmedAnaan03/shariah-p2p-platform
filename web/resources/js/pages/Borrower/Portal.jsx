import { usePage } from '@inertiajs/react';
import Button from '../../components/Button';
import Card from '../../components/Card';
import OnboardingStepper from '../../components/OnboardingStepper';

/**
 * Borrower portal home: the verification checklist (PB-09, PB-11) and, once verified, the way
 * into financing requests (PB-13). `checklist` comes from VerificationChecklist on the server.
 */
export default function BorrowerPortal({ checklist, requestCount }) {
    const { auth } = usePage().props;
    const missing = checklist.required_documents.filter((document) => !document.provided);

    const steps = [
        {
            title: 'Complete your profile',
            description: 'Personal details, and your business details if you are an SME.',
            done: checklist.has_profile,
            href: '/borrower/profile',
            actionLabel: 'Complete profile',
        },
        {
            title: 'Upload your documents',
            description: missing.length > 0 ? `Still needed: ${missing.map((d) => d.label).join(', ')}.` : 'All required documents uploaded.',
            done: checklist.complete,
            href: '/borrower/documents',
            actionLabel: 'Upload documents',
        },
        {
            title: 'Verification by an administrator',
            description:
                checklist.complete && auth.user.status === 'pending_verification'
                    ? 'Your account is in the review queue.'
                    : 'Starts once your profile and documents are complete.',
            done: auth.user.status === 'verified',
        },
    ];

    if (auth.user.status === 'verified') {
        return (
            <Card
                title="Raise financing"
                description={
                    requestCount === 0
                        ? 'Your account is verified. Submit a financing request and an administrator will review it.'
                        : `You have ${requestCount === 1 ? '1 financing request' : `${requestCount} financing requests`}.`
                }
                actions={
                    <>
                        {requestCount > 0 && (
                            <Button href="/borrower/campaigns" variant="secondary">
                                View your requests
                            </Button>
                        )}
                        <Button href="/borrower/campaigns/create" iconLeft="plus">
                            New request
                        </Button>
                    </>
                }
            />
        );
    }

    return (
        <Card title="Get verified" description="Three steps before you can raise financing.">
            <OnboardingStepper steps={steps} />
        </Card>
    );
}

BorrowerPortal.layout = {
    title: 'Overview',
    description: 'Get verified, then raise financing from investors.',
};
