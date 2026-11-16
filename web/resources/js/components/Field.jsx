import Icon from './Icon';

/**
 * The frame every form control shares: label, the control, an optional helper line and the
 * server's validation message (component-api.d.ts, Field). Input, Select, Textarea and
 * FileDropzone are built on it, so labels and errors look and behave the same everywhere.
 *
 *   <Field label="Business name" htmlFor="business_name" optional helper="..." error={form.errors.business_name}>
 *       <TextInput id="business_name" ... />
 *   </Field>
 *
 * Spacing: label to control space-2. Errors say what went wrong and how to fix it.
 */
export default function Field({ label, htmlFor, required = false, optional = false, helper, error, className = '', children }) {
    return (
        <div className={`flex flex-col gap-2 ${className}`}>
            {label && (
                <label htmlFor={htmlFor} className="text-label text-text">
                    {label}
                    {optional && <span className="font-normal text-text-muted"> (optional)</span>}
                    {required && <span className="sr-only"> (required)</span>}
                </label>
            )}
            {children}
            {helper && (
                <p id={`${htmlFor}-hint`} className="text-body-sm text-text-muted">
                    {helper}
                </p>
            )}
            <FieldError id={htmlFor} error={error} />
        </div>
    );
}

/**
 * The server's message for one field (form.errors.<name>), or nothing. Slides down 4 px.
 */
export function FieldError({ id, error }) {
    if (!error) {
        return null;
    }

    return (
        <p id={`${id}-error`} className="flex animate-error items-start gap-1.5 text-body-sm text-danger">
            <Icon name="warning-circle" size={16} className="mt-0.5" />
            <span>{error}</span>
        </p>
    );
}

/**
 * Accessibility attributes that tie a control to its helper and error, so a screen reader
 * reads the message when the control is focused.
 */
export function describedBy(id, { hint, error }) {
    return {
        'aria-invalid': error ? 'true' : undefined,
        'aria-describedby': [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined,
    };
}
