import Input from './Input';

/**
 * A whole number such as a count or years (PB-57): an Input in the figure font with tabular
 * digits, a numeric keyboard on phones, and anything but digits dropped as it is typed.
 *
 *   <CountInput id="open_credit_lines" label="..." value={form.data.open_credit_lines}
 *       onChange={(value) => form.setData('open_credit_lines', value)} error={form.errors.open_credit_lines} />
 *
 * onChange gets the digits as text ("3"), not a browser event. The box is narrow, since counts
 * are short, while the label and hint keep the field's full width.
 */
export default function CountInput({ onChange, controlClassName = 'max-w-40', ...props }) {
    return (
        <Input
            inputMode="numeric"
            autoComplete="off"
            figures
            controlClassName={controlClassName}
            {...props}
            onChange={(event) => onChange?.(event.target.value.replace(/\D/g, ''))}
        />
    );
}
