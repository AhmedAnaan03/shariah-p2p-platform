import { renderToStaticMarkup } from 'react-dom/server';
import { describe, expect, it } from 'vitest';
import WalletCard from './WalletCard';

/** PB-18: the wallet card always says the money is simulated (README, "Voice and content"). */
describe('WalletCard', () => {
    it('shows the balance, the held amount and "Simulated wallet, no real money."', () => {
        const html = renderToStaticMarkup(<WalletCard available="2500.00" held="500.00" onTopUp={() => {}} onWithdraw={() => {}} />);
        expect(html).toContain('Simulated wallet, no real money.');
        expect(html).toContain('MVR 2500.00'); // read out exactly, before the count-up starts
        expect(html).toContain('Held in pledges');
        expect(html).toContain('Top up');
        expect(html).toContain('Withdraw');
    });

    it('hides the buttons for an account that cannot move money yet', () => {
        const html = renderToStaticMarkup(<WalletCard available="0.00" held="0.00" />);
        expect(html).not.toContain('Top up');
        expect(html).toContain('Simulated wallet, no real money.');
    });
});
