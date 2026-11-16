/**
 * The 16 px khatam (eight-pointed star) from docs/design-system/patterns/khatam-icon-16.svg.
 * Only inside the Shariah badge and the mode switch. Draws in currentColor, so it follows the
 * text colour it sits in. Always next to the word "Shariah", so it is decorative.
 */
export default function KhatamIcon({ size = 16, className = '' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            width={size}
            height={size}
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.25"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
            className={`shrink-0 ${className}`}
        >
            <rect x="3.25" y="3.25" width="9.5" height="9.5" />
            <rect x="3.25" y="3.25" width="9.5" height="9.5" transform="rotate(45 8 8)" />
            <circle cx="8" cy="8" r="1.6" />
        </svg>
    );
}
