import { FieldError } from './FormField';

/**
 * A set of radio buttons drawn as selectable cards, e.g. the role on the registration form or
 * individual / SME on the borrower profile. `options` is a list of { value, label, description }.
 * onChange receives the chosen value.
 */
export default function RadioGroup({ name, legend, options, value, onChange, error, className = '' }) {
    const errorId = error ? `${name}-error` : undefined;

    return (
        <fieldset className={className}>
            <legend className="text-sm font-medium text-slate-700">{legend}</legend>
            <div className="mt-2 grid gap-3 sm:grid-cols-2">
                {options.map((option) => {
                    const selected = value === option.value;

                    return (
                        <label
                            key={option.value}
                            className={`flex cursor-pointer gap-3 rounded-md border p-3 text-sm ${
                                selected ? 'border-emerald-600 bg-emerald-50' : 'border-slate-300 bg-white hover:border-slate-400'
                            }`}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={selected}
                                onChange={() => onChange(option.value)}
                                aria-describedby={errorId}
                                className="mt-0.5 accent-emerald-700"
                            />
                            <span>
                                <span className="block font-semibold">{option.label}</span>
                                {option.description && <span className="block text-slate-600">{option.description}</span>}
                            </span>
                        </label>
                    );
                })}
            </div>
            <FieldError id={name} error={error} />
        </fieldset>
    );
}
