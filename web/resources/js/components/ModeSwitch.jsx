import { router, usePage } from '@inertiajs/react';
import { useId } from 'react';
import cx from '../lib/cx';
import { usePlatformMode } from '../lib/mode';
import KhatamIcon from './KhatamIcon';
import { SwitchControl } from './Switch';
import { useToasts } from './Toast';
import Tooltip from './Tooltip';

/**
 * The platform-wide Shariah-only toggle for investors (README, "Modes"): the khatam, a label
 * (hidden on phones, still read by screen readers) and a switch.
 *
 *   <ModeSwitch mode={mode} onChange={setMode} />
 *
 * `disabledReason` disables it and explains why in a tooltip.
 */
export default function ModeSwitch({ mode, onChange, label = 'Shariah-only', disabledReason, compactLabel = true }) {
    const id = useId();
    const on = mode === 'shariah';
    const disabled = Boolean(disabledReason);

    const control = (
        <span
            className={cx(
                'inline-flex h-(--control-md) items-center gap-2 rounded-full border border-border bg-surface pr-2 pl-3',
                disabled && 'opacity-(--opacity-disabled)',
            )}
        >
            <KhatamIcon className={on ? 'text-primary' : 'text-icon'} />
            <label htmlFor={id} className={cx('text-label whitespace-nowrap text-text', compactLabel && 'sr-only @bp-sm/shell:not-sr-only', disabled ? 'cursor-not-allowed' : 'cursor-pointer')}>
                {label}
            </label>
            <SwitchControl id={id} checked={on} disabled={disabled} onChange={(checked) => onChange?.(checked ? 'shariah' : 'conventional')} />
        </span>
    );

    if (!disabled) {
        return control;
    }

    // A disabled control can't take focus, so the explanation hangs off a focusable wrapper.
    return (
        <Tooltip content={disabledReason}>
            <span tabIndex={0} aria-label={`${label}: ${disabledReason}`} className="rounded-full">
                {control}
            </span>
        </Tooltip>
    );
}

/**
 * The investor's mode switch wired to the server. Switching it:
 *   1. changes <html data-mode> straight away, so the 500 ms cross-fade plays;
 *   2. saves the choice with PATCH /investor/preferences/shariah-only.
 * If the save fails, the palette goes back and a toast says so. Returns null for anyone who
 * isn't an investor (the server sends modeSwitch: null).
 */
export function useShariahSwitch() {
    const { modeSwitch } = usePage().props;
    const [mode, setMode] = usePlatformMode();
    const { push } = useToasts();

    if (!modeSwitch) {
        return null;
    }

    function change(next) {
        const previous = mode;
        setMode(next);

        const fail = (message) => {
            setMode(previous);
            push({ tone: 'danger', title: "We couldn't save Shariah-only mode", children: message });
        };

        router.patch(
            modeSwitch.url,
            { shariah_only: next === 'shariah' },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => fail(errors.shariah_only ?? 'Please try again.'),
                onHttpException: () => fail('Please try again.'),
                onNetworkError: () => fail('Check your connection and try again.'),
            },
        );
    }

    return {
        mode,
        change,
        disabledReason: modeSwitch.available ? undefined : 'Complete your profile to choose Shariah-only mode.',
    };
}
