import { usePage } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Card from '../../components/Card';
import ModePanel from '../../components/ModePanel';
import OnboardingStepper from '../../components/OnboardingStepper';
import { setupSteps } from '../../lib/onboarding';

/**
 * Investor portal home: the steps to start investing (PB-10, PB-11; the onboarding wizard's
 * steps since PB-53) and the Shariah-only mode panel (PB-51). `pledge` is the answer of the
 * server's 'pledge' gate, with its reason when pledging is still locked.
 */
export default function InvestorPortal({ onboarding, pledge }) {
    const { auth } = usePage().props;
    const steps = setupSteps(onboarding, auth.user.status);

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
                <OnboardingStepper steps={steps} orientation="vertical" />
            </Card>

            <ModePanel />
        </>
    );
}

InvestorPortal.layout = { title: 'Overview' };
