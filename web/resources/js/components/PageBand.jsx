import cx from '../lib/cx';
import BackgroundPattern from './BackgroundPattern';

/**
 * A page band: the patterned `color-band` strip at the top of a page or section, 32 px padding,
 * radius-xl. The largest header the design system allows; there are no hero sections.
 *
 *   <PageBand><PageHeading ... /></PageBand>
 */
export default function PageBand({ className = '', children }) {
    return (
        <section className={cx('relative overflow-hidden rounded-xl border border-border bg-band p-6 @bp-sm/shell:p-8', className)}>
            <BackgroundPattern />
            <div className="relative">{children}</div>
        </section>
    );
}
