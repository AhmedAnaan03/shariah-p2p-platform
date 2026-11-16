import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import BulletList from '../../components/BulletList';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Checkbox from '../../components/Checkbox';
import { formatDate } from '../../lib/format';

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
        <Card title={acknowledgement.title} className="max-w-3xl">
            <div className="space-y-6">
                <BulletList items={acknowledgement.points} />

                {acknowledgedAt ? (
                    <Alert tone="success" title="You have accepted this acknowledgement">
                        Accepted on {formatDate(acknowledgedAt)}.
                    </Alert>
                ) : !hasProfile ? (
                    <Alert
                        tone="info"
                        role="note"
                        title="Complete your profile first"
                        action={
                            <Button href="/investor/profile" size="sm" variant="secondary">
                                Complete profile
                            </Button>
                        }
                    >
                        You can accept the acknowledgement once your profile is saved.
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
                        <Button type="submit" loading={form.processing}>
                            Accept and continue
                        </Button>
                    </form>
                )}

                <Alert tone="info" role="note">
                    Academic prototype: all money on this platform is simulated.
                </Alert>
            </div>
        </Card>
    );
}

Acknowledgement.layout = { title: 'Risk acknowledgement' };
