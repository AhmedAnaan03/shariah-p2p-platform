import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import BulletList from '../../components/BulletList';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Checkbox from '../../components/Checkbox';
import PortalLayout from '../../components/PortalLayout';

/**
 * The capital-at-risk acknowledgement (PB-10). The text comes from the server
 * (App\Support\RiskAcknowledgement), so the audit log records the exact version shown.
 */
export default function Acknowledgement({ acknowledgement, hasProfile, acknowledgedAt }) {
    const form = useForm({ accepted: false });

    function submit(event) {
        event.preventDefault();
        form.post('/investor/acknowledgement');
    }

    return (
        <PortalLayout title="Risk acknowledgement">
            <Card title={acknowledgement.title} titleAs="h1" className="max-w-3xl">
                <div className="space-y-6">
                    <BulletList items={acknowledgement.points} />

                    {acknowledgedAt ? (
                        <Alert variant="success" title="You have accepted this acknowledgement">
                            Accepted on{' '}
                            {new Date(acknowledgedAt).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}.
                        </Alert>
                    ) : !hasProfile ? (
                        <Alert variant="info" role="note" title="Complete your profile first">
                            You can accept the acknowledgement once your profile is saved.
                            <div className="mt-3">
                                <Button href="/investor/profile" size="sm" variant="secondary">
                                    Complete profile
                                </Button>
                            </div>
                        </Alert>
                    ) : (
                        <form onSubmit={submit} noValidate className="space-y-5">
                            <Checkbox
                                id="accepted"
                                label={acknowledgement.confirmation}
                                checked={form.data.accepted}
                                onChange={(checked) => form.setData('accepted', checked)}
                                error={form.errors.accepted}
                            />
                            <Button type="submit" processing={form.processing}>
                                Accept and continue
                            </Button>
                        </form>
                    )}

                    <Alert variant="info" role="note">
                        Academic prototype: all money on this platform is simulated.
                    </Alert>
                </div>
            </Card>
        </PortalLayout>
    );
}
