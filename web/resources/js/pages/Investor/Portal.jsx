import { usePage } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Card from '../../components/Card';
import PortalLayout from '../../components/PortalLayout';
import StepList from '../../components/StepList';

/**
 * Investor portal home: the steps to start investing (PB-10, PB-11). `pledge` is the answer
 * of the server's 'pledge' gate, with its reason when pledging is still locked.
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
            description: 'A short, plain-language explanation that your capital is at risk.',
            done: riskAcknowledged,
            href: '/investor/acknowledgement',
            actionLabel: 'Read and accept',
        },
        {
            title: 'Verification by an administrator',
            description: checklist.complete && auth.user.status === 'pending_verification'
                ? 'Your account is in the review queue.'
                : 'Starts once your profile and documents are complete.',
            done: auth.user.status === 'verified',
        },
    ];

    return (
        <PortalLayout title="Investor portal">
            <Card title="Investor portal" titleAs="h1" description="Complete these steps to start investing.">
                <div className="space-y-5">
                    {pledge.allowed ? (
                        <Alert variant="success" title="Pledging is unlocked">
                            You can pledge to campaigns once the marketplace opens in the next sprint.
                        </Alert>
                    ) : (
                        <Alert variant="warning" role="note" title="Pledging is locked">
                            {pledge.reason}
                        </Alert>
                    )}
                    <StepList steps={steps} />
                </div>
            </Card>
        </PortalLayout>
    );
}
