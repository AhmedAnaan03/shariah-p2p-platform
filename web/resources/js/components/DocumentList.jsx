import { StatusBadge } from './Badge';
import TextLink from './TextLink';

/**
 * File size in a form people read: "820 KB", "1.4 MB".
 */
export function formatBytes(bytes) {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/**
 * KYC documents with their status, for the owner's documents page and the admin review page.
 * Each `document` comes from KycDocument::toListItem(). The link goes to the server's
 * document route (owner or admin only), opened in a new tab as a normal browser request.
 */
export default function DocumentList({ documents, emptyMessage = 'No documents uploaded yet.' }) {
    if (documents.length === 0) {
        return <p className="text-sm text-slate-600">{emptyMessage}</p>;
    }

    return (
        <ul className="divide-y divide-slate-200 rounded-md border border-slate-200">
            {documents.map((document) => (
                <li key={document.id} className="flex flex-wrap items-start justify-between gap-3 p-4 text-sm">
                    <div>
                        <p className="font-medium text-slate-900">{document.type_label}</p>
                        <p className="mt-0.5 text-slate-600">
                            <TextLink href={document.url} external target="_blank" rel="noopener">
                                {document.original_name}
                            </TextLink>{' '}
                            · {formatBytes(document.size_bytes)} · uploaded{' '}
                            {new Date(document.uploaded_at).toLocaleDateString('en-GB', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                            })}
                        </p>
                        {document.status === 'rejected' && document.rejection_reason && (
                            <p className="mt-1 text-red-700">Rejected: {document.rejection_reason}</p>
                        )}
                    </div>
                    <StatusBadge status={document.status} />
                </li>
            ))}
        </ul>
    );
}
