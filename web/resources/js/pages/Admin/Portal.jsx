import BulletList from '../../components/BulletList';
import Button from '../../components/Button';
import Card from '../../components/Card';
import StatCard from '../../components/StatCard';

/**
 * Admin portal home. `readyForReview` is how many accounts are complete and waiting (PB-11).
 */
export default function AdminPortal({ readyForReview }) {
    return (
        <div className="grid gap-6 @bp-md/shell:grid-cols-12">
            <StatCard
                label="Ready for verification"
                value={readyForReview}
                icon="user-list"
                className="@bp-md/shell:col-span-5"
                action={
                    <Button href="/admin/verifications" iconRight="arrow-right">
                        Open verification queue
                    </Button>
                }
            >
                {readyForReview === 1 ? '1 account has' : `${readyForReview} accounts have`} a complete profile and documents.
            </StatCard>

            <Card title="Coming in later sprints" className="@bp-md/shell:col-span-7">
                <BulletList items={['Review and approve campaigns', 'Monitor the platform and audit log']} />
            </Card>
        </div>
    );
}

AdminPortal.layout = { title: 'Overview' };
