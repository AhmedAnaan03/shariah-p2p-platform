import { useState } from 'react';
import cx from '../lib/cx';
import { SCHEDULE_STATES } from '../lib/schedule';
import Button from './Button';
import Icon from './Icon';
import Money from './Money';

// Static class names, so Tailwind can find them. The marker's tone matches Badge.
const MARKER = {
    success: 'border-success-border bg-success-bg text-success',
    warning: 'border-warning-border bg-warning-bg text-warning',
    danger: 'border-danger-border bg-danger-bg text-danger',
    info: 'border-info-border bg-info-bg text-info',
    neutral: 'border-neutral-border bg-neutral-bg text-neutral',
};

const STATE_TEXT = {
    danger: 'text-danger',
    warning: 'text-warning',
    info: 'text-info',
    success: 'text-success',
    neutral: 'text-text-muted',
};

/**
 * A schedule as a vertical timeline (component-api.d.ts, RepaymentTimeline; PB-34): one row per
 * instalment or, for a Mudarabah, per planned capital return. Each row has a marker (icon and
 * tone), its label, its state in words (from the server, e.g. "Due 26 Dec 2026", "3 days late",
 * "Planned date passed, awaiting business report") and its amount. State is never shown by
 * colour alone: the icon and the words carry it.
 *
 *   <RepaymentTimeline items={timelineItems(schedule.rows)} />
 *
 * Long schedules show `limit` rows from the one before the next unpaid row, with "Show all".
 */
export default function RepaymentTimeline({ items, limit = 6, label = 'Schedule' }) {
    const [expanded, setExpanded] = useState(false);
    const next = Math.max(0, items.findIndex((item) => item.state !== 'paid'));
    const start = expanded ? 0 : Math.max(0, Math.min(next - 1, items.length - limit));
    const shown = expanded ? items : items.slice(start, start + limit);

    return (
        <div className="space-y-3">
            <ol aria-label={label}>
                {shown.map((item, index) => {
                    const { tone, icon } = SCHEDULE_STATES[item.state] ?? SCHEDULE_STATES.upcoming;
                    const last = index === shown.length - 1;
                    return (
                        <li key={item.id ?? item.label} className="flex gap-4">
                            <div className="flex flex-col items-center">
                                <span className={cx('flex size-8 shrink-0 items-center justify-center rounded-full border', MARKER[tone])}>
                                    <Icon name={icon} size={16} />
                                </span>
                                {!last && <span aria-hidden="true" className="w-px flex-1 bg-border" />}
                            </div>
                            <div className={cx('flex min-w-0 flex-1 flex-wrap items-start justify-between gap-x-4 gap-y-1', !last && 'pb-5')}>
                                <div className="min-w-0">
                                    <p className="text-label text-text">{item.label}</p>
                                    <p className={cx('text-body-sm', STATE_TEXT[tone])}>{item.stateLabel}</p>
                                </div>
                                <Money value={item.amount} />
                            </div>
                        </li>
                    );
                })}
            </ol>
            {items.length > limit && (
                <Button variant="ghost" size="sm" iconRight={expanded ? 'caret-down' : 'caret-right'} onClick={() => setExpanded(!expanded)} aria-expanded={expanded}>
                    {expanded ? 'Show fewer' : `Show all ${items.length}`}
                </Button>
            )}
        </div>
    );
}
