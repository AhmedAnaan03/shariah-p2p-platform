import { initials } from '../lib/format';

/**
 * A person shown as their initials in a circle. No photos (README, "Iconography and imagery").
 * Decorative: the name is always written next to it.
 */
export default function Avatar({ name, size = 32 }) {
    return (
        <span
            aria-hidden="true"
            className="inline-flex shrink-0 items-center justify-center rounded-full bg-primary-muted font-semibold text-primary-ink"
            style={{ width: size, height: size, fontSize: Math.round(size * 0.4) }}
        >
            {initials(name)}
        </span>
    );
}
