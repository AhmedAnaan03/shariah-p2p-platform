import { CONTRACTS } from '../lib/contracts';
import RadioCards from './RadioCards';
import ShariahBadge from './ShariahBadge';

/**
 * The borrower's choice of contract (component-api.d.ts, ContractTypePicker): conventional,
 * Murabahah or Mudarabah, as three RadioCards. The two Shariah contracts carry the
 * ShariahBadge (khatam and the word), so the choice never relies on colour.
 *
 *   <ContractTypePicker value={form.data.contract_type}
 *       onChange={(contract) => form.setData('contract_type', contract)} error={form.errors.contract_type} />
 *
 * The card text comes from lib/contracts.js, where a test checks it never says "interest" or
 * "guaranteed": the cards stay on screen when the page switches to Shariah mode.
 */
export default function ContractTypePicker({ name = 'contract_type', legend = 'Contract type', value, defaultValue, onChange, error, disabled }) {
    return (
        <RadioCards
            name={name}
            legend={legend}
            columns={3}
            value={value}
            defaultValue={defaultValue}
            onChange={onChange}
            error={error}
            disabled={disabled}
            options={CONTRACTS.map((contract) => ({
                value: contract.value,
                label: contract.label,
                icon: contract.icon,
                description: contract.description,
                badge: contract.shariah ? <ShariahBadge size="sm" /> : null,
            }))}
        />
    );
}
