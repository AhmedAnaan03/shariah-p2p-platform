import Field, { describedBy } from './Field';
import TextInput from './TextInput';

/**
 * A labelled single-line input (text, email, password, number, ...) with an optional hint and
 * the server's validation message: a Field around a TextInput. `error` is form.errors.<name>.
 *
 *   <Input id="email" type="email" label="Email address" value={...} onChange={...} error={form.errors.email} />
 *
 * Also takes TextInput's size, prefix, suffix and icon, `optional` for the "(optional)" tag, and
 * `controlClassName` to size the box itself (e.g. a narrow box for a count) while the label and
 * hint keep the field's full width.
 */
export default function Input({ id, label, hint, error, optional, className = '', controlClassName, ...inputProps }) {
    return (
        <Field htmlFor={id} label={label} helper={hint} error={error} optional={optional} className={className}>
            <TextInput id={id} name={id} invalid={Boolean(error)} className={controlClassName} {...describedBy(id, { hint, error })} {...inputProps} />
        </Field>
    );
}
