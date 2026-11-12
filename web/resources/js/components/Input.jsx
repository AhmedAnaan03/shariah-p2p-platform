import FormField, { controlClasses, describedBy } from './FormField';

/**
 * A labelled single-line input (text, email, password, number, ...) with an optional hint
 * and the server's validation message. `error` is form.errors.<name>.
 *
 *   <Input id="email" type="email" label="Email address" value={...} onChange={...} error={form.errors.email} />
 */
export default function Input({ id, label, hint, error, className = '', type = 'text', ...inputProps }) {
    return (
        <FormField id={id} label={label} hint={hint} error={error} className={className}>
            <input
                id={id}
                name={id}
                type={type}
                className={controlClasses(error)}
                {...describedBy(id, { hint, error })}
                {...inputProps}
            />
        </FormField>
    );
}
