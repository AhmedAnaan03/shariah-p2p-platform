import { Link } from '@inertiajs/react';
import Icon from './Icon';

/**
 * Where this page sits (component-api.d.ts, Breadcrumbs; PB-54). The top bar shows it above the
 * page title. Each item but the last is a link; the last is the current page.
 *
 *   <Breadcrumbs items={[{ label: 'Campaign review', href: '/admin/campaigns/7?return_to=...' }, { label: 'Ibrahim Waheed' }]} />
 *
 * The first link goes to the page the user came from (the page's `back` prop from
 * App\Support\ReturnUrl), so it returns there with its filters, not to a fixed parent.
 */
export default function Breadcrumbs({ items }) {
    return (
        <nav aria-label="Breadcrumb">
            <ol className="flex min-w-0 items-center gap-1 text-body-sm text-text-muted">
                {items.map((item, index) => {
                    const last = index === items.length - 1;

                    return (
                        <li key={`${item.label}-${index}`} className="flex min-w-0 items-center gap-1">
                            {index > 0 && <Icon name="caret-right" size={16} className="shrink-0 text-icon" />}
                            {last || !item.href ? (
                                <span aria-current={last ? 'page' : undefined} className="truncate">
                                    {item.label}
                                </span>
                            ) : (
                                <Link href={item.href} className="truncate rounded-xs text-text-secondary underline decoration-1 underline-offset-2 hover:text-text hover:decoration-2">
                                    {item.label}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
