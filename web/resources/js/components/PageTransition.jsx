import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'motion/react';
import { pageTransition, useReducedMotion } from '../lib/motion';

/**
 * The page change (README, "Motion"): the old page fades out over 150 ms, then the new one fades
 * in and rises 8 px over 250 ms. Only the content inside the layout animates; the layout (and
 * so the sidebar and top bar) stays mounted and still.
 *
 * Keyed by the URL path, so a form that redirects back to the same page, or a background
 * reload, doesn't replay it. Not on the first load.
 */
export default function PageTransition({ children }) {
    const { url } = usePage();
    const reduced = useReducedMotion();
    const path = url.split('?')[0];

    return (
        <AnimatePresence mode="wait" initial={false}>
            <motion.div key={path} {...pageTransition(reduced)}>
                {children}
            </motion.div>
        </AnimatePresence>
    );
}
