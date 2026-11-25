import Button from './Button';

/**
 * Pages of a long list (PB-16): Previous, the page numbers with gaps ("1 2 3 … 9"), Next, and
 * "Showing 10 to 18 of 23". Every link keeps the list's search and filters (the server builds
 * the URLs) and is an Inertia partial reload of only the list props.
 *
 *   <Pagination page={campaigns} only={['campaigns']} noun={['campaign', 'campaigns']} />
 *
 * `page` is { current_page, last_page, total, from, to, prev_url, next_url, pages: [{ label, url, active }] }.
 * Nothing is shown when everything fits on one page.
 */
export default function Pagination({ page, only = [], noun = ['result', 'results'] }) {
    if (!page || page.last_page <= 1) {
        return null;
    }

    const visit = { only: [...only, 'filters'], preserveState: true };

    return (
        <nav aria-label="Pages" className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-body-sm text-text-muted">
                Showing <span className="font-mono">{page.from}</span> to <span className="font-mono">{page.to}</span> of{' '}
                <span className="font-mono">{page.total}</span> {page.total === 1 ? noun[0] : noun[1]}
            </p>
            <ul className="flex flex-wrap items-center gap-1">
                <li>
                    {page.prev_url ? (
                        <Button href={page.prev_url} variant="secondary" size="sm" iconLeft="caret-left" {...visit}>
                            Previous
                        </Button>
                    ) : (
                        <Button variant="secondary" size="sm" iconLeft="caret-left" disabled>
                            Previous
                        </Button>
                    )}
                </li>
                {page.pages.map((link, index) =>
                    link.url === null ? (
                        <li key={`gap-${index}`} aria-hidden="true" className="px-1 text-text-muted">
                            …
                        </li>
                    ) : (
                        <li key={link.label}>
                            <Button
                                href={link.url}
                                variant={link.active ? 'primary' : 'ghost'}
                                size="sm"
                                aria-current={link.active ? 'page' : undefined}
                                aria-label={`Page ${link.label}`}
                                {...visit}
                            >
                                <span className="font-mono">{link.label}</span>
                            </Button>
                        </li>
                    ),
                )}
                <li>
                    {page.next_url ? (
                        <Button href={page.next_url} variant="secondary" size="sm" iconRight="caret-right" {...visit}>
                            Next
                        </Button>
                    ) : (
                        <Button variant="secondary" size="sm" iconRight="caret-right" disabled>
                            Next
                        </Button>
                    )}
                </li>
            </ul>
        </nav>
    );
}
