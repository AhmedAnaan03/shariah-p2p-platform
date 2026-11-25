import cx from '../lib/cx';
import Button from './Button';
import Icon from './Icon';
import TextLink from './TextLink';

/**
 * The steps to get started on a portal home (component-api.d.ts, OnboardingStepper).
 * `steps` is a list of { title, description, done, href, actionLabel }. `current` defaults to
 * the first step not yet done; that step shows its action button.
 *
 *   <OnboardingStepper steps={[{ title: 'Complete your profile', done: true }, ...]} />
 *
 * Each step's state is written out for screen readers ("Done", "Current step", "Not started"),
 * and shown by its marker (tick, ring, number), not only by colour. Horizontal when its
 * container is 640 px or wider and `orientation` allows it; vertical below.
 *
 * `variant="nav"` is the onboarding wizard's progress bar (PB-53): `current` is the step being
 * shown, a step's title is a link when it has an `href` (steps the user has reached), and
 * there are no action buttons. It is a <nav> with the current step marked aria-current="step".
 *
 *   <OnboardingStepper variant="nav" current={1} steps={[{ title: 'Your details', done: true, href: '/borrower/onboarding/profile' }, ...]} />
 */
export default function OnboardingStepper({ steps, current, orientation = 'horizontal', variant = 'checklist' }) {
    const firstOpen = steps.findIndex((step) => !step.done);
    const active = current ?? (firstOpen === -1 ? steps.length : firstOpen);
    const doneCount = steps.filter((step) => step.done).length;
    const horizontal = orientation === 'horizontal';
    const nav = variant === 'nav';
    const Wrapper = nav ? 'nav' : 'div';

    return (
        <Wrapper className="@container" aria-label={nav ? 'Setup steps' : undefined}>
            <p className="mb-4 text-label text-text-muted">
                <span className="font-mono text-code text-text">{doneCount}</span> of <span className="font-mono text-code text-text">{steps.length}</span> steps done
            </p>
            <ol className={cx('grid', horizontal && GRID[steps.length])}>
                {steps.map((step, index) => {
                    // In the wizard the step on screen is "current" even when it is already done.
                    const state = nav && index === active ? 'current' : step.done ? 'done' : index === active ? 'current' : 'upcoming';
                    const first = index === 0;
                    const last = index === steps.length - 1;

                    return (
                        <li
                            key={step.title}
                            aria-current={state === 'current' ? 'step' : undefined}
                            className={cx('flex gap-3', horizontal && '@bp-sm:flex-col @bp-sm:items-center @bp-sm:gap-3 @bp-sm:text-center')}
                        >
                            {/* The track: the marker with the lines to its neighbours. The lines are
                                flex items next to the marker, so they always touch it, and they fill
                                the rest of the step, so they meet the next step's line at the edge:
                                no gaps at any width. On phones one line runs down from the marker to
                                the next step; when horizontal a half-line runs to each side. Grid
                                columns have no gap, so neighbouring half-lines meet. */}
                            <div aria-hidden="true" className={cx('flex flex-col items-center', horizontal && '@bp-sm:w-full @bp-sm:flex-row')}>
                                {horizontal && <Line done={!first && steps[index - 1].done} hidden={first} />}
                                <Marker state={state} number={index + 1} done={step.done} />
                                <Line done={step.done} hidden={last} down horizontal={horizontal} />
                            </div>
                            {/* pt-0.5 centres the title's first line on the 28 px marker beside it. */}
                            <div className={cx('min-w-0 flex-1 pt-0.5', !last && 'pb-6', horizontal && '@bp-sm:px-2 @bp-sm:pt-0 @bp-sm:pb-0')}>
                                <p className={cx('font-semibold', state === 'upcoming' ? 'text-text-secondary' : 'text-text')}>
                                    {nav && step.href && state !== 'current' ? (
                                        <TextLink href={step.href}>{step.title}</TextLink>
                                    ) : (
                                        step.title
                                    )}
                                </p>
                                <p className="sr-only">{nav && state === 'current' && step.done ? 'Current step, done.' : STATE_LABEL[state]}</p>
                                {step.description && <p className="mt-1 text-body-sm text-text-muted">{step.description}</p>}
                                {!nav && state === 'current' && step.href && (
                                    <div className="mt-3">
                                        <Button href={step.href} size="sm" iconRight="arrow-right">
                                            {step.actionLabel}
                                        </Button>
                                    </div>
                                )}
                                {!nav && state === 'upcoming' && step.href && (
                                    <div className="mt-3">
                                        <Button href={step.href} size="sm" variant="secondary">
                                            {step.actionLabel}
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </Wrapper>
    );
}

const GRID = { 2: '@bp-sm:grid-cols-2', 3: '@bp-sm:grid-cols-3', 4: '@bp-sm:grid-cols-4', 5: '@bp-sm:grid-cols-5' };

const STATE_LABEL = { done: 'Done.', current: 'Current step.', upcoming: 'Not started.' };

/**
 * A connecting line, 2 px thick, centred on the marker: primary once the step before it is done.
 *
 * - `down`: the line from the marker towards the next step. On phones it runs down the left
 *   column to the bottom of the step, where the next marker starts; once the stepper turns
 *   horizontal (`horizontal`) it becomes the right-hand half-line.
 * - Otherwise: the left-hand half-line, shown only when horizontal.
 *
 * `hidden` keeps the space without drawing it (before the first step and after the last), so
 * every marker stays centred over its label.
 */
function Line({ done, hidden = false, down = false, horizontal = false }) {
    return (
        <span
            className={cx(
                'flex-1',
                down ? 'w-0.5' : 'hidden h-0.5 @bp-sm:block',
                down && horizontal && '@bp-sm:h-0.5',
                hidden ? 'invisible' : done ? 'bg-primary' : 'bg-border',
            )}
        />
    );
}

function Marker({ state, number, done }) {
    if (state === 'done' || (state === 'current' && done)) {
        return (
            <span
                aria-hidden="true"
                className={cx(
                    'relative flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-primary-fg',
                    state === 'current' && 'ring-3 ring-primary-muted',
                )}
            >
                <Icon name="check" size={16} weight="bold" />
            </span>
        );
    }

    return (
        <span
            aria-hidden="true"
            className={cx(
                'relative flex size-7 shrink-0 items-center justify-center rounded-full border-2 bg-surface font-mono text-code',
                state === 'current' ? 'border-primary text-primary-ink ring-3 ring-primary-muted' : 'border-border-control text-text-muted',
            )}
        >
            {number}
        </span>
    );
}
