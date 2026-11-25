import { PLATFORM_NAME } from '../lib/brand';
import cx from '../lib/cx';

/**
 * The platform's name, "Amanah P2P", in Bricolage Grotesque 700 with a full stop in the mode's
 * primary colour (there is no logo yet). The full stop is decorative. `lg` is the sidebar and
 * auth pages, `md` the site header.
 */
export default function Wordmark({ size = 'md', className = '' }) {
    return (
        <span className={cx('font-display font-bold tracking-tight text-text', size === 'lg' ? 'text-h2' : 'text-h3', className)}>
            {PLATFORM_NAME}
            <span aria-hidden="true" className="text-primary">
                .
            </span>
        </span>
    );
}
