import { motion } from 'motion/react';
import { duration, ease, useReducedMotion } from '../lib/motion';

/**
 * A campaign's funding as a ring, with the percentage in the centre (component-api.d.ts,
 * FundingRing). It fills from 0 over `duration-slow` on first view and animates when a pledge
 * lands; with reduced motion it shows the final value at once.
 *
 *   <FundingRing value={0} max={120000} label="funded" />
 *   <FundingRing value={0} max={120000}>{details}</FundingRing>   details beside the ring
 *
 * `size` and `stroke` are the SVG's own dimensions in px (not page styling). Exposed as a
 * progressbar, so the value is read out, not only drawn.
 *
 * `children` (raised, target, investors) sit to the right of the ring while there is room for
 * at least 10 rem of text, and move under it when there isn't. Their column has a real width
 * (a flex basis), so text inside wraps by word and never collapses to one letter per line.
 */
export default function FundingRing({ children, ...ring }) {
    if (!children) {
        return <Ring {...ring} />;
    }

    return (
        <div className="flex flex-wrap items-center gap-x-6 gap-y-4">
            <Ring {...ring} />
            <div data-funding-details className="min-w-0 flex-1 basis-40">
                {children}
            </div>
        </div>
    );
}

function Ring({ value, max, size = 136, stroke = 12, label = 'funded' }) {
    const reduced = useReducedMotion();
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const percent = max > 0 ? Math.max(0, Math.min(100, Math.floor(((Number(value) || 0) / max) * 100))) : 0;
    const offset = circumference * (1 - percent / 100);

    return (
        <div
            role="progressbar"
            aria-label={`Funding: ${percent}% ${label}`}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
            className="relative inline-grid shrink-0 place-items-center"
            style={{ width: size, height: size }}
        >
            <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} aria-hidden="true" className="-rotate-90">
                <circle cx={size / 2} cy={size / 2} r={radius} fill="none" strokeWidth={stroke} className="stroke-border" />
                <motion.circle
                    cx={size / 2}
                    cy={size / 2}
                    r={radius}
                    fill="none"
                    strokeWidth={stroke}
                    strokeLinecap={percent > 0 ? 'round' : 'butt'}
                    strokeDasharray={circumference}
                    className="stroke-primary"
                    initial={reduced ? false : { strokeDashoffset: circumference }}
                    animate={{ strokeDashoffset: offset }}
                    transition={reduced ? { duration: 0 } : { duration: duration.slow, ease: ease.standard }}
                />
            </svg>
            <div aria-hidden="true" className="absolute inset-0 flex flex-col items-center justify-center text-center whitespace-nowrap">
                <span className="font-mono text-figure-lg text-text">{percent}%</span>
                <span className="text-body-sm text-text-muted">{label}</span>
            </div>
        </div>
    );
}
