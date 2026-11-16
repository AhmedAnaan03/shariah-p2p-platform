import * as RadixRadio from '@radix-ui/react-radio-group';
import { useId } from 'react';
import cx from '../lib/cx';
import { FieldError } from './Field';

/**
 * A plain list of radio buttons (component-api.d.ts, RadioGroup), built on Radix RadioGroup:
 * arrow keys move between options, Tab leaves the group. For options shown as selectable
 * cards (role, borrower type, a decision) use RadioCards.
 *
 *   <RadioGroup name="frequency" legend="Repayment frequency" options={[{ value: 'monthly', label: 'Monthly' }]}
 *               value={...} onChange={(value) => ...} error={form.errors.frequency} />
 */
export default function RadioGroup({
    name,
    legend,
    options,
    value,
    defaultValue,
    onChange,
    orientation = 'vertical',
    disabled = false,
    error,
    className = '',
    'aria-label': ariaLabel,
}) {
    const legendId = useId();

    return (
        <fieldset className={className}>
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
                orientation={orientation}
                disabled={disabled}
                aria-labelledby={legend ? legendId : undefined}
                aria-label={ariaLabel}
                aria-describedby={error ? `${name}-error` : undefined}
                className={cx('flex gap-3', orientation === 'vertical' ? 'flex-col' : 'flex-wrap gap-x-6')}
            >
                {options.map((option) => {
                    const id = `${name}-${option.value}`;
                    return (
                        <div key={option.value} className="flex items-start gap-3 pointer-coarse:min-h-(--touch-target)">
                            <RadixRadio.Item
                                id={id}
                                value={option.value}
                                disabled={option.disabled}
                                className={cx(
                                    'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border bg-surface',
                                    'data-[state=checked]:border-primary disabled:cursor-not-allowed disabled:opacity-(--opacity-disabled)',
                                    error ? 'border-danger' : 'border-border-control',
                                )}
                            >
                                <RadixRadio.Indicator className="size-2 rounded-full bg-primary" />
                            </RadixRadio.Item>
                            <label htmlFor={id} className="cursor-pointer text-body">
                                <span className="font-medium text-text">{option.label}</span>
                                {option.description && <span className="block text-body-sm text-text-muted">{option.description}</span>}
                            </label>
                        </div>
                    );
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
