/**
 * The pages each role's sidebar links to. Every link stays inside that role's portal, so the
 * role gate on the routes and the links here always agree. Account links (change password,
 * sign out) are in the top bar's user menu.
 */
export const NAVIGATION = {
    borrower: {
        portal: 'Borrower',
        groups: [
            {
                items: [
                    { href: '/borrower', label: 'Overview', icon: 'house' },
                    { href: '/borrower/campaigns', label: 'Financing requests', icon: 'hand-coins' },
                    { href: '/borrower/profile', label: 'Profile', icon: 'user' },
                    { href: '/borrower/documents', label: 'Documents', icon: 'file-text' },
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
                    { href: '/investor/profile', label: 'Profile', icon: 'user' },
                    { href: '/investor/documents', label: 'Documents', icon: 'file-text' },
                    { href: '/investor/acknowledgement', label: 'Risk acknowledgement', icon: 'scales' },
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
 * The navigation for a role with the current page marked `active`. The longest matching link
 * wins, so /borrower/profile highlights "Profile", not "Overview".
 */
export function navigationFor(role, url) {
    const config = NAVIGATION[role] ?? { portal: '', groups: [] };
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
