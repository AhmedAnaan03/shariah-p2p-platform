import * as RadixSlider from '@radix-ui/react-slider';
import { AnimatePresence, motion } from 'motion/react';
import { useEffect, useId, useRef, useState } from 'react';
import cx from '../lib/cx';
import { duration, ease, useReducedMotion } from '../lib/motion';
import { amountText, rangeText } from '../lib/rangeSlider';
import Button from './Button';

const THUMB_LABELS = ['Minimum amount', 'Maximum amount'];

/**
 * A two-handle range slider on Radix Slider (PB-16): the marketplace's amount filter.
 *
 *   <RangeSlider label="Amount" steps={[10000, 15000, ...]} value={[2, 14]} onCommit={(positions, how) => ...} />
 *
 * - It works in positions along `steps` (a list of amounts), so the scale can be logarithmic.
 * - While a handle is dragged or moved with the keyboard, a small tooltip above that handle
 *   shows the exact amount ("MVR 250,000"), fading and rising 4 px over duration-base (a fade
 *   only with reduced motion). The current range is written next to the label.
 * - `onCommit` runs when a handle is released, not on every movement, with "pointer" or
 *   "keyboard" so the caller can wait for a pause in key presses.
 * - Keyboard: arrows move one step, Page Up / Page Down ten, Home / End to the ends. Each handle
 *   is named ("Minimum amount") and reads its amount in MVR (aria-valuetext).
 * - "Reset" appears once a range is set and puts both handles back at the ends.
 */
export default function RangeSlider({ id, label, steps, value, onCommit, className = '' }) {
    const reduced = useReducedMotion();
    const labelId = useId();
    const last = steps.length - 1;
    const [positions, setPositions] = useState(value);
    const [dragging, setDragging] = useState(false);
    const [focused, setFocused] = useState(null);
    const [keyboard, setKeyboard] = useState(false);
    const input = useRef('pointer');

    // Follow the server's value (Back, Clear filters), unless a handle is being dragged.
    const serverKey = value.join(',');
    useEffect(() => {
        if (!dragging) {
            setPositions(value);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverKey]);

    // A drag can end outside the slider, so listen on the window for the release.
    useEffect(() => {
        if (!dragging) return undefined;
        const end = () => setDragging(false);
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
        return () => {
            window.removeEventListener('pointerup', end);
            window.removeEventListener('pointercancel', end);
        };
    }, [dragging]);

    const isSet = positions[0] > 0 || positions[1] < last;
    const tip = reduced
        ? { initial: { opacity: 0 }, animate: { opacity: 1 }, exit: { opacity: 0 }, transition: { duration: duration.instant } }
        : { initial: { opacity: 0, y: 4, scale: 0.97 }, animate: { opacity: 1, y: 0, scale: 1 }, exit: { opacity: 0, y: 4 }, transition: { duration: duration.base, ease: ease.enter } };

    return (
        <div className={cx('min-w-0', className)}>
            {/* One label line, exactly like a dropdown's label: the summary uses the label size and
                Reset is laid over the right end, so neither makes the row taller. */}
            <div className={cx('relative flex items-baseline gap-x-3', isSet && 'pr-14')}>
                <span id={labelId} className="text-label text-text">
                    {label}
                </span>
                <span className="min-w-0 truncate font-mono text-label text-text-secondary" aria-hidden="true">
                    {rangeText(positions, steps)}
                </span>
                {isSet && (
                    <Button
                        variant="link"
                        className="absolute top-1/2 right-0 -translate-y-1/2"
                        onClick={() => {
                            setPositions([0, last]);
                            onCommit([0, last], 'pointer');
                        }}
                    >
                        Reset
                    </Button>
                )}
            </div>
            <RadixSlider.Root
                id={id}
                aria-labelledby={labelId}
                min={0}
                max={last}
                step={1}
                minStepsBetweenThumbs={0}
                value={positions}
                onValueChange={setPositions}
                onValueCommit={(next) => onCommit(next, input.current)}
                onPointerDown={() => {
                    input.current = 'pointer';
                    setKeyboard(false);
                    setDragging(true);
                }}
                onKeyDown={() => {
                    input.current = 'keyboard';
                    setKeyboard(true);
                }}
                // Label to control 8 px, like every field, so the slider lines up with the dropdowns
                // beside it; the tooltip may cover the label briefly while a handle moves.
                className="relative mt-2 flex h-(--control-md) w-full touch-none items-center select-none"
            >
                <RadixSlider.Track className="relative h-1.5 grow overflow-hidden rounded-full bg-surface-subtle ring-1 ring-border ring-inset">
                    <RadixSlider.Range className="absolute h-full rounded-full bg-primary" />
                </RadixSlider.Track>
                {positions.map((position, index) => (
                    <RadixSlider.Thumb
                        key={index}
                        aria-label={THUMB_LABELS[index]}
                        aria-valuetext={amountText(steps[position])}
                        onFocus={() => setFocused(index)}
                        onBlur={() => setFocused(null)}
                        className="relative block size-5 rounded-full border-2 border-primary bg-surface shadow-xs transition-[box-shadow] duration-(--duration-fast) ease-standard hover:shadow-sm pointer-coarse:size-7"
                    >
                        <AnimatePresence>
                            {/* Only the handle in use: the one being dragged (Radix focuses it) or moved with keys. */}
                            {focused === index && (dragging || keyboard) && (
                                <motion.span
                                    {...tip}
                                    aria-hidden="true"
                                    className="pointer-events-none absolute bottom-full left-1/2 z-(--z-tooltip) mb-2 -translate-x-1/2 rounded-xs bg-text px-2 py-1 font-mono text-code whitespace-nowrap text-surface shadow-md"
                                >
                                    {amountText(steps[position])}
                                </motion.span>
                            )}
                        </AnimatePresence>
                    </RadixSlider.Thumb>
                ))}
            </RadixSlider.Root>
        </div>
    );
}
