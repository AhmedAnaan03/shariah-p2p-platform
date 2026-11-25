import * as RadixTooltip from '@radix-ui/react-tooltip';
import { useState } from 'react';

/**
 * A short hint on hover or keyboard focus, built on Radix Tooltip (announced as the trigger's
 * description). For anything a touch user must be able to read, use Term or Popover: tooltips
 * don't open on tap.
 *
 *   <Tooltip content="Complete your profile first"><span tabIndex={0}>...</span></Tooltip>
 *
 * The child must be focusable. AppRoot provides the shared TooltipProvider.
 */
export default function Tooltip({ content, children, side = 'top', defaultOpen, disabled = false }) {
    const [open, setOpen] = useState(defaultOpen ?? false);
    // `disabled` keeps the same elements (so the trigger isn't re-created, which would restart its
    // transitions) but never opens: the expanded sidebar's items, whose label is already shown.
    return (
        <RadixTooltip.Root open={open && !disabled} onOpenChange={setOpen}>
            <RadixTooltip.Trigger asChild>{children}</RadixTooltip.Trigger>
            <RadixTooltip.Portal>
                <RadixTooltip.Content
                    side={side}
                    sideOffset={6}
                    collisionPadding={16}
                    className="z-(--z-tooltip) max-w-64 animate-pop rounded-xs bg-text px-2 py-1 text-body-sm text-surface shadow-md"
                >
                    {content}
                    <RadixTooltip.Arrow className="fill-text" />
                </RadixTooltip.Content>
            </RadixTooltip.Portal>
        </RadixTooltip.Root>
    );
}

export const TooltipProvider = RadixTooltip.Provider;
