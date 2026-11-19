import cx from '../lib/cx';
import Field, { describedBy } from './Field';

/**
 * A multi-line text box, e.g. an address or a rejection reason.
 *
 *   <Textarea id="reason" label="Reason for rejecting" hint="..." error={form.errors.reason} maxLength={255} />
 *
 * With a `label` it comes in a Field (label, hint, error, `optional` tag). Without one it is the bare control
 * (component-api.d.ts, Textarea) for use inside your own Field. `maxLength` adds a live
 * "12 / 255" counter.
 */
export default function Textarea({ id, label, hint, error, invalid, optional, rows = 3, maxLength, className = '', value, disabled, ...textareaProps }) {
    const isInvalid = invalid ?? Boolean(error);
    const length = typeof value === 'string' ? value.length : 0;

    const control = (
        <div className={cx('control flex flex-col', isInvalid && 'control-invalid', disabled && 'control-disabled', !label && className)}>
            <textarea
                id={id}
                name={id}
                rows={rows}
                value={value}
                disabled={disabled}
                maxLength={maxLength}
                className="min-h-20 w-full resize-y bg-transparent px-3 py-2 text-body text-text outline-none placeholder:text-text-placeholder disabled:cursor-not-allowed"
                {...describedBy(id, { hint, error })}
                aria-invalid={isInvalid ? 'true' : undefined}
                {...textareaProps}
            />
            {maxLength && (
                <p className="self-end px-3 pb-1.5 font-mono text-code text-text-muted" aria-live="polite">
                    {length} / {maxLength}
                </p>
            )}
        </div>
    );

    if (!label) {
        return control;
    }

    return (
        <Field htmlFor={id} label={label} helper={hint} error={error} optional={optional} className={className}>
            {control}
        </Field>
    );
}
