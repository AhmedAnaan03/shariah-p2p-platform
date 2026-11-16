import cx from '../lib/cx';
import { formatAmount, formatPercent } from '../lib/format';
import Icon from './Icon';
import Money from './Money';

/**
 * One figure with its label (component-api.d.ts, StatCard): an overline label, the number in
 * Plex Mono, and an optional line under it. `format` is 'count', 'money' or 'pct'.
 *
 *   <StatCard label="Ready for review" value={3} icon="user-list" action={<Button ...>Open queue</Button>} />
 */
export default function StatCard({ label, value, format = 'count', icon, children, action, className = '' }) {
    return (
        <section className={cx('flex flex-col gap-3 rounded-lg border border-border bg-surface p-5 shadow-xs @bp-sm/shell:p-6', className)}>
            <div className="flex items-center justify-between gap-3">
                <h2 className="text-overline text-text-muted">{label}</h2>
                {icon && <Icon name={icon} className="text-icon" />}
            </div>
            {format === 'money' ? (
                <Money value={value} size="figure-xl" />
            ) : (
                <p className="font-mono text-figure-xl text-text">{format === 'pct' ? formatPercent(value) : formatAmount(value, { decimals: 0 })}</p>
            )}
            {children && <div className="text-body text-text-muted">{children}</div>}
            {action && <div className="mt-1">{action}</div>}
        </section>
    );
}
