import { usePage } from '@inertiajs/react';
import Card from '../../components/Card';
import PortalLayout from '../../components/PortalLayout';
import StepList from '../../components/StepList';

/**
 * Borrower portal home: the verification checklist (PB-09, PB-11). `checklist` comes from
 * VerificationChecklist on the server.
 */
export default function BorrowerPortal({ checklist }) {
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
            description: checklist.complete && auth.user.status === 'pending_verification'
                ? 'Your account is in the review queue.'
                : 'Starts once your profile and documents are complete.',
            done: auth.user.status === 'verified',
        },
    ];

    return (
        <PortalLayout title="Borrower portal">
            <Card
                title="Borrower / SME portal"
                titleAs="h1"
                description="Get verified to raise financing. Creating a financing request arrives in the next sprint."
            >
                <StepList steps={steps} />
            </Card>
        </PortalLayout>
    );
}
