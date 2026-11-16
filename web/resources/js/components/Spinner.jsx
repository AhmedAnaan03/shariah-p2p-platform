/**
 * A loading spinner in currentColor. With a `label` it is announced ("Saving…"); without one
 * it is decorative, for buttons that already set aria-busy.
 *
 * The spin is an essential "still working" signal, so it keeps turning with reduced motion,
 * at a slower, calmer speed.
 */
export default function Spinner({ size = 16, label, className = '' }) {
    return (
        <span role={label ? 'status' : undefined} className={`inline-flex shrink-0 ${className}`}>
            <svg
                width={size}
                height={size}
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
                className="animate-spin motion-reduce:[animation-duration:2s]"
            >
                <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.25" strokeWidth="2.5" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
            </svg>
            {label && <span className="sr-only">{label}</span>}
        </span>
    );
}
