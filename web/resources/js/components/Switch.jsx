import * as RadixSwitch from '@radix-ui/react-switch';
import { useId } from 'react';
import cx from '../lib/cx';
import KhatamIcon from './KhatamIcon';

/**
 * An on/off switch built on Radix Switch (role="switch", Space toggles it). onChange receives
 * true or false. `shariah` puts the khatam before the label, as the mode switch does.
 *
 *   <Switch label="Email me about new campaigns" checked={...} onChange={(on) => ...} />
 *
 * The off track is `color-border-control` (3:1 against white), so the control is visible
 * whether it is on or off; the thumb's position, not just its colour, shows the state.
 */
export default function Switch({ id, label, description, checked, defaultChecked, onChange, disabled = false, shariah = false, className = '' }) {
    const generatedId = useId();
    const switchId = id ?? generatedId;

    return (
        <div className={cx('flex items-start justify-between gap-4', disabled && 'opacity-(--opacity-disabled)', className)}>
            {(label || description) && (
                <label htmlFor={switchId} className={cx('text-body', disabled ? 'cursor-not-allowed' : 'cursor-pointer')}>
                    <span className="flex items-center gap-2 font-medium text-text">
                        {shariah && <KhatamIcon className="text-primary" />}
                        {label}
                    </span>
                    {description && (
                        <span id={`${switchId}-description`} className="mt-0.5 block text-body-sm text-text-muted">
                            {description}
                        </span>
                    )}
                </label>
            )}
            <SwitchControl
                id={switchId}
                checked={checked}
                defaultChecked={defaultChecked}
                onChange={onChange}
                disabled={disabled}
                aria-describedby={description ? `${switchId}-description` : undefined}
            />
        </div>
    );
}

/** The bare track and thumb, for places that supply their own label (ModeSwitch). */
export function SwitchControl({ onChange, className = '', ...props }) {
    return (
        <RadixSwitch.Root
            onCheckedChange={onChange}
            className={cx(
                'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-border-control transition-colors duration-(--duration-fast) ease-standard',
                'data-[state=checked]:bg-primary disabled:cursor-not-allowed',
                className,
            )}
            {...props}
        >
            <RadixSwitch.Thumb className="block size-4 translate-x-0.5 rounded-full bg-surface shadow-xs transition-transform duration-(--duration-fast) ease-standard data-[state=checked]:translate-x-4.5 motion-reduce:transition-none" />
        </RadixSwitch.Root>
    );
}
