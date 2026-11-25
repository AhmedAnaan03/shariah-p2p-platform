import { animate } from 'motion';
import { useEffect, useRef, useState } from 'react';
import { duration, ease, useReducedMotion } from './motion';

/**
 * A number that counts up from 0 on first view and tweens to each new value over
 * `duration-count` (README, "Motion": wallet balances and totals). With reduced motion it is
 * always the final value. Never use it for a number the user is typing.
 *
 *   const shown = useCountUp(12500);   // 0 → 12500 over 800 ms
 */
export function useCountUp(target) {
    const reduced = useReducedMotion();
    const value = Number(target) || 0;
    const [shown, setShown] = useState(reduced ? value : 0);
    const from = useRef(reduced ? value : 0);

    useEffect(() => {
        if (reduced) {
            from.current = value;
            setShown(value);
            return undefined;
        }
        const controls = animate(from.current, value, {
            duration: duration.count,
            ease: ease.standard,
            onUpdate: (latest) => {
                from.current = latest;
                setShown(latest);
            },
        });
        return () => controls.stop();
    }, [value, reduced]);

    return shown;
}
