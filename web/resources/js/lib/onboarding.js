/**
 * The wizard's steps (OnboardingSteps on the server) plus the administrator's check, for the
 * portal's checklist. The first unfinished step has a button that resumes the wizard there.
 */
export function setupSteps(onboarding, status) {
    const submitted = onboarding.steps.every((step) => step.done);
    const firstOpen = onboarding.steps.findIndex((step) => !step.done);

    return [
        ...onboarding.steps.map((step, index) => ({
            title: step.title,
            done: step.done,
            // Only the step to resume at gets a button; it opens the wizard where the user left off.
            href: index === firstOpen ? onboarding.continueUrl : null,
            actionLabel: 'Continue setup',
        })),
        {
            title: 'Verification by an administrator',
            description:
                status === 'rejected'
                    ? 'Not approved. Fix what the administrator asked for, then submit again.'
                    : submitted
                      ? 'Your account is in the review queue.'
                      : 'Starts once you submit your account.',
            done: status === 'verified',
        },
    ];
}
