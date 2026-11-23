import { describe, expect, it } from 'vitest';
import { setupSteps } from './onboarding';

const onboarding = (done) => ({
    continueUrl: '/borrower/onboarding/address',
    steps: ['Your details', 'Address', 'Documents', 'Review and submit'].map((title, index) => ({ title, done: index < done })),
});

describe('setupSteps', () => {
    it('gives only the first unfinished step a button, pointing at where the wizard resumes', () => {
        const steps = setupSteps(onboarding(1), 'pending_verification');

        expect(steps.map((step) => step.href)).toEqual([null, '/borrower/onboarding/address', null, null, undefined]);
    });

    it('adds the administrator check, waiting until the account is submitted', () => {
        expect(setupSteps(onboarding(2), 'pending_verification').at(-1)).toMatchObject({ done: false, description: 'Starts once you submit your account.' });
        expect(setupSteps(onboarding(4), 'pending_verification').at(-1).description).toBe('Your account is in the review queue.');
        expect(setupSteps(onboarding(4), 'verified').at(-1).done).toBe(true);
    });

    it('tells a rejected user to fix and submit again', () => {
        expect(setupSteps(onboarding(3), 'rejected').at(-1).description).toMatch(/submit again/);
    });
});
