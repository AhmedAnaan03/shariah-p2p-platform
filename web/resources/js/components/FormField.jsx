/**
 * The frame every form control shares: a label, the control itself, an optional hint and the
 * server's validation message. Input, Select, Textarea and FileUpload are built on it, so
 * labels, hints and errors look and behave the same on every page.
 *
 * Not used by pages directly; pages use the controls.
 */
export default function FormField({ id, label, hint, error, className = '', children }) {
    return (
        <div className={className}>
            <label htmlFor={id} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            {children}
            {hint && (
                <p id={`${id}-hint`} className="mt-1 text-xs text-slate-500">
                    {hint}
                </p>
            )}
            <FieldError id={id} error={error} />
        </div>
    );
}

/**
 * The server's message for one field (form.errors.<name>), or nothing.
 */
export function FieldError({ id, error }) {
    if (!error) {
        return null;
    }

    return (
        <p id={`${id}-error`} className="mt-1 text-sm text-red-600">
            {error}
        </p>
    );
}

/**
 * Accessibility attributes that tie a control to its hint and error, so a screen reader
 * reads the message when the control is focused.
 */
export function describedBy(id, { hint, error }) {
    return {
        'aria-invalid': error ? 'true' : undefined,
        'aria-describedby': [hint && `${id}-hint`, error && `${id}-error`].filter(Boolean).join(' ') || undefined,
    };
}

/**
 * Border and focus styles for text-like controls: red while the field has an error.
 */
export function controlClasses(error) {
    return `mt-1 block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 disabled:bg-slate-100 disabled:text-slate-500 ${
        error
            ? 'border-red-400 focus:border-red-500 focus:ring-red-200'
            : 'border-slate-300 focus:border-emerald-600 focus:ring-emerald-200'
    }`;
}
