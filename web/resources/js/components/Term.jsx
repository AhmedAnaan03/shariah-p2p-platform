import * as RadixPopover from '@radix-ui/react-popover';

/**
 * Explains an Islamic finance term on first use (README, "Voice and content"): the word gets a
 * dotted underline, and tapping, clicking or pressing Enter on it shows the definition.
 *
 *   <Term definition="A sale at cost plus an agreed, fixed markup.">Murabahah</Term>
 *
 * A popover rather than a hover tooltip, so it also works on phones.
 */
export default function Term({ definition, children, defaultOpen }) {
    return (
        <RadixPopover.Root defaultOpen={defaultOpen}>
            <RadixPopover.Trigger className="cursor-help font-medium text-text underline decoration-border-control decoration-dotted decoration-2 underline-offset-4 hover:decoration-primary">
                {children}
            </RadixPopover.Trigger>
            <RadixPopover.Portal>
                <RadixPopover.Content
                    side="top"
                    sideOffset={6}
                    collisionPadding={16}
                    className="z-(--z-tooltip) max-w-72 animate-pop rounded-md border border-border bg-surface-raised p-3 text-body-sm text-text-secondary shadow-md"
                >
                    <p className="text-overline text-text-muted">{children}</p>
                    <p className="mt-1">{definition}</p>
                    <RadixPopover.Arrow className="fill-surface-raised" />
                </RadixPopover.Content>
            </RadixPopover.Portal>
        </RadixPopover.Root>
    );
}
