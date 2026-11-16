import cx from '../lib/cx';
import Icon from './Icon';

const SIZES = {
    sm: 'h-(--control-sm) text-body-sm',
    md: 'h-(--control-md) text-body',
    lg: 'h-(--control-lg) text-body-lg',
};

/**
 * A single-line input without a label (component-api.d.ts, TextInput). Put it in a Field, or
 * use Input, which does that for you.
 *
 *   <TextInput id="amount" prefix="MVR" inputMode="decimal" />
 *   <TextInput icon="magnifying-glass" size="sm" placeholder="Search" />
 *
 * Heights control-sm 32 / md 40 / lg 48 px with 12 px side padding. Placeholders are examples,
 * never instructions (they are only 3.6:1).
 */
export default function TextInput({
    size = 'md',
    prefix,
    suffix,
    icon,
    invalid = false,
    disabled = false,
    className = '',
    inputClassName = '',
    type = 'text',
    ...inputProps
}) {
    return (
        <div
            className={cx(
                'control flex w-full items-center gap-2 px-3',
                SIZES[size],
                invalid && 'control-invalid',
                disabled && 'control-disabled',
                className,
            )}
        >
            {icon && <Icon name={icon} size={size === 'sm' ? 16 : 20} className="text-icon" />}
            {prefix && <span className="shrink-0 font-mono text-code text-text-muted">{prefix}</span>}
            <input
                type={type}
                disabled={disabled}
                aria-invalid={invalid ? 'true' : undefined}
                className={cx(
                    'h-full min-w-0 flex-1 bg-transparent text-text outline-none placeholder:text-text-placeholder disabled:cursor-not-allowed',
                    inputClassName,
                )}
                {...inputProps}
            />
            {suffix && <span className="shrink-0 text-body-sm text-text-muted">{suffix}</span>}
        </div>
    );
}
