import { usePoll } from '@inertiajs/react';
import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import EmptyState from '../../../components/EmptyState';
import RoleBadge from '../../../components/RoleBadge';
import Table from '../../../components/Table';
import { formatDate } from '../../../lib/format';

/**
 * The verification queue (PB-11): pending borrowers and investors whose profile and required
 * documents are complete, oldest first.
 *
 * The list refreshes itself every 30 s (a partial reload of just these props), so when another
 * administrator decides an account, its row leaves the queue with the 200 ms collapse (PB-51)
 * instead of staying until the page is reloaded.
 */
export default function VerificationsIndex({ applicants, incompleteCount }) {
    usePoll(30000, { only: ['applicants', 'incompleteCount'] });

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
                <Button href={row.url} size="sm" variant="secondary" iconRight="arrow-right">
                    Review<span className="sr-only"> {row.name}</span>
                </Button>
            ),
        },
    ];

    return (
        <>
            <Card title="Waiting for verification">
                {applicants.length === 0 ? (
                    <EmptyState kind="queue" title="No accounts are waiting for verification">
                        Accounts appear here once their profile and documents are complete.
                    </EmptyState>
                ) : (
                    <Table columns={columns} rows={applicants} caption="Accounts waiting for verification" stack="wide" />
                )}
            </Card>

            {incompleteCount > 0 && (
                <Alert tone="info" role="note">
                    {incompleteCount === 1 ? '1 more account is' : `${incompleteCount} more accounts are`} still completing their profile
                    or documents. They will appear here once they are complete.
                </Alert>
            )}
        </>
    );
}

VerificationsIndex.layout = {
    title: 'Verification queue',
    description: "Check each account's profile against its documents, then approve or reject it.",
};
