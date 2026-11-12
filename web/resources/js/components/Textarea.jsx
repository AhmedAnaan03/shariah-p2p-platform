import FormField, { controlClasses, describedBy } from './FormField';

/**
 * A labelled multi-line text box, e.g. an address or a rejection reason.
 */
export default function Textarea({ id, label, hint, error, rows = 3, className = '', ...textareaProps }) {
    return (
        <FormField id={id} label={label} hint={hint} error={error} className={className}>
            <textarea
                id={id}
                name={id}
                rows={rows}
                className={controlClasses(error)}
                {...describedBy(id, { hint, error })}
                {...textareaProps}
            />
        </FormField>
    );
}
