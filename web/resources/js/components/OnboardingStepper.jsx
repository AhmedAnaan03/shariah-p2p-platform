import cx from '../lib/cx';
import Button from './Button';
import Icon from './Icon';

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
 */
export default function OnboardingStepper({ steps, current, orientation = 'horizontal' }) {
    const firstOpen = steps.findIndex((step) => !step.done);
    const active = current ?? (firstOpen === -1 ? steps.length : firstOpen);
    const doneCount = steps.filter((step) => step.done).length;
    const horizontal = orientation === 'horizontal';

    return (
        <div className="@container">
            <p className="mb-4 text-label text-text-muted">
                <span className="font-mono text-code text-text">{doneCount}</span> of <span className="font-mono text-code text-text">{steps.length}</span> steps done
            </p>
            <ol className={cx('grid gap-0', horizontal && '@bp-sm:gap-4', horizontal && GRID[steps.length])}>
                {steps.map((step, index) => {
                    const state = step.done ? 'done' : index === active ? 'current' : 'upcoming';
                    const last = index === steps.length - 1;

                    return (
                        <li
                            key={step.title}
                            aria-current={state === 'current' ? 'step' : undefined}
                            className={cx('relative flex gap-3 pb-6', last && 'pb-0', horizontal && '@bp-sm:flex-col @bp-sm:pb-0')}
                        >
                            {/* The line joining this step to the next: down on phones, across when horizontal. */}
                            {!last && (
                                <span
                                    aria-hidden="true"
                                    className={cx(
                                        'absolute top-8 bottom-0 left-3.5 w-px',
                                        step.done ? 'bg-primary' : 'bg-border',
                                        horizontal && '@bp-sm:top-3.5 @bp-sm:-right-4 @bp-sm:bottom-auto @bp-sm:left-10 @bp-sm:h-px @bp-sm:w-auto',
                                    )}
                                />
                            )}
                            <Marker state={state} number={index + 1} />
                            <div className="min-w-0 flex-1">
                                <p className={cx('font-semibold', state === 'upcoming' ? 'text-text-secondary' : 'text-text')}>{step.title}</p>
                                <p className="sr-only">{STATE_LABEL[state]}</p>
                                {step.description && <p className="mt-1 text-body-sm text-text-muted">{step.description}</p>}
                                {state === 'current' && step.href && (
                                    <div className="mt-3">
                                        <Button href={step.href} size="sm" iconRight="arrow-right">
                                            {step.actionLabel}
                                        </Button>
                                    </div>
                                )}
                                {state === 'upcoming' && step.href && (
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
        </div>
    );
}

const GRID = { 2: '@bp-sm:grid-cols-2', 3: '@bp-sm:grid-cols-3', 4: '@bp-sm:grid-cols-4', 5: '@bp-sm:grid-cols-5' };

const STATE_LABEL = { done: 'Done.', current: 'Current step.', upcoming: 'Not started.' };

function Marker({ state, number }) {
    if (state === 'done') {
        return (
            <span aria-hidden="true" className="relative flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-primary-fg">
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
