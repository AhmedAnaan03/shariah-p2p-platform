import cx from '../lib/cx';

/**
 * The placeholder brand: "Amanah" in Bricolage Grotesque 700 with a full stop in the mode's
 * primary colour (there is no logo yet). The full stop is decorative.
 */
export default function Wordmark({ size = 'md', className = '' }) {
    return (
        <span className={cx('font-display font-bold tracking-tight text-text', size === 'lg' ? 'text-h2' : 'text-h3', className)}>
            Amanah<span aria-hidden="true" className="text-primary">.</span>
        </span>
    );
}
