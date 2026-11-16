import * as RadixMenu from '@radix-ui/react-dropdown-menu';
import cx from '../lib/cx';
import Icon from './Icon';

/**
 * A menu of actions from a trigger, built on Radix DropdownMenu (arrow keys, type-ahead,
 * Escape). `items` are:
 *   '-'                                   a separator
 *   { heading: 'Signed in as Aishath' }   a small heading
 *   { icon, label, onSelect, shortcut, danger, disabled }
 *
 *   <DropdownMenu trigger={<button ...>} items={[{ icon: 'key', label: 'Change password', onSelect: ... }]} />
 */
export default function DropdownMenu({ trigger, items, defaultOpen, align = 'end' }) {
    return (
        <RadixMenu.Root defaultOpen={defaultOpen} modal={false}>
            <RadixMenu.Trigger asChild>{trigger}</RadixMenu.Trigger>
            <RadixMenu.Portal>
                <RadixMenu.Content
                    align={align}
                    sideOffset={6}
                    collisionPadding={16}
                    className="z-(--z-dropdown) min-w-56 animate-pop rounded-lg border border-border bg-surface-raised p-1 shadow-md"
                >
                    {items.map((item, index) => {
                        if (item === '-') {
                            return <RadixMenu.Separator key={index} className="my-1 h-px bg-border" />;
                        }
                        if (item.heading) {
                            return (
                                <RadixMenu.Label key={index} className="px-3 py-2 text-body-sm text-text-muted">
                                    {item.heading}
                                </RadixMenu.Label>
                            );
                        }
                        return (
                            <RadixMenu.Item
                                key={index}
                                disabled={item.disabled}
                                onSelect={item.onSelect}
                                className={cx(
                                    'flex h-(--control-md) cursor-pointer items-center gap-3 rounded-md px-3 text-body outline-none select-none pointer-coarse:min-h-(--touch-target)',
                                    'data-disabled:cursor-not-allowed data-disabled:opacity-(--opacity-disabled) data-highlighted:bg-surface-subtle',
                                    item.danger ? 'text-danger' : 'text-text',
                                )}
                            >
                                {item.icon && <Icon name={item.icon} className={item.danger ? 'text-danger' : 'text-icon'} />}
                                <span className="flex-1">{item.label}</span>
                                {item.shortcut && <span className="font-mono text-code text-text-muted">{item.shortcut}</span>}
                            </RadixMenu.Item>
                        );
                    })}
                </RadixMenu.Content>
            </RadixMenu.Portal>
        </RadixMenu.Root>
    );
}
