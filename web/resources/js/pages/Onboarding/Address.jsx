import { useForm } from '@inertiajs/react';
import Alert from '../../components/Alert';
import Card from '../../components/Card';
import Input from '../../components/Input';
import OnboardingFrame from '../../components/OnboardingFrame';
import Select from '../../components/Select';

/**
 * Onboarding step 2, the address (PB-53): house or building name, road (magu), atoll, island
 * and postcode. The island list follows the chosen atoll; `atolls` carries every atoll with its
 * inhabited islands, so filtering needs no request. Choosing another atoll clears the island,
 * since the old one isn't in the new list. The server checks the island belongs to the atoll.
 *
 * `legacyAddress` is the single-line address an older profile had, shown while the user checks
 * the fields that were filled in from it.
 */
export default function OnboardingAddress({ wizard, address, legacyAddress, atolls }) {
    const form = useForm(address);
    const islands = atolls.find((atoll) => atoll.value === form.data.atoll_id)?.islands ?? [];

    function submit(event) {
        event.preventDefault();
        form.put(`/${wizard.role}/onboarding/address`, { preserveScroll: true });
    }

    const field = (name) => ({
        id: name,
        value: form.data[name],
        onChange: (e) => form.setData(name, e.target.value),
        error: form.errors[name],
        disabled: wizard.locked,
    });

    return (
        <OnboardingFrame wizard={wizard} onSubmit={submit} processing={form.processing}>
            {legacyAddress && (
                <Alert tone="info" role="note" title="Check your address">
                    <p>Addresses are now split into separate parts. We filled in what we could from the address you gave before:</p>
                    <p className="mt-2 font-medium">{legacyAddress}</p>
                    <p className="mt-2">Check each part, fill in anything missing, then continue.</p>
                </Alert>
            )}

            <Card title="Your address" description="Where you live. For a business, where it operates.">
                <fieldset className="grid gap-5 @bp-sm/shell:grid-cols-2">
                    <legend className="sr-only">Address</legend>
                    <Input {...field('address_house')} label="House or building name" autoComplete="address-line1" hint="Like H. Sea Breeze or Fehivina." />
                    <Input {...field('address_road')} label="Road (magu)" autoComplete="address-line2" hint="Like Majeedhee Magu." />
                    <Select
                        {...field('atoll_id')}
                        onChange={(value) => form.setData((data) => ({ ...data, atoll_id: value, island_id: value === data.atoll_id ? data.island_id : '' }))}
                        label="Atoll"
                        placeholder="Choose your atoll"
                        options={atolls}
                    />
                    <Select
                        {...field('island_id')}
                        onChange={(value) => form.setData('island_id', value)}
                        label="Island"
                        placeholder={form.data.atoll_id ? 'Choose your island' : 'Choose an atoll first'}
                        options={islands}
                        disabled={wizard.locked || !form.data.atoll_id}
                    />
                    <Input
                        {...field('postcode')}
                        label="Postcode"
                        inputMode="numeric"
                        autoComplete="postal-code"
                        maxLength={6}
                        hint="5 digits, like 20026."
                        controlClassName="max-w-40"
                    />
                </fieldset>
            </Card>
        </OnboardingFrame>
    );
}

OnboardingAddress.layout = {
    title: 'Set up your account',
    description: 'A few steps, saved as you go. You can come back and finish later.',
};
