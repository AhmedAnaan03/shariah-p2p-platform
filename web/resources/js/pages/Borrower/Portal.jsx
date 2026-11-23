import { usePage } from '@inertiajs/react';
import Button from '../../components/Button';
import Card from '../../components/Card';
import OnboardingStepper from '../../components/OnboardingStepper';
import { setupSteps } from '../../lib/onboarding';

/**
 * Borrower portal home: the steps to get verified (PB-09, PB-11; the onboarding wizard's steps
 * since PB-53) and, once verified, the way into financing requests (PB-13).
 */
export default function BorrowerPortal({ onboarding, requestCount }) {
    const { auth } = usePage().props;
    const steps = setupSteps(onboarding, auth.user.status);

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
        <Card title="Get verified" description="Set up your account, then an administrator verifies it before you can raise financing.">
            <OnboardingStepper steps={steps} orientation="vertical" />
        </Card>
    );
}

BorrowerPortal.layout = {
    title: 'Overview',
    description: 'Get verified, then raise financing from investors.',
};
