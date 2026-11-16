import cx from '../lib/cx';
import Button from './Button';
import Icon from './Icon';

const TONES = {
    info: { box: 'border-info-border bg-info-bg', icon: 'info', ink: 'text-info' },
    success: { box: 'border-success-border bg-success-bg', icon: 'check-circle', ink: 'text-success' },
    warning: { box: 'border-warning-border bg-warning-bg', icon: 'warning', ink: 'text-warning' },
    danger: { box: 'border-danger-border bg-danger-bg', icon: 'warning-circle', ink: 'text-danger' },
};

/**
 * A message box with an icon, an optional title, an optional action and an optional dismiss
 * button (component-api.d.ts, Alert). The icon and the words carry the meaning, not the colour.
 *
 *   <Alert tone="warning" title="Your account is pending verification">...</Alert>
 *
 * `role` defaults to "status" (read out politely by screen readers); use "alert" for errors
 * the user must act on now, or "note" for standing information.
 */
export default function Alert({ tone = 'info', title, action, onDismiss, role = 'status', className = '', children }) {
    const style = TONES[tone] ?? TONES.info;

    return (
        <div role={role} className={cx('flex gap-3 rounded-lg border p-4', style.box, className)}>
            <Icon name={style.icon} className={cx('mt-0.5', style.ink)} />
            <div className="min-w-0 flex-1 text-body text-text-secondary">
                {title && <p className="font-semibold text-text">{title}</p>}
                {children && <div className={cx('space-y-1', title && 'mt-1')}>{children}</div>}
                {action && <div className="mt-3">{action}</div>}
            </div>
            {onDismiss && <Button icon="x" size="sm" variant="ghost" aria-label="Dismiss" onClick={onDismiss} className="-my-1 -mr-1" />}
        </div>
    );
}
