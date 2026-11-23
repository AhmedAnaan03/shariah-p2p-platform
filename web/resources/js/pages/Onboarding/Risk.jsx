import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import BulletList from '../../components/BulletList';
import Card from '../../components/Card';
import Checkbox from '../../components/Checkbox';
import OnboardingFrame from '../../components/OnboardingFrame';
import { formatDate } from '../../lib/format';

/**
 * Onboarding step 4 for investors, the capital-at-risk acknowledgement (PB-10, moved into the
 * wizard as its last step before review by PB-53). The text comes from the server
 * (App\Support\RiskAcknowledgement), so the audit log records the exact version shown.
 *
 * This step is never locked: pledging needs the acknowledgement, so an investor who was
 * verified before accepting it must still be able to. Hence `locked: false` for the frame.
 */
export default function OnboardingRisk({ wizard, acknowledgement, acknowledgedAt, acceptUrl }) {
    const form = useForm({ accepted: acknowledgedAt !== null });

    function submit(event) {
        event.preventDefault();
        form.post(acceptUrl, { preserveScroll: true });
    }

    return (
        <OnboardingFrame wizard={{ ...wizard, locked: false }} onSubmit={submit} processing={form.processing} continueLabel={acknowledgedAt ? 'Continue' : 'Accept and continue'}>
            <Card title={acknowledgement.title} className="max-w-3xl">
                <div className="space-y-6">
                    <BulletList items={acknowledgement.points} />

                    {acknowledgedAt ? (
                        <Alert tone="success" title="You have accepted this acknowledgement">
                            Accepted on {formatDate(acknowledgedAt)}.
                        </Alert>
                    ) : (
                        <Checkbox
                            id="accepted"
                            label={acknowledgement.confirmation}
                            checked={form.data.accepted}
                            onChange={(checked) => form.setData('accepted', checked)}
                            error={form.errors.accepted}
                        />
                    )}

                    <Alert tone="info" role="note">
                        Academic prototype: all money on this platform is simulated.
                    </Alert>
                </div>
            </Card>
        </OnboardingFrame>
    );
}

OnboardingRisk.layout = {
    title: 'Set up your account',
    description: 'A few steps, saved as you go. You can come back and finish later.',
};
