import cx from '../lib/cx';
import Icon from './Icon';

const TONES = {
    neutral: 'border-neutral-border bg-neutral-bg text-neutral',
    success: 'border-success-border bg-success-bg text-success',
    warning: 'border-warning-border bg-warning-bg text-warning',
    danger: 'border-danger-border bg-danger-bg text-danger',
    info: 'border-info-border bg-info-bg text-info',
    // Tinted with the mode's primary: the Shariah and role badges.
    primary: 'border-primary-muted bg-primary-subtle text-primary-ink',
};

const SIZES = {
    sm: 'h-5 gap-1 px-1.5 text-body-sm',
    md: 'h-6 gap-1.5 px-2 text-label',
};

/**
 * A small label (component-api.d.ts, Badge). Status colours are shared by both modes; pair a
 * tone with an icon and a word, never colour alone. For statuses use StatusBadge.
 *
 *   <Badge tone="info" icon="clock">Closes in 3 days</Badge>
 */
export default function Badge({ tone = 'neutral', icon, size = 'md', className = '', children }) {
    return (
        <span className={cx('inline-flex shrink-0 items-center rounded-sm border font-medium whitespace-nowrap', TONES[tone] ?? TONES.neutral, SIZES[size], className)}>
            {icon && <Icon name={icon} size={size === 'sm' ? 12 : 16} />}
            {children}
        </span>
    );
}
