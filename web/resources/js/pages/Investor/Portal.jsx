import { usePage } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Card from '../../components/Card';
import ModePanel from '../../components/ModePanel';
import OnboardingStepper from '../../components/OnboardingStepper';

/**
 * Investor portal home: the steps to start investing (PB-10, PB-11) and the Shariah-only mode
 * panel (PB-51). `pledge` is the answer of the server's 'pledge' gate, with its reason when
 * pledging is still locked.
 */
export default function InvestorPortal({ checklist, riskAcknowledged, pledge }) {
    const { auth } = usePage().props;
    const missing = checklist.required_documents.filter((document) => !document.provided);

    const steps = [
        {
            title: 'Complete your profile',
            done: checklist.has_profile,
            href: '/investor/profile',
            actionLabel: 'Complete profile',
        },
        {
            title: 'Upload your ID document',
            description: missing.length > 0 ? `Still needed: ${missing.map((d) => d.label).join(', ')}.` : null,
            done: checklist.complete,
            href: '/investor/documents',
            actionLabel: 'Upload documents',
        },
        {
            title: 'Accept the risk acknowledgement',
            description: 'A short, plain-language explanation that you could lose some or all of your money.',
            done: riskAcknowledged,
            href: '/investor/acknowledgement',
            actionLabel: 'Read and accept',
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

    return (
        <>
            {pledge.allowed ? (
                <Alert tone="success" title="Pledging is unlocked">
                    You can pledge to campaigns once the marketplace opens in the next sprint.
                </Alert>
            ) : (
                <Alert tone="warning" role="note" title="Pledging is locked">
                    {pledge.reason}
                </Alert>
            )}

            <Card title="Start investing" description="Complete these steps to start investing.">
                <OnboardingStepper steps={steps} />
            </Card>

            <ModePanel />
        </>
    );
}

InvestorPortal.layout = { title: 'Overview' };
