import { EMPTY, formatAmount } from '../lib/format';

const FIGURE = {
    figure: 'text-figure',
    'figure-lg': 'text-figure-lg',
    'figure-xl': 'text-figure-xl',
};

// The currency code is smaller and muted, before the figure.
const CODE = {
    figure: 'text-code',
    'figure-lg': 'text-figure',
    'figure-xl': 'text-figure-lg',
};

/**
 * An amount of money in IBM Plex Mono with tabular figures: `MVR 12,500.00`
 * (README, "Numbers are the interface"). Right-align it in tables.
 *
 *   <Money value={12500} />                       MVR 12,500.00
 *   <Money value={5000} decimals={0} size="figure-lg" />
 *   <Money value={1200} sign />                   MVR +1,200.00 (received money)
 */
export default function Money({ value, size = 'figure', code = 'MVR', decimals = 2, sign = false, className = '' }) {
    const amount = formatAmount(value, { decimals, sign });

    if (amount === EMPTY) {
        return <span className={`font-mono ${FIGURE[size]} text-text-muted ${className}`}>{EMPTY}</span>;
    }

    return (
        <span className={`inline-flex items-baseline gap-1 whitespace-nowrap font-mono ${className}`}>
            <span className={`${CODE[size]} text-text-muted`}>{code}</span>
            <span className={FIGURE[size]}>{amount}</span>
        </span>
    );
}
