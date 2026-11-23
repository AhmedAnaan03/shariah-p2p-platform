import { useLayoutEffect, useRef, useState } from 'react';
import { formatAmount, toNumber } from '../lib/format';
import { formatMoneyTyping, groupThousands } from '../lib/moneyInput';
import Field, { describedBy } from './Field';
import TextInput from './TextInput';

/**
 * An amount of money in MVR (component-api.d.ts, MoneyInput): the "MVR" prefix and the figure in
 * Plex Mono with tabular digits. Thousands separators appear as you type ("120,000"), and the
 * cursor stays after the digit it was after (lib/moneyInput.js, PB-57); once the box loses focus
 * it shows two decimals ("120,000.00"). Only digits and one decimal point are kept.
 *
 *   <MoneyInput id="amount_requested" label="Amount" value={form.data.amount_requested}
 *       onValueChange={(amount) => form.setData('amount_requested', amount)} error={form.errors.amount_requested} />
 *
 * onValueChange gets the plain number (no separators), or null when the box is empty, so the
 * form always submits 120000, never "120,000". With a `label` it comes in a
 * Field; without one it is the bare control.
 */
export default function MoneyInput({ id, label, hint, error, optional, value, defaultValue, onValueChange, className = '', ...inputProps }) {
    const [focused, setFocused] = useState(false);
    const [draft, setDraft] = useState(() => toText(value ?? defaultValue));
    const input = useRef(null);
    const caret = useRef(null);

    // After a keystroke is reformatted, put the cursor back where formatMoneyTyping says.
    useLayoutEffect(() => {
        if (caret.current !== null && input.current) {
            input.current.setSelectionRange(caret.current, caret.current);
            caret.current = null;
        }
    }, [draft]);

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
            figures
            ref={input}
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
                const typed = formatMoneyTyping(event.target.value, event.target.selectionStart, draft);
                caret.current = typed.caret;
                setDraft(typed.text);
                onValueChange?.(typed.value);
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

/** The text in the box for a stored value: "120,000" while typing, "120,000.00" otherwise. */
function toText(value, formatted = false) {
    const number = toNumber(value);
    if (number === null) {
        return '';
    }
    if (formatted) {
        return formatAmount(number);
    }
    const [whole, decimals] = String(number).split('.');
    return groupThousands(whole) + (decimals ? `.${decimals}` : '');
}
