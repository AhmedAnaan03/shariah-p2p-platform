import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatCompactMoney } from '../lib/format';
import { duration, useReducedMotion } from '../lib/motion';
import { SERIES_COLORS } from '../lib/schedule';
import Money from './Money';

// README, "Motion": chart bars grow from the baseline, staggered 40 ms per series.
const STAGGER_MS = 40;

// Axis ticks in body-sm, chart-axis colour (README, "Data visualisation").
const TICK = { fill: 'var(--color-chart-axis)', fontSize: 'var(--text-body-sm)' };

/**
 * A schedule's months as stacked bars, capital below and return above (component-api.d.ts,
 * RepaymentScheduleChart; PB-34). Built with Recharts: `<BarChart>` with two `<Bar stackId>`,
 * horizontal gridlines only, no axis lines, 4 px top corners on the top of each stack.
 *
 *   <RepaymentScheduleChart data={chartData(schedule.rows)} capitalLabel="Capital" returnLabel="Markup" />
 *
 * A Mudarabah has no return series (`returnLabel` null): its bars are planned capital returns.
 * With reduced motion the bars appear at full height with no animation. Put it in a ChartFrame,
 * which gives the title, takeaway, legend and table.
 */
export default function RepaymentScheduleChart({ data, height = 240, capitalLabel = 'Capital', returnLabel = 'Return' }) {
    const reduced = useReducedMotion();
    const animation = (series) => ({
        isAnimationActive: !reduced,
        animationBegin: series * STAGGER_MS,
        animationDuration: duration.slow * 1000,
        animationEasing: 'ease-out',
    });
    const top = [4, 4, 0, 0];

    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} margin={{ top: 8, right: 0, bottom: 0, left: 0 }}>
                <CartesianGrid vertical={false} stroke="var(--color-chart-grid)" />
                <XAxis dataKey="label" axisLine={false} tickLine={false} tick={TICK} interval="preserveStartEnd" minTickGap={16} />
                <YAxis axisLine={false} tickLine={false} tick={TICK} tickFormatter={(value) => formatCompactMoney(value)} width="auto" />
                <Tooltip cursor={{ fill: 'var(--color-surface-subtle)' }} content={<ScheduleTooltip capitalLabel={capitalLabel} returnLabel={returnLabel} />} />
                <Bar dataKey="capital" name={capitalLabel} stackId="row" fill={SERIES_COLORS.capital} radius={returnLabel ? 0 : top} {...animation(0)} />
                {returnLabel && <Bar dataKey="ret" name={returnLabel} stackId="row" fill={SERIES_COLORS.ret} radius={top} {...animation(1)} />}
            </BarChart>
        </ResponsiveContainer>
    );
}

/**
 * README, "Data visualisation": surface-raised, radius-md, shadow-md, an overline title, figures
 * in Plex Mono and a key dot per series.
 */
function ScheduleTooltip({ active, payload, label, capitalLabel, returnLabel }) {
    if (!active || !payload?.length) {
        return null;
    }
    const row = payload[0].payload;
    const series = [
        { name: capitalLabel, value: row.capital, color: SERIES_COLORS.capital },
        ...(returnLabel ? [{ name: returnLabel, value: row.ret, color: SERIES_COLORS.ret }] : []),
    ];

    return (
        <div className="rounded-md border border-border bg-surface-raised p-3 shadow-md">
            <p className="text-overline text-text-muted">{label}</p>
            <ul className="mt-2 space-y-1">
                {series.map((item) => (
                    <li key={item.name} className="flex items-center justify-between gap-4 text-body-sm text-text-secondary">
                        <span className="flex items-center gap-2">
                            <span aria-hidden="true" className="size-2 rounded-full" style={{ backgroundColor: item.color }} />
                            {item.name}
                        </span>
                        <Money value={item.value} />
                    </li>
                ))}
            </ul>
        </div>
    );
}
