/**
 * The pages each role's sidebar links to. Every link stays inside that role's portal, so the
 * role gate on the routes and the links here always agree. Account links (change password,
 * sign out) are in the top bar's user menu. Profile, documents and (for investors) the risk
 * acknowledgement are steps of one "Account setup" wizard since PB-53.
 */
export const NAVIGATION = {
    borrower: {
        portal: 'Borrower',
        groups: [
            {
                items: [
                    { href: '/borrower', label: 'Overview', icon: 'house' },
                    { href: '/borrower/campaigns', label: 'Financing requests', icon: 'hand-coins' },
                    { href: '/borrower/wallet', label: 'Wallet', icon: 'wallet' },
                    { href: '/borrower/onboarding', label: 'Account setup', icon: 'user-check' },
                ],
            },
        ],
    },
    investor: {
        portal: 'Investor',
        groups: [
            {
                items: [
                    { href: '/investor', label: 'Overview', icon: 'house' },
                    { href: '/investor/campaigns', label: 'Marketplace', icon: 'storefront' },
                    { href: '/investor/wallet', label: 'Wallet', icon: 'wallet' },
                    { href: '/investor/onboarding', label: 'Account setup', icon: 'user-check' },
                ],
            },
        ],
    },
    admin: {
        portal: 'Admin',
        groups: [
            {
                items: [
                    { href: '/admin', label: 'Overview', icon: 'house' },
                    { href: '/admin/verifications', label: 'Verifications', icon: 'user-list' },
                    { href: '/admin/campaigns', label: 'Campaigns', icon: 'clipboard-text' },
                ],
            },
        ],
    },
};

/**
 * DEMO TOOL (PB-34): the admin's "Demo controls" link, added only when the server says the demo
 * tools exist (the `demoTools` prop, true on a developer's machine only).
 */
export const DEMO_CONTROLS = { href: '/admin/demo', label: 'Demo controls', icon: 'clock' };

/**
 * The navigation for a role with the current page marked `active`. The longest matching link
 * wins, so /borrower/onboarding/address highlights "Account setup", not "Overview".
 */
export function navigationFor(role, url, { demoTools = false } = {}) {
    const base = NAVIGATION[role] ?? { portal: '', groups: [] };
    const config = demoTools && role === 'admin' ? { ...base, groups: [...base.groups, { label: 'Demo', items: [DEMO_CONTROLS] }] } : base;
    const path = url.split('?')[0];
    const all = config.groups.flatMap((group) => group.items);
    const current = all
        .filter((item) => path === item.href || path.startsWith(`${item.href}/`))
        .sort((a, b) => b.href.length - a.href.length)[0];

    return {
        portal: config.portal,
        groups: config.groups.map((group) => ({
            ...group,
            items: group.items.map((item) => ({ ...item, active: item === current })),
        })),
    };
}
