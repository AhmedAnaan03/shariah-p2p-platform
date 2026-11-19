import { usePage, usePoll } from '@inertiajs/react';
import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import EmptyState from '../../../components/EmptyState';
import ListFilters from '../../../components/ListFilters';
import RoleBadge from '../../../components/RoleBadge';
import Table from '../../../components/Table';
import { formatDate } from '../../../lib/format';
import { withReturn } from '../../../lib/returnUrl';

/**
 * The verification queue (PB-11): pending borrowers and investors who have finished and
 * submitted account setup (PB-53), oldest first, with the shared search-and-filter bar (PB-54).
 *
 * The list refreshes itself every 30 s (a partial reload of just these props), so when another
 * administrator decides an account, its row leaves the queue with the 200 ms collapse (PB-51)
 * instead of staying until the page is reloaded.
 */
export default function VerificationsIndex({ applicants, incompleteCount, filters, filterOptions }) {
    // The poll reloads the current URL, so it keeps the filters in it.
    usePoll(30000, { only: ['applicants', 'incompleteCount'] });
    const { url } = usePage();
    const filtered = Object.keys(filters).length > 0;

    const columns = [
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'Email' },
        { key: 'role', label: 'Role', render: (row) => <RoleBadge role={row.role} size="sm" /> },
        { key: 'registered_at', label: 'Registered', mono: true, render: (row) => formatDate(row.registered_at) },
        { key: 'documents_count', label: 'Documents to review', align: 'right', mono: true },
        {
            key: 'review',
            label: <span className="sr-only">Actions</span>,
            render: (row) => (
                <Button href={withReturn(row.url, url)} size="sm" variant="secondary" iconRight="arrow-right">
                    Review<span className="sr-only"> {row.name}</span>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Card title="Waiting for verification">
                <div className="mb-5">
                    <ListFilters
                        filters={filters}
                        only={['applicants', 'incompleteCount']}
                        searchLabel="Search by name or email"
                        searchPlaceholder="Aishath or aishath@example.com"
                        fields={[
                            { name: 'role', label: 'Role', allLabel: 'All roles', options: filterOptions.role },
                            { name: 'type', label: 'Borrower type', allLabel: 'All borrower types', options: filterOptions.type },
                        ]}
                        resultCount={applicants.length}
                        resultNoun={['account', 'accounts']}
                    />
                </div>
                {applicants.length === 0 && filtered ? (
                    <EmptyState kind="search" title="No waiting accounts match these filters">
                        Try other words, or clear the filters to see the whole queue.
                    </EmptyState>
                ) : applicants.length === 0 ? (
                    <EmptyState kind="queue" title="No accounts are waiting for verification">
                        Accounts appear here once they have finished account setup and submitted it.
                    </EmptyState>
                ) : (
                    <Table columns={columns} rows={applicants} caption="Accounts waiting for verification" stack="wide" />
                )}
            </Card>

            {incompleteCount > 0 && (
                <Alert tone="info" role="note">
                    {incompleteCount === 1 ? '1 more account is' : `${incompleteCount} more accounts are`} still completing account setup.
                    They will appear here once they have finished and submitted it.
                </Alert>
            )}
        </>
    );
}

VerificationsIndex.layout = {
    title: 'Verification queue',
    description: "Check each account's profile against its documents, then approve or reject it.",
};
