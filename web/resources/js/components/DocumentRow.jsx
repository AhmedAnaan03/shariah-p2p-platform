import Button from './Button';
import Icon from './Icon';
import StatusBadge from './StatusBadge';

const FILE_ICONS = { pdf: 'file-pdf', jpg: 'file-image', jpeg: 'file-image', png: 'file-image' };

/**
 * One uploaded file (component-api.d.ts, DocumentRow): a 24 px file icon, what the document
 * is, the file name, size and upload date, its status, and a View button.
 *
 *   <DocumentRow title="National ID card" name="id-front.jpg" type="jpg" size="820 KB"
 *                uploaded="12 Nov 2026" status="pending" href="/documents/4" />
 *
 * `href` opens the file through the protected document route in a new tab (a normal browser
 * request, since the server returns the file, not a page). `reason` shows an admin's rejection.
 */
export default function DocumentRow({ title, name, type, size, uploaded, status, href, onView, reason }) {
    const extension = (type ?? name?.split('.').pop() ?? '').toLowerCase();

    return (
        <div className="flex flex-wrap items-center gap-x-4 gap-y-3 py-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary-subtle text-primary-ink">
                <Icon name={FILE_ICONS[extension] ?? 'file-text'} size={24} />
            </span>
            <div className="min-w-0 flex-1 basis-48">
                <p className="font-medium text-text">{title ?? name}</p>
                {/* A sentence, so body font: mono inside running text leaves uneven gaps. */}
                <p className="mt-0.5 text-body-sm break-words text-text-muted">
                    {title && <span className="text-text-secondary">{name}</span>}
                    {title && ' · '}
                    {size} · uploaded {uploaded}
                </p>
                {reason && (
                    <p className="mt-1 flex items-start gap-1.5 text-body-sm text-danger">
                        <Icon name="warning-circle" size={16} className="mt-0.5" />
                        <span>Rejected: {reason}</span>
                    </p>
                )}
            </div>
            <div className="flex items-center gap-3">
                {status && <StatusBadge status={status} />}
                {href && (
                    <a
                        href={href}
                        target="_blank"
                        rel="noopener"
                        className="inline-flex h-(--control-sm) items-center gap-2 rounded-md px-3 text-label font-semibold text-text-secondary hover:bg-surface-subtle hover:text-text pointer-coarse:min-h-(--touch-target)"
                    >
                        <Icon name="eye" size={16} />
                        View<span className="sr-only"> {title ?? name} (opens in a new tab)</span>
                    </a>
                )}
                {!href && onView && (
                    <Button variant="ghost" size="sm" iconLeft="eye" onClick={onView}>
                        View
                    </Button>
                )}
            </div>
        </div>
    );
}
