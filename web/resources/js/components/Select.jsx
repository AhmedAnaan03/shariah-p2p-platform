import * as RadixSelect from '@radix-ui/react-select';
import cx from '../lib/cx';
import Field, { describedBy } from './Field';
import Icon from './Icon';

const SIZES = {
    sm: 'h-(--control-sm) text-body-sm',
    md: 'h-(--control-md) text-body',
    lg: 'h-(--control-lg) text-body-lg',
};

/**
 * A drop-down list built on Radix Select (keyboard, type-ahead and screen-reader support).
 * `options` is a list of { value, label }; `placeholder` shows until something is chosen.
 * onChange receives the chosen value, not a browser event.
 *
 *   <Select id="sector" label="Sector" options={sectors} placeholder="Choose a sector"
 *           value={form.data.sector} onChange={(value) => form.setData('sector', value)} error={form.errors.sector} />
 *
 * With a `label` it comes in a Field; without one it is the bare control.
 */
export default function Select({
    id,
    label,
    hint,
    error,
    invalid,
    options,
    placeholder,
    value,
    defaultValue,
    onChange,
    size = 'md',
    disabled = false,
    className = '',
}) {
    const isInvalid = invalid ?? Boolean(error);

    const control = (
        <RadixSelect.Root value={value ?? undefined} defaultValue={defaultValue} onValueChange={onChange} disabled={disabled}>
            <RadixSelect.Trigger
                id={id}
                {...describedBy(id, { hint, error })}
                aria-invalid={isInvalid ? 'true' : undefined}
                className={cx(
                    'control flex w-full items-center justify-between gap-2 px-3 text-left text-text',
                    // The empty state is an instruction ("Choose a sector"), so it gets readable
                    // text-muted (5.2:1), not the 3.6:1 placeholder colour meant for examples.
                    'data-placeholder:text-text-muted',
                    SIZES[size],
                    isInvalid && 'control-invalid',
                    disabled && 'control-disabled',
                    !label && className,
                )}
            >
                <span className="truncate">
                    <RadixSelect.Value placeholder={placeholder} />
                </span>
                <RadixSelect.Icon>
                    <Icon name="caret-down" size={16} className="text-icon" />
                </RadixSelect.Icon>
            </RadixSelect.Trigger>

            <RadixSelect.Portal>
                <RadixSelect.Content
                    position="popper"
                    sideOffset={4}
                    className="z-(--z-dropdown) max-h-(--radix-select-content-available-height) w-(--radix-select-trigger-width) animate-pop overflow-hidden rounded-md border border-border bg-surface-raised p-1 shadow-md"
                >
                    <RadixSelect.Viewport>
                        {options.map((option) => (
                            <RadixSelect.Item
                                key={option.value}
                                value={String(option.value)}
                                className="relative flex h-(--control-md) cursor-pointer items-center rounded-md pr-3 pl-8 text-body text-text outline-none select-none data-disabled:opacity-(--opacity-disabled) data-highlighted:bg-surface-subtle pointer-coarse:min-h-(--touch-target)"
                            >
                                <RadixSelect.ItemIndicator className="absolute left-2 text-primary">
                                    <Icon name="check" size={16} />
                                </RadixSelect.ItemIndicator>
                                <RadixSelect.ItemText>{option.label}</RadixSelect.ItemText>
                            </RadixSelect.Item>
                        ))}
                    </RadixSelect.Viewport>
                </RadixSelect.Content>
            </RadixSelect.Portal>
        </RadixSelect.Root>
    );

    if (!label) {
        return control;
    }

    return (
        <Field htmlFor={id} label={label} helper={hint} error={error} className={className}>
            {control}
        </Field>
    );
}
