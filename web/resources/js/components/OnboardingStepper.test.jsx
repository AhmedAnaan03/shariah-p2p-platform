import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import OnboardingStepper from './OnboardingStepper';

const STEPS = [
    { title: 'Your details', done: true, href: '/a' },
    { title: 'Address', done: true, href: '/b' },
    { title: 'Documents', done: false, href: '/c' },
    { title: 'Review', done: false },
];

/** Every line segment's classes, in order. */
const lines = (html) => [...html.matchAll(/<span class="(flex-1[^"]*)"/g)].map((m) => m[1]);

/**
 * PB-17 review fix: markers centred over their labels, and lines that join the markers with no
 * gaps in every state. The layout itself is checked in a browser (scripts/screenshots.mjs);
 * these check the structure that makes it work.
 */
describe('OnboardingStepper', () => {
    it('renders every step with its state for screen readers', () => {
        const html = renderToStaticMarkup(<OnboardingStepper steps={STEPS} />);
        expect(html.replace(/<[^>]+>/g, '')).toContain('2 of 4 steps done');
        expect(html.match(/<li/g)).toHaveLength(4);
        expect(html).toContain('aria-current="step"');
        expect(html).toContain('Current step.');
        expect(html).toContain('Not started.');
    });

    it('draws a line on each side of every marker when horizontal, hiding the outer ends', () => {
        const segments = lines(renderToStaticMarkup(<OnboardingStepper steps={STEPS} />));
        expect(segments).toHaveLength(8); // left and right of 4 markers
        expect(segments[0]).toContain('invisible'); // before the first step
        expect(segments.at(-1)).toContain('invisible'); // after the last
        // Done steps colour the line to the next step on both sides of the join.
        expect(segments[1]).toContain('bg-primary'); // step 1 → 2 (right of 1)
        expect(segments[2]).toContain('bg-primary'); // step 1 → 2 (left of 2)
        expect(segments[5]).toContain('bg-border'); // step 3 → 4 (right of 3): not done yet
        expect(segments[6]).toContain('bg-border'); // step 3 → 4 (left of 4)
    });

    it('centres each marker over its label when horizontal', () => {
        const html = renderToStaticMarkup(<OnboardingStepper steps={STEPS} />);
        expect(html).toContain('@bp-sm:flex-col @bp-sm:items-center');
        expect(html).toContain('@bp-sm:text-center');
    });

    it('draws only the downward lines when vertical', () => {
        const segments = lines(renderToStaticMarkup(<OnboardingStepper steps={STEPS} orientation="vertical" />));
        expect(segments).toHaveLength(4);
        expect(segments.every((classes) => classes.includes('w-0.5'))).toBe(true);
        expect(segments.at(-1)).toContain('invisible');
    });

    it('marks the step on screen in the wizard and links the steps reached', () => {
        const html = renderToStaticMarkup(<OnboardingStepper variant="nav" current={1} steps={STEPS} />);
        expect(html).toContain('<nav');
        expect(html).toContain('href="/a"');
        expect(html).toContain('Current step, done.');
    });
});
