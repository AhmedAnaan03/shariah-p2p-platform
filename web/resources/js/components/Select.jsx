import FormField, { controlClasses, describedBy } from './FormField';

/**
 * A labelled drop-down. `options` is a list of { value, label }; `placeholder` adds a first,
 * empty choice so nothing is selected by accident.
 *
 *   <Select id="sector" label="Sector" options={sectors} placeholder="Choose a sector" ... />
 */
export default function Select({ id, label, hint, error, options, placeholder, className = '', ...selectProps }) {
    return (
        <FormField id={id} label={label} hint={hint} error={error} className={className}>
            <select id={id} name={id} className={controlClasses(error)} {...describedBy(id, { hint, error })} {...selectProps}>
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </FormField>
    );
}
