import * as RadixCheckbox from '@radix-ui/react-checkbox';
import cx from '../lib/cx';
import { FieldError } from './Field';
import Icon from './Icon';

/**
 * A checkbox with its label to the right, an optional description and the server's error.
 * Built on Radix Checkbox. onChange receives true or false, not the browser event.
 *
 *   <Checkbox id="remember" label="Remember me" checked={...} onChange={(checked) => ...} />
 *
 * `indeterminate` shows a dash (some, not all, rows selected). The whole row is the click
 * target, and at least 44 px tall on touch screens.
 */
export default function Checkbox({
    id,
    label,
    description,
    error,
    invalid,
    checked,
    defaultChecked,
    indeterminate = false,
    disabled = false,
    onChange,
    className = '',
}) {
    const isInvalid = invalid ?? Boolean(error);
    const describedBy = [description && `${id}-description`, error && `${id}-error`].filter(Boolean).join(' ') || undefined;

    return (
        <div className={className}>
            <div className={cx('flex items-start gap-3 pointer-coarse:min-h-(--touch-target) pointer-coarse:items-center', disabled && 'opacity-(--opacity-disabled)')}>
                <RadixCheckbox.Root
                    id={id}
                    name={id}
                    checked={indeterminate ? 'indeterminate' : checked}
                    defaultChecked={defaultChecked}
                    disabled={disabled}
                    onCheckedChange={(value) => onChange?.(value === true)}
                    aria-invalid={isInvalid ? 'true' : undefined}
                    aria-describedby={describedBy}
                    className={cx(
                        'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-xs border bg-surface text-primary-fg transition-colors duration-(--duration-fast)',
                        'data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=indeterminate]:border-primary data-[state=indeterminate]:bg-primary',
                        'disabled:cursor-not-allowed pointer-coarse:mt-0',
                        isInvalid ? 'border-danger' : 'border-border-control',
                    )}
                >
                    <RadixCheckbox.Indicator>
                        <Icon name={indeterminate ? 'minus' : 'check'} size={12} weight="bold" />
                    </RadixCheckbox.Indicator>
                </RadixCheckbox.Root>
                {(label || description) && (
                    <label htmlFor={id} className={cx('text-body', disabled ? 'cursor-not-allowed' : 'cursor-pointer')}>
                        {label && <span className="font-medium text-text">{label}</span>}
                        {description && (
                            <span id={`${id}-description`} className="mt-0.5 block text-body-sm text-text-muted">
                                {description}
                            </span>
                        )}
                    </label>
                )}
            </div>
            {error && (
                <div className="mt-2">
                    <FieldError id={id} error={error} />
                </div>
            )}
        </div>
    );
}
