import { motion } from 'motion/react';
import cx from '../lib/cx';
import { duration, ease, useReducedMotion } from '../lib/motion';

const HEIGHTS = { sm: 'h-1.5', md: 'h-2', lg: 'h-3' };

const FILLS = {
    primary: 'bg-primary',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-neutral',
};

/**
 * How far something has got, such as a campaign's funding (component-api.d.ts, ProgressBar).
 * It fills from 0 over `duration-slow` on first view and animates between values (README,
 * "Motion"); with reduced motion it shows the final value straight away.
 *
 *   <ProgressBar value={12500} max={50000} label="Funded" showValue />
 *
 * `label` names it for screen readers (and is shown with `showValue`). Uses the mode's primary
 * unless a status `tone` is given.
 */
export default function ProgressBar({ value, max = 100, label, size = 'md', showValue = false, tone }) {
    const reduced = useReducedMotion();
    const safeMax = max > 0 ? max : 100;
    const percent = Math.max(0, Math.min(100, Math.floor(((Number(value) || 0) / safeMax) * 100)));

    return (
        <div className="w-full">
            {showValue && (
                <div className="mb-1 flex items-baseline justify-between gap-2 text-body-sm">
                    <span className="text-text-muted">{label}</span>
                    <span className="font-mono text-text-secondary">{percent}%</span>
                </div>
            )}
            <div
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
                aria-valuetext={`${percent}%`}
                className={cx('w-full overflow-hidden rounded-sm bg-surface-subtle', HEIGHTS[size] ?? HEIGHTS.md)}
            >
                <motion.div
                    className={cx('h-full rounded-sm', FILLS[tone] ?? FILLS.primary)}
                    initial={reduced ? false : { width: '0%' }}
                    animate={{ width: `${percent}%` }}
                    transition={reduced ? { duration: 0 } : { duration: duration.slow, ease: ease.standard }}
                />
            </div>
        </div>
    );
}
