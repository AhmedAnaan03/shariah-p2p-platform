import cx from '../lib/cx';
import BackgroundPattern from './BackgroundPattern';
import Icon from './Icon';

const KINDS = {
    campaigns: 'storefront',
    documents: 'file-text',
    queue: 'user-list',
    search: 'magnifying-glass',
    notifications: 'bell',
};

/**
 * What a list shows when it has nothing in it: a simple line illustration, a title, a sentence
 * on what to do, and an optional action (README, "Iconography and imagery").
 *
 *   <EmptyState kind="queue" title="No accounts are waiting">New accounts appear here once ...</EmptyState>
 *
 * `banded` puts it on the patterned band; `compact` is the small version for popovers.
 */
export default function EmptyState({ kind = 'documents', title, children, action, banded = false, compact = false }) {
    return (
        <div
            className={cx(
                'relative flex flex-col items-center overflow-hidden text-center',
                compact ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-10',
                banded && 'rounded-lg border border-border bg-band',
            )}
        >
            {banded && <BackgroundPattern />}
            <Illustration icon={KINDS[kind] ?? KINDS.documents} compact={compact} />
            <div className="relative max-w-prose">
                <p className={cx('font-semibold text-text', compact ? 'text-body' : 'text-h4')}>{title}</p>
                {children && <div className={cx('mt-1 text-body', banded ? 'text-text-secondary' : 'text-text-muted')}>{children}</div>}
            </div>
            {action && <div className="relative mt-2">{action}</div>}
        </div>
    );
}

/** A soft primary-subtle disc, an accent-soft offset ring and a line icon in border-control. */
function Illustration({ icon, compact }) {
    const size = compact ? 'size-12' : 'size-18';

    return (
        <span aria-hidden="true" className={cx('relative flex items-center justify-center rounded-full bg-primary-subtle', size)}>
            <span className="absolute -top-1 -right-1 size-4 rounded-full border-2 border-accent-soft bg-surface" />
            <Icon name={icon} size={compact ? 24 : 32} className="text-border-control" />
        </span>
    );
}
