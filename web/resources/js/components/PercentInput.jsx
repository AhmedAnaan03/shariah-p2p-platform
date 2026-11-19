import { cleanNumberInput } from '../lib/format';
import Field, { describedBy } from './Field';
import TextInput from './TextInput';

/**
 * A percentage (component-api.d.ts, PercentInput): the figure in Plex Mono with a "%" suffix,
 * up to 2 decimal places. Only digits and one decimal point are kept as the user types.
 *
 *   <PercentInput id="investor_profit_share_percent" label="Investors’ share of the profit"
 *       value={form.data.investor_profit_share_percent}
 *       onChange={(value) => form.setData('investor_profit_share_percent', value)} />
 *
 * onChange gets the cleaned text ("60", "8.5"), not a browser event. With a `label` it comes in
 * a Field; without one it is the bare control.
 */
export default function PercentInput({ id, label, hint, error, optional, value, onChange, className = '', ...inputProps }) {
    const control = (
        <TextInput
            id={id}
            name={id}
            suffix="%"
            inputMode="decimal"
            autoComplete="off"
            invalid={Boolean(error)}
            inputClassName="font-mono"
            {...describedBy(id, { hint, error })}
            {...inputProps}
            className={label ? '' : className}
            value={value ?? ''}
            onChange={(event) => onChange?.(cleanNumberInput(event.target.value))}
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
