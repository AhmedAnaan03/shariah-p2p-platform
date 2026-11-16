import { formatBytes, formatDate } from '../lib/format';
import DocumentRow from './DocumentRow';
import EmptyState from './EmptyState';

export { formatBytes };

/**
 * KYC documents with their status, for the owner's documents page and the admin review page:
 * one DocumentRow per file, or an empty state. Each `document` comes from
 * KycDocument::toListItem().
 */
export default function DocumentList({ documents, emptyTitle = 'No documents uploaded yet', emptyMessage }) {
    if (documents.length === 0) {
        return (
            <EmptyState kind="documents" title={emptyTitle} compact>
                {emptyMessage}
            </EmptyState>
        );
    }

    return (
        <ul className="divide-y divide-border-subtle">
            {documents.map((document) => (
                <li key={document.id}>
                    <DocumentRow
                        title={document.type_label}
                        name={document.original_name}
                        size={formatBytes(document.size_bytes)}
                        uploaded={formatDate(document.uploaded_at)}
                        status={document.status}
                        href={document.url}
                        reason={document.status === 'rejected' ? document.rejection_reason : null}
                    />
                </li>
            ))}
        </ul>
    );
}
