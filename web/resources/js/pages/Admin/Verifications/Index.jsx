import Alert from '../../../components/Alert';
import Button from '../../../components/Button';
import Card from '../../../components/Card';
import PortalLayout from '../../../components/PortalLayout';
import Table from '../../../components/Table';

const formatDate = (iso) => new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });

/**
 * The verification queue (PB-11): pending borrowers and investors whose profile and required
 * documents are complete, oldest first.
 */
export default function VerificationsIndex({ applicants, incompleteCount }) {
    const columns = [
        { key: 'name', label: 'Name' },
        { key: 'email', label: 'Email' },
        { key: 'role_label', label: 'Role' },
        { key: 'registered_at', label: 'Registered', render: (row) => formatDate(row.registered_at) },
        { key: 'documents_count', label: 'Documents to review' },
        {
            key: 'review',
            label: <span className="sr-only">Actions</span>,
            render: (row) => (
                <Button href={row.url} size="sm" variant="secondary">
                    Review
                </Button>
            ),
        },
    ];

    return (
        <PortalLayout title="Verification queue">
            <Card
                title="Verification queue"
                titleAs="h1"
                description="Check each account's profile against its documents, then approve or reject it."
            >
                <div className="space-y-4">
                    {applicants.length === 0 ? (
                        <Alert variant="success">No accounts are waiting for verification.</Alert>
                    ) : (
                        <Table columns={columns} rows={applicants} caption="Accounts waiting for verification" />
                    )}

                    {incompleteCount > 0 && (
                        <Alert variant="info" role="note">
                            {incompleteCount === 1 ? '1 more account is' : `${incompleteCount} more accounts are`} still completing
                            their profile or documents. They will appear here once they are complete.
                        </Alert>
                    )}
                </div>
            </Card>
        </PortalLayout>
    );
}
