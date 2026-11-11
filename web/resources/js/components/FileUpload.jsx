import { useState } from 'react';
import FormField, { describedBy } from './FormField';

/**
 * A labelled file picker. Calls onChange(file) with the chosen File, or null.
 *
 * `maxBytes` and `tooLargeMessage` stop a file that is obviously too big before it is sent, so
 * the user isn't left waiting for a large upload the server will refuse anyway. This is only a
 * convenience: the server still checks type and size on every upload and its message is the
 * one shown for everything else.
 *
 * To clear the picker after a successful upload, change its `key` (React then mounts a fresh,
 * empty input; a file input's value can't be set from code).
 */
export default function FileUpload({ id, label, hint, error, accept, maxBytes, tooLargeMessage, onChange, className = '' }) {
    const [localError, setLocalError] = useState(null);
    const shownError = localError ?? error;

    function choose(event) {
        const file = event.target.files[0] ?? null;

        if (file && maxBytes && file.size > maxBytes) {
            setLocalError(tooLargeMessage);
            event.target.value = '';
            onChange(null);
            return;
        }

        setLocalError(null);
        onChange(file);
    }

    return (
        <FormField id={id} label={label} hint={hint} error={shownError} className={className}>
            <input
                id={id}
                name={id}
                type="file"
                accept={accept}
                onChange={choose}
                {...describedBy(id, { hint, error: shownError })}
                className={`mt-1 block w-full rounded-md border bg-white text-sm text-slate-700 shadow-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 ${
                    shownError ? 'border-red-400' : 'border-slate-300'
                }`}
            />
        </FormField>
    );
}
