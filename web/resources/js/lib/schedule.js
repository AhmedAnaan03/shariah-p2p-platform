/**
 * A funded campaign's schedule on the borrower's and investor's pages (PB-34, PB-28). The server
 * (SchedulePresenter) sends every label and each row's state already worded for its contract; these
 * helpers only reshape that for the timeline, the chart and the chart's table.
 *
 * A Mudarabah's schedule is planned capital returns, not a debt: its rows never say instalment,
 * due, owed or late, and a passed date is "awaiting business report" (state `awaiting`).
 *
 * Plain functions with no React, so they are unit-tested on their own (schedule.test.js).
 */
import { formatMoney, toNumber } from './format';

/** How each row state looks: Badge tone and icon. The words come from the server. */
export const SCHEDULE_STATES = {
    paid: { tone: 'success', icon: 'check' },
    due: { tone: 'warning', icon: 'clock' },
    upcoming: { tone: 'neutral', icon: 'calendar-blank' },
    late: { tone: 'danger', icon: 'warning' },
    awaiting: { tone: 'info', icon: 'hourglass' },
    // PB-29: a Mudarabah period whose report is waiting for review, or not needed once settled.
    review: { tone: 'info', icon: 'clipboard-text' },
    closed: { tone: 'neutral', icon: 'lock' },
};

/** The chart's series colours, from the theme (they change with the mode and theme). */
export const SERIES_COLORS = {
    capital: 'var(--color-chart-1)',
    ret: 'var(--color-chart-3)',
};

/** True for a Mudarabah's planned capital return schedule. */
export function isCapitalReturn(schedule) {
    return schedule?.labels?.kind === 'capital_returns';
}

/** The rows as RepaymentTimeline items. */
export function timelineItems(rows) {
    return (rows ?? []).map((row) => ({
        id: row.id,
        label: row.label,
        state: row.state,
        stateLabel: row.stateLabel,
        amount: row.amount,
    }));
}

/** The rows as RepaymentScheduleChart data: numbers, with no return for a Mudarabah. */
export function chartData(rows) {
    return (rows ?? []).map((row) => ({
        label: row.monthLabel,
        capital: toNumber(row.capital) ?? 0,
        ret: toNumber(row.return) ?? 0,
    }));
}

/** The chart's legend: capital, and the return series when the contract has one. */
export function chartLegend(labels) {
    const legend = [{ label: labels.capital, color: SERIES_COLORS.capital }];
    return labels.return ? [...legend, { label: labels.return, color: SERIES_COLORS.ret }] : legend;
}

/** The chart's "View as table": the same figures as the chart, one row per month. */
export function chartTable(schedule) {
    const { labels, rows } = schedule;
    const columns = [labels.row, labels.date, labels.capital, ...(labels.return ? [labels.return, 'Total'] : [])];
    return {
        columns,
        rows: rows.map((row) => [
            row.label,
            row.dateLabel,
            formatMoney(row.capital),
            ...(labels.return ? [formatMoney(row.return), formatMoney(row.amount)] : []),
        ]),
    };
}

/** The chart's one-line takeaway. */
export function scheduleTakeaway(schedule) {
    const count = `${schedule.count} ${schedule.count === 1 ? 'month' : 'months'}`;
    if (isCapitalReturn(schedule)) {
        return `${formatMoney(schedule.capitalTotal)} of capital planned to be returned over ${count}, from ${schedule.firstDateLabel}.`;
    }
    return `${formatMoney(schedule.total)} over ${count}: ${formatMoney(schedule.capitalTotal)} capital and ${formatMoney(schedule.returnTotal)} ${schedule.labels.return.toLowerCase()}.`;
}

/** The first row not yet paid, or null when every row is paid. */
export function nextRow(rows) {
    return (rows ?? []).find((row) => row.state !== 'paid') ?? null;
}
