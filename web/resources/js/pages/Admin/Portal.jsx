import BulletList from '../../components/BulletList';
import Button from '../../components/Button';
import Card from '../../components/Card';
import PortalLayout from '../../components/PortalLayout';

/**
 * Admin portal home. `readyForReview` is how many accounts are complete and waiting (PB-11).
 */
export default function AdminPortal({ readyForReview }) {
    return (
        <PortalLayout title="Admin portal">
            <Card
                title="Administrator portal"
                titleAs="h1"
                description={
                    readyForReview === 1
                        ? '1 account is ready for verification.'
                        : `${readyForReview} accounts are ready for verification.`
                }
                actions={<Button href="/admin/verifications">Open verification queue</Button>}
            />

            <Card title="Coming in later sprints">
                <BulletList items={['Review and approve campaigns', 'Monitor the platform and audit log']} />
            </Card>
        </PortalLayout>
    );
}
