import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import Alert from '../../components/Alert';
import Badge from '../../components/Badge';
import Button from '../../components/Button';
import Card from '../../components/Card';
import Dialog from '../../components/Dialog';
import EmptyState from '../../components/EmptyState';
import ListFilters from '../../components/ListFilters';
import Money from '../../components/Money';
import MoneyInput from '../../components/MoneyInput';
import Pagination from '../../components/Pagination';
import Table from '../../components/Table';
import WalletCard from '../../components/WalletCard';
import { formatDate, formatMoney, formatTime } from '../../lib/format';
import { SIMULATED } from '../../lib/wallet';

const ACTIONS = {
    topUp: { title: 'Top up your wallet', button: 'Top up', verb: 'Add', icon: 'plus' },
    withdraw: { title: 'Withdraw from your wallet', button: 'Withdraw', verb: 'Withdraw', icon: 'minus' },
};

/**
 * The simulated wallet (PB-18), for investors and borrowers: the balance and money held in
 * pledges (WalletCard), simulated top-ups and withdrawals, and the full history with the shared
 * search-and-filter bar. Every movement is a pair of ledger entries written by the server.
 */
export default function WalletShow({ available, held, transactions, hasAny, filters, filterOptions, canMoveMoney, blockedReason, limits, urls }) {
    const [action, setAction] = useState(null);
    const only = ['transactions', 'available', 'held'];

    return (
        <>
            {!canMoveMoney && (
                <Alert tone="warning" role="note" title="Your wallet is locked">
                    {blockedReason}
                </Alert>
            )}

            <WalletCard
                available={available}
                held={held}
                transactions={transactions.data.slice(0, 3).map((entry) => ({ title: entry.description ?? entry.type_label, date: entry.created_at, amount: entry.amount }))}
                onTopUp={canMoveMoney ? () => setAction('topUp') : undefined}
                onWithdraw={canMoveMoney ? () => setAction('withdraw') : undefined}
            />

            <Card title="Transactions" description="Every movement in and out of your wallet, newest first.">
                {hasAny && (
                    <div className="mb-5">
                        <ListFilters
                            filters={filters}
                            only={only}
                            searchLabel="Search transactions"
                            searchPlaceholder="Campaign name"
                            fields={[{ name: 'type', label: 'Type', allLabel: 'All types', options: filterOptions.type }]}
                            resultCount={transactions.total}
                            resultNoun={['transaction', 'transactions']}
                        />
                    </div>
                )}
                {!hasAny ? (
                    <EmptyState kind="documents" title="No transactions yet">
                        {canMoveMoney ? 'Top up your wallet to get started. It is simulated: no real money moves.' : 'Once your account is verified you can top up your wallet.'}
                    </EmptyState>
                ) : transactions.data.length === 0 ? (
                    <EmptyState kind="search" title="No transactions match these filters">
                        Try other words, or clear the filters to see every transaction.
                    </EmptyState>
                ) : (
                    <div className="space-y-5">
                        <Table
                            caption="Wallet transactions"
                            rows={transactions.data}
                            columns={[
                                { key: 'created_at', label: 'Date', mono: true, render: (row) => `${formatDate(row.created_at)} ${formatTime(row.created_at)}` },
                                { key: 'type', label: 'Type', render: (row) => <Badge size="sm">{row.type_label}</Badge> },
                                { key: 'description', label: 'Details', render: (row) => row.description ?? '' },
                                { key: 'amount', label: 'Amount', align: 'right', render: (row) => <Money value={row.amount} sign /> },
                                { key: 'balance_after', label: 'Balance after', align: 'right', render: (row) => <Money value={row.balance_after} /> },
                            ]}
                        />
                        <Pagination page={transactions} only={only} noun={['transaction', 'transactions']} />
                    </div>
                )}
            </Card>

            {action && <MoneyDialog action={action} url={action === 'topUp' ? urls.topUp : urls.withdraw} available={available} limits={limits} onClose={() => setAction(null)} />}
        </>
    );
}

function MoneyDialog({ action, url, available, limits, onClose }) {
    const form = useForm({ amount: null });
    const words = ACTIONS[action];
    const hint =
        action === 'topUp'
            ? `From ${formatMoney(limits.minTopUp, { decimals: 0 })} to ${formatMoney(limits.maxTopUp, { decimals: 0 })} at a time.`
            : `You have ${formatMoney(available)} available.`;

    function submit(event) {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: onClose });
    }

    const amount = form.data.amount ? formatMoney(form.data.amount, { decimals: Number.isInteger(form.data.amount) ? 0 : 2 }) : '';

    return (
        <Dialog
            open
            onClose={onClose}
            title={words.title}
            description={SIMULATED}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" form="wallet-money" iconLeft={words.icon} loading={form.processing}>
                        {amount ? `${words.button} ${amount}` : words.button}
                    </Button>
                </>
            }
        >
            <form id="wallet-money" onSubmit={submit} noValidate>
                <MoneyInput
                    id="amount"
                    label="Amount"
                    hint={hint}
                    value={form.data.amount}
                    onValueChange={(value) => form.setData('amount', value)}
                    error={form.errors.amount}
                    autoFocus
                />
            </form>
        </Dialog>
    );
}

WalletShow.layout = {
    title: 'Wallet',
    description: 'Your simulated wallet: top up, withdraw, and see every movement.',
};
