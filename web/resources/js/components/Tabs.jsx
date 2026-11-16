import * as RadixTabs from '@radix-ui/react-tabs';
import { useState } from 'react';

/**
 * Tabs built on Radix Tabs (arrow keys move between tabs). `children` is either one panel's
 * content for every tab, or a function of the active tab's value.
 *
 *   <Tabs tabs={[{ value: 'pending', label: 'Pending', count: 3 }, ...]}>{(active) => <List status={active} />}</Tabs>
 */
export default function Tabs({ tabs, defaultValue, onChange, children, 'aria-label': ariaLabel }) {
    const [active, setActive] = useState(defaultValue ?? tabs[0]?.value);

    function change(value) {
        setActive(value);
        onChange?.(value);
    }

    return (
        <RadixTabs.Root value={active} onValueChange={change}>
            <RadixTabs.List aria-label={ariaLabel} className="flex gap-1 overflow-x-auto border-b border-border">
                {tabs.map((tab) => (
                    <RadixTabs.Trigger
                        key={tab.value}
                        value={tab.value}
                        className="relative -mb-px flex h-(--control-md) items-center gap-2 border-b-2 border-transparent px-3 text-body font-medium whitespace-nowrap text-text-muted transition-colors duration-(--duration-fast) hover:text-text data-[state=active]:border-primary data-[state=active]:text-text pointer-coarse:min-h-(--touch-target)"
                    >
                        {tab.label}
                        {tab.count !== undefined && (
                            <span className="rounded-sm bg-surface-subtle px-1.5 font-mono text-code text-text-secondary">{tab.count}</span>
                        )}
                    </RadixTabs.Trigger>
                ))}
            </RadixTabs.List>
            {tabs.map((tab) => (
                <RadixTabs.Content key={tab.value} value={tab.value} className="pt-5">
                    {typeof children === 'function' ? children(tab.value) : children}
                </RadixTabs.Content>
            ))}
        </RadixTabs.Root>
    );
}
