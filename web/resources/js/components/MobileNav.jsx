import * as RadixDialog from '@radix-ui/react-dialog';
import Button from './Button';
import Sidebar from './Sidebar';

/**
 * The sidebar as a drawer, for app shells narrower than 1024 px. Built on Radix Dialog, so
 * focus is trapped inside while it is open, Escape closes it, and focus returns to the menu
 * button. Slides in from the left over 250 ms (a short fade with reduced motion).
 *
 * `footer` holds the mode switch for investors, since the top bar is short on room here.
 */
export default function MobileNav({ open, onClose, portal, groups, footer }) {
    return (
        <RadixDialog.Root open={open} onOpenChange={(next) => !next && onClose()}>
            <RadixDialog.Portal>
                <RadixDialog.Overlay className="fixed inset-0 z-(--z-drawer) animate-overlay bg-overlay backdrop-blur-overlay" />
                <RadixDialog.Content
                    aria-describedby={undefined}
                    className="fixed inset-y-0 left-0 z-(--z-drawer) w-(--sidebar) max-w-[85vw] animate-drawer shadow-lg"
                >
                    <RadixDialog.Title className="sr-only">{portal} portal menu</RadixDialog.Title>
                    <Sidebar portal={portal} groups={groups} footer={footer} collapsible={false} onNavigate={onClose} />
                    <RadixDialog.Close asChild>
                        <Button icon="x" variant="ghost" aria-label="Close menu" className="absolute top-3 right-3" />
                    </RadixDialog.Close>
                </RadixDialog.Content>
            </RadixDialog.Portal>
        </RadixDialog.Root>
    );
}
