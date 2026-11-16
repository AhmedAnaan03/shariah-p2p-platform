import cx from '../lib/cx';

/**
 * A white panel with an optional heading, description and actions (top right). Cards rely on a
 * 1 px border more than shadow, and never move (only link cards rise on hover). 24 px padding,
 * 20 on phones.
 *
 *   <Card title="Personal details" description="..." actions={<Button ...>Edit</Button>}>...</Card>
 *
 * The page's h1 is in the top bar, so a card's title is an h2 by default (`titleAs` to change
 * it); it always looks like the h3 card-title style. Give every card a job.
 */
export default function Card({ title, titleAs: Heading = 'h2', description, actions, className = '', children }) {
    const hasHeader = title || description || actions;

    return (
        <section className={cx('rounded-lg border border-border bg-surface p-5 shadow-xs @bp-sm/shell:p-6', className)}>
            {hasHeader && (
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 max-w-prose">
                        {title && <Heading className={Heading === 'h1' ? 'text-h2 text-text' : 'text-h3 text-text'}>{title}</Heading>}
                        {description && <p className="mt-1 text-body text-text-muted">{description}</p>}
                    </div>
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </div>
            )}
            {children && <div className={hasHeader ? 'mt-5' : ''}>{children}</div>}
        </section>
    );
}
