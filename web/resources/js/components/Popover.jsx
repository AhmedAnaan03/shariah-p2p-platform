import * as RadixPopover from '@radix-ui/react-popover';

/**
 * A panel that opens from a trigger on click, tap or Enter, built on Radix Popover (focus moves
 * in, Escape closes, focus returns). Used for the notifications bell and the Term definitions.
 *
 *   <Popover trigger={<Button icon="bell" aria-label="Notifications" variant="ghost" />}>...</Popover>
 */
export default function Popover({ trigger, children, defaultOpen, align = 'end', width = 320, label }) {
    return (
        <RadixPopover.Root defaultOpen={defaultOpen}>
            <RadixPopover.Trigger asChild>{trigger}</RadixPopover.Trigger>
            <RadixPopover.Portal>
                <RadixPopover.Content
                    align={align}
                    sideOffset={8}
                    collisionPadding={16}
                    aria-label={label}
                    className="z-(--z-dropdown) max-w-(--radix-popover-content-available-width) animate-pop rounded-lg border border-border bg-surface-raised shadow-md"
                    style={{ width }}
                >
                    {children}
                </RadixPopover.Content>
            </RadixPopover.Portal>
        </RadixPopover.Root>
    );
}
