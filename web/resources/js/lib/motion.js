/**
 * The motion tokens from tailwind-theme.css as JS values for Motion (Framer Motion), which
 * takes seconds and cubic-bezier arrays rather than CSS strings. motion.test.js checks they
 * still match the CSS, so the two can't drift apart.
 *
 *   <motion.div transition={{ duration: duration.moderate, ease: ease.enter }} />
 */
import { useReducedMotion } from 'motion/react';

export { useReducedMotion };

/** Seconds. */
export const duration = {
    instant: 0.1, // press, tick
    fast: 0.15, // hover, focus, exits
    base: 0.2, // dropdowns, popovers, toasts
    moderate: 0.25, // page, drawer, modal
    slow: 0.4, // progress, bars
    mode: 0.5, // the conventional ⇄ Shariah cross-fade
    count: 0.8, // count-up
};

export const ease = {
    standard: [0.2, 0, 0, 1],
    enter: [0, 0, 0.2, 1],
    exit: [0.4, 0, 1, 1],
    mode: [0.4, 0, 0.2, 1],
};

/** If a spring is ever needed. No bounce. */
export const spring = { type: 'spring', stiffness: 400, damping: 40 };

/**
 * The page change (README, "Motion"): the new page fades in and rises 8 px over 250 ms, the old
 * one fades out over 150 ms. With reduced motion there is no movement, only a fade of 100 ms.
 */
export function pageTransition(reduced) {
    if (reduced) {
        return {
            initial: { opacity: 0 },
            animate: { opacity: 1, transition: { duration: duration.instant } },
            exit: { opacity: 0, transition: { duration: duration.instant } },
        };
    }
    return {
        initial: { opacity: 0, y: 8 },
        animate: { opacity: 1, y: 0, transition: { duration: duration.moderate, ease: ease.enter } },
        exit: { opacity: 0, transition: { duration: duration.fast, ease: ease.exit } },
    };
}

/** A row leaving a list (an approved user leaving the queue): height and opacity, 200 ms. */
export function rowExit(reduced) {
    return reduced
        ? { opacity: 0, transition: { duration: duration.instant } }
        : { opacity: 0, height: 0, transition: { duration: duration.base, ease: ease.exit } };
}

/** Button press: scale to 0.98 over 100 ms (none with reduced motion). */
export function pressProps(reduced, disabled = false) {
    return reduced || disabled ? {} : { whileTap: { scale: 0.98 }, transition: { duration: duration.instant } };
}
