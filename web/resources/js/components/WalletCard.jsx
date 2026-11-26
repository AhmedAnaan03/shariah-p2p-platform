import { useCountUp } from '../lib/useCountUp';
import { SIMULATED } from '../lib/wallet';
import { formatDate } from '../lib/format';
import Button from './Button';
import Icon from './Icon';
import Money from './Money';

/**
 * The simulated wallet (component-api.d.ts, WalletCard; PB-18): the available balance, the money
 * held in pledges, Top up and Withdraw, and the latest movements. The balance counts up on first
 * view and tweens when it changes (instant with reduced motion). It always says the money is
 * simulated (README, "Voice and content").
 *
 *   <WalletCard available={12500} held={5000} transactions={[{ title: 'Top-up', date, amount: 10000 }]}
 *       onTopUp={...} onWithdraw={...} />
 *
 * `amount` in transactions is positive into the wallet and negative out of it. Leave out
 * onTopUp / onWithdraw to hide the buttons (an account that can't move money yet).
 */
export default function WalletCard({ available, held, transactions = [], onTopUp, onWithdraw }) {
    const shown = useCountUp(available);

    return (
        <section aria-labelledby="wallet-title" className="min-w-0 rounded-lg border border-border bg-surface p-5 shadow-xs @bp-sm/shell:p-6">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0">
                    <h2 id="wallet-title" className="text-overline text-text-muted">
                        Available balance
                    </h2>
                    <p className="mt-2" aria-live="polite">
                        <span className="sr-only">MVR {Number(available).toFixed(2)}</span>
                        <span aria-hidden="true">
                            {/* On a narrow card a long balance puts "MVR" above the figure instead of overflowing. */}
                            <Money value={shown} size="figure-xl" className="flex-wrap" />
                        </span>
                    </p>
                    <p className="mt-2 text-body-sm text-text-secondary">
                        Held in pledges: <Money value={held} />
                    </p>
                </div>
                {(onTopUp || onWithdraw) && (
                    <div className="flex flex-wrap gap-2">
                        {onTopUp && (
                            <Button iconLeft="plus" onClick={onTopUp}>
                                Top up
                            </Button>
                        )}
                        {onWithdraw && (
                            <Button variant="secondary" iconLeft="minus" onClick={onWithdraw}>
                                Withdraw
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <p className="mt-4 flex items-center gap-2 rounded-md bg-surface-subtle px-3 py-2 text-body-sm text-text-secondary">
                <Icon name="info" size={16} className="text-icon" />
                {SIMULATED}
            </p>

            {transactions.length > 0 && (
                <ul className="mt-5 divide-y divide-border border-t border-border">
                    {transactions.map((entry, index) => (
                        <li key={index} className="flex items-center justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <p className="truncate text-body text-text">{entry.title}</p>
                                <p className="text-body-sm text-text-muted">{formatDate(entry.date)}</p>
                            </div>
                            <Money value={entry.amount} sign className={Number(entry.amount) > 0 ? 'text-primary-ink' : 'text-text'} />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
