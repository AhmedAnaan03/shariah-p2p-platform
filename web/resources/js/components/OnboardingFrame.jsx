import Alert from './Alert';
import Button from './Button';
import Card from './Card';
import OnboardingStepper from './OnboardingStepper';

/**
 * The frame every onboarding wizard step sits in (PB-53): the steps across the top, the step's
 * own content, and the Back / Continue buttons.
 *
 *   <OnboardingFrame wizard={wizard} onSubmit={submit} processing={form.processing}>
 *       <Card title="Your address">...</Card>
 *   </OnboardingFrame>
 *
 * `wizard` is the prop OnboardingController sends with every step: { steps, step, backUrl,
 * nextUrl, locked }. With `onSubmit` the content is a form and Continue submits it (the server
 * saves the step and moves on). Once the account is verified (`locked`) nothing can be saved,
 * so Continue becomes a plain link to the next step. `actions` replaces the forward button
 * (the review step has its own Submit button).
 */
export default function OnboardingFrame({ wizard, onSubmit, processing = false, continueLabel = 'Continue', actions, children }) {
    const current = wizard.steps.findIndex((step) => step.key === wizard.step);

    const back = wizard.backUrl && (
        <Button href={wizard.backUrl} variant="secondary" iconLeft="arrow-left">
            Back
        </Button>
    );

    let forward = null;
    if (wizard.locked) {
        forward = wizard.nextUrl && (
            <Button href={wizard.nextUrl} iconRight="arrow-right">
                Next
            </Button>
        );
    } else if (onSubmit) {
        forward = (
            <Button type="submit" iconRight="arrow-right" loading={processing}>
                {continueLabel}
            </Button>
        );
    }

    // Back on the left, the way forward on the right, even when there is no Back.
    const buttons = (
        <>
            {back}
            <div className="ml-auto flex flex-wrap items-center gap-3">{actions || forward}</div>
        </>
    );

    const body = (
        <div className="space-y-6">
            {wizard.locked && (
                <Alert tone="info" role="note">
                    Your account has been verified, so these details can no longer be changed.
                </Alert>
            )}
            {children}
            <div className="flex flex-wrap items-center gap-3">{buttons}</div>
        </div>
    );

    return (
        <div className="space-y-6">
            <Card>
                <OnboardingStepper variant="nav" steps={wizard.steps} current={current} />
            </Card>

            {onSubmit && !wizard.locked ? (
                <form onSubmit={onSubmit} noValidate>
                    {body}
                </form>
            ) : (
                body
            )}
        </div>
    );
}
