import { useState } from 'react';
import { cleanNumberInput, formatAmount, toNumber } from '../lib/format';
import Field, { describedBy } from './Field';
import TextInput from './TextInput';

/**
 * An amount of money in MVR (component-api.d.ts, MoneyInput): the "MVR" prefix, the figure in
 * Plex Mono, and thousands separators once the box loses focus ("120,000.00"). While the user
 * types, only digits and one decimal point are kept, and the text is never reformatted under
 * the cursor (README, "never animate a number the user is typing").
 *
 *   <MoneyInput id="amount_requested" label="Amount" value={form.data.amount_requested}
 *       onValueChange={(amount) => form.setData('amount_requested', amount)} error={form.errors.amount_requested} />
 *
 * onValueChange gets a number, or null when the box is empty. With a `label` it comes in a
 * Field; without one it is the bare control.
 */
export default function MoneyInput({ id, label, hint, error, optional, value, defaultValue, onValueChange, className = '', ...inputProps }) {
    const [focused, setFocused] = useState(false);
    const [draft, setDraft] = useState(() => toText(value ?? defaultValue));

    // While the user isn't typing, show the stored value formatted.
    const shown = focused ? draft : toText(value ?? defaultValue, true);

    const control = (
        <TextInput
            id={id}
            name={id}
            prefix="MVR"
            inputMode="decimal"
            autoComplete="off"
            invalid={Boolean(error)}
            inputClassName="font-mono"
            {...describedBy(id, { hint, error })}
            {...inputProps}
            className={label ? '' : className}
            value={shown}
            onFocus={(event) => {
                setDraft(toText(value ?? defaultValue));
                setFocused(true);
                inputProps.onFocus?.(event);
            }}
            onBlur={(event) => {
                setFocused(false);
                inputProps.onBlur?.(event);
            }}
            onChange={(event) => {
                const cleaned = cleanNumberInput(event.target.value);
                setDraft(cleaned);
                onValueChange?.(cleaned === '' ? null : Number(cleaned));
            }}
        />
    );

    if (!label) {
        return control;
    }

    return (
        <Field htmlFor={id} label={label} helper={hint} error={error} optional={optional} className={className}>
            {control}
        </Field>
    );
}

/** The text in the box for a stored value: "120000" while typing, "120,000.00" otherwise. */
function toText(value, formatted = false) {
    const number = toNumber(value);
    if (number === null) {
        return '';
    }
    return formatted ? formatAmount(number) : String(number);
}
