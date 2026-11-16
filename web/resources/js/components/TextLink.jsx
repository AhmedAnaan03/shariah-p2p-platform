import { Link } from '@inertiajs/react';
import cx from '../lib/cx';
import Icon from './Icon';

/**
 * An in-text link, e.g. "Forgot password?" or "Back to the queue".
 *
 * Always underlined, so it is told apart from the text around it by more than colour
 * (WCAG 1.4.1); the underline thickens on hover. Pass `external` for a link that must be a
 * normal browser request rather than an Inertia visit, such as opening a KYC document (the
 * server returns the file, not a page). `icon` puts a small icon before the words.
 */
export default function TextLink({ href, external = false, icon, className = '', children, ...props }) {
    const classes = cx(
        'inline-flex items-center gap-1 font-medium text-link underline decoration-1 underline-offset-2 hover:decoration-2',
        className,
    );
    const content = (
        <>
            {icon && <Icon name={icon} size={16} />}
            {children}
        </>
    );

    if (external) {
        return (
            <a href={href} className={classes} {...props}>
                {content}
            </a>
        );
    }

    return (
        <Link href={href} className={classes} {...props}>
            {content}
        </Link>
    );
}
