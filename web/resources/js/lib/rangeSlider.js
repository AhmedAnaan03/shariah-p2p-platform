/**
 * The amount slider's scale (PB-16): the handles move along a list of amounts from the server
 * (MarketplaceFilters::AMOUNT_STEPS, roughly logarithmic), not along a straight line of MVR, so
 * MVR 10,000 to 100,000 gets as much of the track as 100,000 to 1,000,000.
 *
 * The slider itself works in positions (0 … steps.length − 1); these turn positions into amounts
 * and back, and the slider's two ends into the URL's amount_min / amount_max. Plain functions
 * with no React, so they are unit-tested on their own (rangeSlider.test.js).
 */
import { formatMoney, toNumber } from './format';

/** The position of the step closest to `amount`; the first or last step when blank or out of range. */
export function nearestStep(amount, steps, fallback = 0) {
    const value = toNumber(amount);
    if (value === null || steps.length === 0) {
        return fallback;
    }
    let best = 0;
    steps.forEach((step, index) => {
        if (Math.abs(step - value) < Math.abs(steps[best] - value)) {
            best = index;
        }
    });
    return best;
}

/** The two handle positions for the filter values from the URL ('' or missing = that end of the track). */
export function positionsFor(minValue, maxValue, steps) {
    const last = steps.length - 1;
    const low = nearestStep(minValue, steps, 0);
    const high = nearestStep(maxValue, steps, last);
    return low <= high ? [low, high] : [high, low];
}

/**
 * The URL values for two handle positions: an end at its limit filters nothing, so it is ''
 * (left out of the URL).
 */
export function valuesFor([low, high], steps) {
    const last = steps.length - 1;
    return {
        min: low <= 0 ? '' : String(steps[low]),
        max: high >= last ? '' : String(steps[high]),
    };
}

/** "MVR 250,000": what a handle's tooltip shows and what a screen reader hears. */
export function amountText(amount) {
    return formatMoney(amount, { decimals: 0 });
}

/** The slider's summary: "Any amount", "MVR 50,000 or more", "Up to MVR 500,000", "MVR 50,000 to MVR 500,000". */
export function rangeText([low, high], steps) {
    const last = steps.length - 1;
    if (low <= 0 && high >= last) {
        return 'Any amount';
    }
    if (high >= last) {
        return `${amountText(steps[low])} or more`;
    }
    if (low <= 0) {
        return `Up to ${amountText(steps[high])}`;
    }
    return `${amountText(steps[low])} to ${amountText(steps[high])}`;
}
