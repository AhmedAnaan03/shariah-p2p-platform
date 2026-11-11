/**
 * A labelled text input with an optional hint and the server's validation message.
 * `error` is the message Laravel sent back for this field (form.errors.<name>).
 */
export default function Field({ id, label, error, hint, className = '', ...inputProps }) {
    const hintId = hint ? `${id}-hint` : undefined;
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div className={className}>
            <label htmlFor={id} className="block text-sm font-medium text-slate-700">
                {label}
            </label>
            <input
                id={id}
                name={id}
                aria-invalid={error ? 'true' : undefined}
                aria-describedby={[hintId, errorId].filter(Boolean).join(' ') || undefined}
                className={`mt-1 block w-full rounded-md border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 ${
                    error
                        ? 'border-red-400 focus:border-red-500 focus:ring-red-200'
                        : 'border-slate-300 focus:border-emerald-600 focus:ring-emerald-200'
                }`}
                {...inputProps}
            />
            {hint && (
                <p id={hintId} className="mt-1 text-xs text-slate-500">
                    {hint}
                </p>
            )}
            {error && (
                <p id={errorId} className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}
