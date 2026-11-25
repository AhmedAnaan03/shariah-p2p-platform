import cx from '../lib/cx';
import Tooltip from './Tooltip';

/** What each grade means, in words, so a grade never relies on its colour. */
export const GRADE_MEANINGS = {
    A: 'Lowest risk',
    B: 'Low risk',
    C: 'Moderate risk',
    D: 'High risk',
    E: 'Highest risk',
};

// Listed in full so Tailwind finds every class. Risk colours are shared by both modes and never green.
const TINTED = {
    A: 'bg-risk-a-soft text-risk-a-ink',
    B: 'bg-risk-b-soft text-risk-b-ink',
    C: 'bg-risk-c-soft text-risk-c-ink',
    D: 'bg-risk-d-soft text-risk-d-ink',
    E: 'bg-risk-e-soft text-risk-e-ink',
};

const SOLID = {
    A: 'bg-risk-a-ink text-surface',
    B: 'bg-risk-b-ink text-surface',
    C: 'bg-risk-c-ink text-surface',
    D: 'bg-risk-d-ink text-surface',
    E: 'bg-risk-e-ink text-surface',
};

const SIZES = {
    sm: 'size-6 text-label',
    md: 'size-8 text-h4',
    lg: 'size-16 text-h2',
};

/**
 * A risk grade chip, A to E (component-api.d.ts, RiskGrade): the letter in Bricolage Grotesque
 * on its grade colour, tinted by default. The letter is always shown and the meaning is read
 * out ("Risk grade B, low risk"); `tooltip` also shows it on hover and focus.
 *
 *   <RiskGrade grade="B" />   <RiskGrade grade="D" size="lg" variant="solid" />
 */
export default function RiskGrade({ grade, variant = 'tinted', size = 'md', tooltip = false }) {
    if (!GRADE_MEANINGS[grade]) {
        return null;
    }

    const label = `Risk grade ${grade}, ${GRADE_MEANINGS[grade].toLowerCase()}`;
    const chip = (
        <span
            role="img"
            aria-label={label}
            tabIndex={tooltip ? 0 : undefined}
            className={cx('inline-grid shrink-0 place-items-center rounded-full font-display font-semibold', (variant === 'solid' ? SOLID : TINTED)[grade], SIZES[size] ?? SIZES.md)}
        >
            <span aria-hidden="true">{grade}</span>
        </span>
    );

    return tooltip ? <Tooltip content={`Grade ${grade}: ${GRADE_MEANINGS[grade]}`}>{chip}</Tooltip> : chip;
}
