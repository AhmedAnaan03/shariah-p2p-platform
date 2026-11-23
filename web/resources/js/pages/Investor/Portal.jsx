import { usePage } from '@inertiajs/react';
import Card from '../../components/Card';
import ModePanel from '../../components/ModePanel';
import OnboardingStepper from '../../components/OnboardingStepper';
import { setupSteps } from '../../lib/onboarding';

/**
 * Investor portal home: the steps to start investing (PB-10, PB-11; the onboarding wizard's
 * steps since PB-53) and the Shariah-only mode panel (PB-51). Whether pledging is locked is
 * shown on the marketplace, where it matters (PB-57).
 */
export default function InvestorPortal({ onboarding }) {
    const { auth } = usePage().props;
    const steps = setupSteps(onboarding, auth.user.status);

    return (
        <>
            <Card title="Start investing" description="Complete these steps to start investing.">
                <OnboardingStepper steps={steps} orientation="vertical" />
            </Card>

            <ModePanel />
        </>
    );
}

InvestorPortal.layout = { title: 'Overview' };
