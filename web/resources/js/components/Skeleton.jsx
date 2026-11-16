import { useEffect, useState } from 'react';
import cx from '../lib/cx';

const RADII = { xs: 'rounded-xs', sm: 'rounded-sm', md: 'rounded-md', lg: 'rounded-lg', xl: 'rounded-xl' };

/**
 * A placeholder block while content loads. It only appears after `delay` (300 ms), so a fast
 * load never flashes it, and shimmers every 1.4 s (no shimmer with reduced motion). Hidden
 * from screen readers: announce loading on the region instead (aria-busy).
 */
export default function Skeleton({ width = '100%', height = 16, radius = 'sm', circle = false, delay = 300 }) {
    const [visible, setVisible] = useState(delay === 0);

    useEffect(() => {
        if (delay === 0) return undefined;
        const timer = setTimeout(() => setVisible(true), delay);
        return () => clearTimeout(timer);
    }, [delay]);

    return (
        <span
            aria-hidden="true"
            className={cx('block bg-surface-subtle', circle ? 'rounded-full' : RADII[radius], visible ? 'animate-shimmer' : 'invisible')}
            style={{ width: circle ? height : width, height }}
        />
    );
}
