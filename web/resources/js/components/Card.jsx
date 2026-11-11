/**
 * A white panel with an optional heading, description and actions (top right).
 *
 *   <Card title="Your profile" titleAs="h1" description="..." actions={<Button ...>Edit</Button>}>
 *       ...
 *   </Card>
 *
 * `titleAs` sets the heading level: "h1" for the main card of a page, "h2" (default) otherwise.
 */
export default function Card({ title, titleAs: Heading = 'h2', description, actions, className = '', children }) {
    const hasHeader = title || description || actions;

    return (
        <section className={`rounded-lg border border-slate-200 bg-white p-6 shadow-sm ${className}`}>
            {hasHeader && (
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        {title && (
                            <Heading className={Heading === 'h1' ? 'text-2xl font-semibold' : 'text-lg font-semibold'}>{title}</Heading>
                        )}
                        {description && <p className="mt-1 text-sm text-slate-600">{description}</p>}
                    </div>
                    {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
                </div>
            )}
            {children && <div className={hasHeader ? 'mt-5' : ''}>{children}</div>}
        </section>
    );
}
