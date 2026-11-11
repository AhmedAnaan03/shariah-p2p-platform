import { Link } from '@inertiajs/react';

/**
 * An in-text link, e.g. "Forgot password?" or "Back to your portal".
 *
 * Pass `external` for a link that must be a normal browser request rather than an Inertia
 * visit, such as opening a KYC document (the server returns the file, not a page).
 */
export default function TextLink({ href, external = false, className = '', children, ...props }) {
    const classes = `font-medium text-emerald-700 hover:underline ${className}`;

    if (external) {
        return (
            <a href={href} className={classes} {...props}>
                {children}
            </a>
        );
    }

    return (
        <Link href={href} className={classes} {...props}>
            {children}
        </Link>
    );
}
