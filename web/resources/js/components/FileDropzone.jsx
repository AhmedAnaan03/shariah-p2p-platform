import { useRef, useState } from 'react';
import cx from '../lib/cx';
import { formatBytes } from '../lib/format';
import { describedBy, FieldError } from './Field';
import Icon from './Icon';

/**
 * A labelled file picker you can also drop a file on (component-api.d.ts, FileDropzone;
 * replaces FileUpload). Calls onChange(file) with the chosen File, or null.
 *
 *   <FileDropzone id="file" label="File" hint="PDF, JPG or PNG. Files must be 5 MB or smaller."
 *       accept=".pdf,.jpg,.jpeg,.png" maxBytes={5 * 1024 * 1024} progress={form.progress?.percentage}
 *       onChange={(file) => form.setData('file', file)} error={form.errors.file} />
 *
 * A real <input type="file"> sits under the whole zone, so clicking, tapping, keyboard focus
 * and screen readers all work as for a normal file input. `maxBytes` stops a file that is too
 * big before it is sent, with a message naming its size ("This file is 7.2 MB. Files must be
 * 5 MB or smaller."); `tooLargeMessage` is the server's own sentence for the rule, so the two
 * always agree. The server still checks type and size on every upload. `progress`
 * (0–100) shows the upload bar while sending.
 *
 * To clear it after a successful upload, change its `key`.
 */
export default function FileDropzone({ id, label, hint, error, accept, maxBytes, tooLargeMessage, progress, disabled = false, onChange, className = '' }) {
    const [file, setFile] = useState(null);
    const [localError, setLocalError] = useState(null);
    const [dragging, setDragging] = useState(false);
    const input = useRef(null);
    const shownError = localError ?? error;

    function choose(chosen) {
        if (chosen && maxBytes && chosen.size > maxBytes) {
            const rule = tooLargeMessage ?? `Files must be ${formatBytes(maxBytes).replace('.0 ', ' ')} or smaller.`;
            setLocalError(`This file is ${formatBytes(chosen.size)}. ${rule}`);
            setFile(null);
            if (input.current) input.current.value = '';
            onChange?.(null);
            return;
        }
        setLocalError(null);
        setFile(chosen);
        onChange?.(chosen);
    }

    function drop(event) {
        event.preventDefault();
        setDragging(false);
        const dropped = event.dataTransfer.files[0];
        if (dropped && !disabled) {
            // Put the dropped file into the real input too, so it behaves like a picked file.
            const transfer = new DataTransfer();
            transfer.items.add(dropped);
            input.current.files = transfer.files;
            choose(dropped);
        }
    }

    const sending = typeof progress === 'number';

    return (
        <div className={cx('flex flex-col gap-2', className)}>
            <label htmlFor={id} className="text-label text-text">
                {label}
            </label>
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={drop}
                className={cx(
                    'relative flex flex-col items-center gap-2 rounded-lg border-(length:--border-width-focus) border-dashed px-6 py-8 text-center transition-colors duration-(--duration-fast)',
                    'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-focus-ring',
                    dragging ? 'border-primary bg-primary-subtle' : shownError ? 'border-danger bg-surface' : 'border-border-control bg-surface hover:bg-surface-subtle',
                    disabled && 'pointer-events-none opacity-(--opacity-disabled)',
                )}
            >
                <span className="flex size-10 items-center justify-center rounded-full bg-primary-subtle text-primary-ink">
                    <Icon name="upload-simple" size={24} />
                </span>
                <p className="text-body text-text">
                    <span className="font-semibold text-link underline underline-offset-2">Choose a file</span> or drag it here
                </p>
                {hint && (
                    <p id={`${id}-hint`} className="text-body-sm text-text-muted">
                        {hint}
                    </p>
                )}
                <input
                    ref={input}
                    id={id}
                    name={id}
                    type="file"
                    accept={accept}
                    disabled={disabled}
                    onChange={(event) => choose(event.target.files[0] ?? null)}
                    {...describedBy(id, { hint, error: shownError })}
                    className="absolute inset-0 cursor-pointer opacity-0"
                />
            </div>

            {file && (
                <div className="flex items-center gap-3 rounded-md border border-border bg-surface px-3 py-2">
                    <Icon name="file-text" className="text-icon" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-body text-text">{file.name}</p>
                        {sending ? (
                            <div
                                role="progressbar"
                                aria-label={`Uploading ${file.name}`}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-valuenow={Math.round(progress)}
                                className="mt-1.5 h-1.5 overflow-hidden rounded-sm bg-surface-subtle"
                            >
                                <div
                                    className="h-full rounded-sm bg-primary transition-[width] duration-(--duration-slow) ease-standard"
                                    style={{ width: `${progress}%` }}
                                />
                            </div>
                        ) : (
                            <p className="font-mono text-code text-text-muted">{formatBytes(file.size)}</p>
                        )}
                    </div>
                    {sending && <span className="font-mono text-code text-text-muted">{Math.round(progress)}%</span>}
                </div>
            )}

            <FieldError id={id} error={shownError} />
        </div>
    );
}
