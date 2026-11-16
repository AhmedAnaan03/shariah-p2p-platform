import * as RadixDialog from '@radix-ui/react-dialog';
import cx from '../lib/cx';
import Button from './Button';

const SIZES = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-3xl' };

/**
 * A modal dialog built on Radix Dialog: focus is trapped inside, Escape and the close button
 * close it, and focus returns to what opened it. The overlay is the mode's text colour at 40%
 * with a 2 px blur; the panel scales in from 0.96.
 *
 *   <Dialog open={open} onClose={() => setOpen(false)} title="..." footer={<Button ...>}>...</Dialog>
 */
export default function Dialog({ open, onClose, title, description, children, footer, size = 'md' }) {
    return (
        <RadixDialog.Root open={open} onOpenChange={(next) => !next && onClose?.()}>
            <RadixDialog.Portal>
                <RadixDialog.Overlay className="fixed inset-0 z-(--z-modal) animate-overlay bg-overlay backdrop-blur-overlay" />
                <RadixDialog.Content
                    className={cx(
                        'fixed top-1/2 left-1/2 z-(--z-modal) flex max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] -translate-1/2 animate-pop flex-col overflow-hidden rounded-xl border border-border bg-surface-raised shadow-lg [--pop-from:0.96]',
                        SIZES[size],
                    )}
                    {...(description ? {} : { 'aria-describedby': undefined })}
                >
                    <div className="flex items-start justify-between gap-4 px-6 pt-6">
                        <div>
                            <RadixDialog.Title className="text-h4 text-text">{title}</RadixDialog.Title>
                            {description && <RadixDialog.Description className="mt-1 text-body text-text-muted">{description}</RadixDialog.Description>}
                        </div>
                        <RadixDialog.Close asChild>
                            <Button icon="x" variant="ghost" size="sm" aria-label="Close" className="-mt-1 -mr-2" />
                        </RadixDialog.Close>
                    </div>
                    {children && <div className="overflow-y-auto px-6 pt-4 pb-6">{children}</div>}
                    {footer && <div className="flex flex-wrap justify-end gap-3 border-t border-border px-6 py-4">{footer}</div>}
                </RadixDialog.Content>
            </RadixDialog.Portal>
        </RadixDialog.Root>
    );
}
