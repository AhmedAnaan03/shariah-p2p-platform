import * as RadixRadio from '@radix-ui/react-radio-group';
import { useId } from 'react';
import cx from '../lib/cx';
import { FieldError } from './Field';
import Icon from './Icon';

const COLUMNS = {
    1: '',
    2: '@bp-sm:grid-cols-2',
    3: '@bp-sm:grid-cols-3',
};

/**
 * Radio options drawn as selectable cards (component-api.d.ts, RadioCards), e.g. the role on the
 * registration form, individual / SME, or approve / reject. Built on Radix RadioGroup, so it is
 * one tab stop and arrow keys move the choice. Each card is the radio itself, so its whole
 * area is the target and its label and description are what a screen reader announces.
 *
 *   <RadioCards name="role" legend="I want to register as" columns={2}
 *               options={[{ value: 'investor', label: 'Investor', description: '...', icon: 'hand-coins' }]}
 *               value={form.data.role} onChange={(value) => form.setData('role', value)} error={form.errors.role} />
 *
 * Cards sit side by side when their container is at least 640 px wide, one per row below.
 */
export default function RadioCards({
    name,
    legend,
    options,
    value,
    defaultValue,
    onChange,
    columns = 2,
    disabled = false,
    error,
    className = '',
    'aria-label': ariaLabel,
}) {
    const legendId = useId();

    return (
        <fieldset className={cx('@container', className)}>
            {legend && (
                <legend id={legendId} className="mb-2 text-label text-text">
                    {legend}
                </legend>
            )}
            <RadixRadio.Root
                name={name}
                value={value}
                defaultValue={defaultValue}
                onValueChange={onChange}
                disabled={disabled}
                aria-labelledby={legend ? legendId : undefined}
                aria-label={ariaLabel}
                aria-describedby={error ? `${name}-error` : undefined}
                className={cx('grid gap-3', COLUMNS[columns] ?? COLUMNS[2])}
            >
                {options.map((option) => {
                    const card = (
                        <RadixRadio.Item
                            key={option.value}
                            value={option.value}
                            disabled={option.disabled}
                            className={cx(
                                'group flex w-full items-start gap-3 rounded-lg border bg-surface p-4 text-left shadow-xs transition-colors duration-(--duration-fast) ease-standard',
                                'hover:border-border-control data-[state=checked]:border-primary data-[state=checked]:bg-primary-subtle',
                                'disabled:cursor-not-allowed disabled:opacity-(--opacity-disabled)',
                                error ? 'border-danger' : 'border-border',
                            )}
                        >
                            {/* The visible radio dot, so the choice is not shown by colour alone. */}
                            <span
                                aria-hidden="true"
                                className="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border border-border-control bg-surface group-data-[state=checked]:border-primary"
                            >
                                <span className="size-2 scale-0 rounded-full bg-primary transition-transform duration-(--duration-instant) group-data-[state=checked]:scale-100" />
                            </span>
                            <span className="min-w-0 flex-1">
                                <span className="flex items-center gap-2 text-body font-semibold text-text">
                                    {option.icon && <Icon name={option.icon} className="text-icon group-data-[state=checked]:text-primary" />}
                                    {option.label}
                                    {option.badge}
                                </span>
                                {option.description && (
                                    <span className="mt-1 block text-body-sm text-text-muted group-data-[state=checked]:text-text-secondary">
                                        {option.description}
                                    </span>
                                )}
                            </span>
                        </RadixRadio.Item>
                    );
                    return option.wrap ? option.wrap(card) : card;
                })}
            </RadixRadio.Root>
            {error && (
                <div className="mt-2">
                    <FieldError id={name} error={error} />
                </div>
            )}
        </fieldset>
    );
}
