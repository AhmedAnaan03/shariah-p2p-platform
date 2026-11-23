import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { formatBytes, formatDate } from '../lib/format';
import Button from './Button';
import DocumentRow from './DocumentRow';
import FileDropzone from './FileDropzone';
import Icon from './Icon';

/**
 * One fixed document slot in the onboarding wizard (PB-53): what the slot needs and either an
 * upload box (empty) or the uploaded file with View and Replace (filled).
 *
 *   <DocumentSlot slot={{ type: 'national_id', label: 'National ID card', description: '...', document: null }}
 *                 uploadUrl="/borrower/onboarding/documents" maxBytes={5242880} tooLargeMessage="..." />
 *
 * `slot.document` comes from KycDocument::toListItem(). Replace opens the same upload box; the
 * new file takes the slot and the old one is kept by the server but no longer shown. The file
 * goes up as soon as it is chosen and "Upload" is pressed, so each slot saves on its own.
 * A rejected document shows the administrator's reason and asks for a replacement.
 */
export default function DocumentSlot({ slot, uploadUrl, maxBytes, tooLargeMessage, locked = false }) {
    const form = useForm({ document_type: slot.type, file: null });
    const [replacing, setReplacing] = useState(false);
    // A new key gives a fresh, empty file input after a successful upload.
    const [inputKey, setInputKey] = useState(0);
    const document = slot.document;
    const rejected = document?.status === 'rejected';
    const showUpload = !locked && (!document || replacing || rejected);
    const id = `file-${slot.type}`;

    function upload(event) {
        event.preventDefault();
        form.post(uploadUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('file');
                setInputKey((key) => key + 1);
                setReplacing(false);
            },
        });
    }

    return (
        <li className="rounded-lg border border-border bg-surface p-5 @bp-sm/shell:p-6">
            <div className="flex items-start gap-3">
                <span
                    className={
                        document && !rejected
                            ? 'flex size-8 shrink-0 items-center justify-center rounded-full bg-primary text-primary-fg'
                            : 'flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-border-control text-icon'
                    }
                >
                    <Icon name={document && !rejected ? 'check' : 'file-text'} size={16} weight={document && !rejected ? 'bold' : 'regular'} />
                </span>
                <div className="min-w-0 flex-1">
                    <h3 className="text-h4 text-text">{slot.label}</h3>
                    <p className="mt-1 text-body-sm text-text-muted">{slot.description}</p>
                    <p className="sr-only">{document && !rejected ? 'Uploaded.' : rejected ? 'Rejected, upload a replacement.' : 'Not uploaded yet.'}</p>
                </div>
            </div>

            {document && (
                <div className="mt-2">
                    <DocumentRow
                        title={null}
                        name={document.original_name}
                        size={formatBytes(document.size_bytes)}
                        uploaded={formatDate(document.uploaded_at)}
                        status={document.status}
                        href={document.url}
                        reason={rejected ? document.rejection_reason : null}
                        action={
                            !locked && !rejected && !replacing && (
                                <Button variant="secondary" size="sm" iconLeft="arrows-clockwise" onClick={() => setReplacing(true)}>
                                    Replace<span className="sr-only"> {slot.label}</span>
                                </Button>
                            )
                        }
                    />
                </div>
            )}

            {showUpload && (
                <form onSubmit={upload} noValidate className="mt-4 space-y-4">
                    <FileDropzone
                        key={inputKey}
                        id={id}
                        label={document ? `New file for: ${slot.label}` : `${slot.label} file`}
                        hint="PDF, JPG or PNG. Files must be 5 MB or smaller."
                        accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                        maxBytes={maxBytes}
                        tooLargeMessage={tooLargeMessage}
                        progress={form.progress?.percentage}
                        onChange={(file) => form.setData('file', file)}
                        error={form.errors.file ?? form.errors.document_type}
                    />
                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" variant="secondary" iconLeft="upload-simple" loading={form.processing}>
                            {document ? 'Upload replacement' : 'Upload'}
                            <span className="sr-only"> {slot.label}</span>
                        </Button>
                        {replacing && (
                            <Button variant="ghost" onClick={() => setReplacing(false)}>
                                Keep current file
                            </Button>
                        )}
                    </div>
                </form>
            )}
        </li>
    );
}
