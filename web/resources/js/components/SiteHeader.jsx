import { Link } from '@inertiajs/react';
import Wordmark from './Wordmark';
import { PLATFORM_NAME } from '../lib/brand';

/**
 * The bar at the top of a public page: the wordmark (home link) and the page's actions,
 * e.g. "Sign in" and "Create an account". White, with a bottom border, like the portal top bar.
 */
export default function SiteHeader({ actions }) {
    return (
        <header className="border-b border-border bg-surface">
            <div className="mx-auto flex h-(--topbar) max-w-(--content-max) items-center justify-between gap-4 px-4 @bp-sm/shell:px-8">
                <Link href="/" aria-label={`${PLATFORM_NAME}, home`} className="rounded-sm">
                    <Wordmark />
                </Link>
                {actions && <div className="flex items-center gap-2">{actions}</div>}
            </div>
        </header>
    );
}
