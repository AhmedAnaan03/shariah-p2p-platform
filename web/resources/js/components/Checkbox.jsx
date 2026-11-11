import { FieldError } from './FormField';

/**
 * A checkbox with its label to the right, and an optional description and server error.
 *
 *   <Checkbox id="remember" label="Remember me" checked={...} onChange={(checked) => ...} />
 *
 * onChange receives true or false, not the browser event.
 */
export default function Checkbox({ id, label, description, error, checked, onChange, className = '' }) {
    const descriptionId = description ? `${id}-description` : undefined;
    const errorId = error ? `${id}-error` : undefined;

    return (
        <div className={className}>
            <label htmlFor={id} className="flex cursor-pointer items-start gap-2 text-sm text-slate-700">
                <input
                    id={id}
                    name={id}
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    aria-invalid={error ? 'true' : undefined}
                    aria-describedby={[descriptionId, errorId].filter(Boolean).join(' ') || undefined}
                    className="mt-0.5 h-4 w-4 shrink-0 accent-emerald-700"
                />
                <span>
                    <span className="font-medium">{label}</span>
                    {description && (
                        <span id={descriptionId} className="mt-0.5 block text-slate-600">
                            {description}
                        </span>
                    )}
                </span>
            </label>
            <FieldError id={id} error={error} />
        </div>
    );
}
