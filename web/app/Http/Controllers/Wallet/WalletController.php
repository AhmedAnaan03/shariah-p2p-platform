<?php

namespace App\Http\Controllers\Wallet;

use App\Exceptions\InsufficientFunds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\TopUpRequest;
use App\Http\Requests\Wallet\WithdrawRequest;
use App\Models\Pledge;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Services\WalletLedger;
use App\Support\ListFilters;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The simulated wallet page for investors and borrowers (PB-18): the balance, the money held in
 * pledges, simulated top-ups and withdrawals, and the transaction history. Registered inside each
 * role's route group, so it always shows the signed-in user's own wallet (no id in the URL).
 */
class WalletController extends Controller
{
    public const PER_PAGE = 15;

    public function show(Request $request, WalletLedger $ledger): Response
    {
        $user = $request->user();
        $role = $user->role->name;
        $account = WalletAccount::where('user_id', $user->id)->first();
        $movement = Gate::inspect('move-money');

        $filters = ListFilters::from($request, ['type' => array_keys(WalletTransaction::LABELS)]);
        $query = WalletTransaction::where('wallet_account_id', $account?->id ?? 0)
            ->when($filters->get('type'), fn ($q, $type) => $q->where('type', $type));
        $filters->applySearch($query, ['description']);
        $page = $query->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Wallet/Show', [
            'available' => Money::fromCents($ledger->balanceFor($user)),
            'held' => Money::fromCents(Money::fromDatabase(Pledge::where('investor_id', $user->id)->where('status', 'held')->sum('amount'))),
            'transactions' => [
                'data' => $page->getCollection()->map(fn (WalletTransaction $entry) => [
                    'id' => $entry->id,
                    'type' => $entry->type,
                    'type_label' => WalletTransaction::LABELS[$entry->type] ?? $entry->type,
                    'description' => $entry->description,
                    // Money into the wallet is positive, out of it negative.
                    'amount' => ($entry->direction === WalletTransaction::DEBIT ? '-' : '').$entry->amount,
                    'balance_after' => (string) $entry->balance_after,
                    'created_at' => $entry->created_at?->toIso8601String(),
                ])->values(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
                'prev_url' => $page->previousPageUrl(),
                'next_url' => $page->nextPageUrl(),
                'pages' => collect($page->onEachSide(1)->linkCollection())->slice(1, -1)
                    ->map(fn (array $link) => ['label' => $link['label'], 'url' => $link['url'], 'active' => $link['active']])->values(),
            ],
            'hasAny' => $account !== null && WalletTransaction::where('wallet_account_id', $account->id)->exists(),
            'filters' => $filters->toArray(),
            'filterOptions' => ['type' => ListFilters::options(WalletTransaction::LABELS)],
            'canMoveMoney' => $movement->allowed(),
            'blockedReason' => $movement->message(),
            'limits' => [
                'minTopUp' => Money::fromCents(TopUpRequest::MIN_CENTS),
                'maxTopUp' => Money::fromCents(WalletLedger::MAX_TOP_UP_CENTS),
                'minWithdrawal' => Money::fromCents(WithdrawRequest::MIN_CENTS),
            ],
            'urls' => [
                'topUp' => route("{$role}.wallet.top-up"),
                'withdraw' => route("{$role}.wallet.withdraw"),
            ],
        ]);
    }

    public function topUp(TopUpRequest $request, WalletLedger $ledger): RedirectResponse
    {
        $ledger->topUp($request->user(), $request->cents());

        return back()->with('status', 'Added '.Money::format($request->cents()).' to your simulated wallet.');
    }

    public function withdraw(WithdrawRequest $request, WalletLedger $ledger): RedirectResponse
    {
        try {
            $ledger->withdraw($request->user(), $request->cents());
        } catch (InsufficientFunds) {
            // The balance changed between the check and the transfer (a pledge in another tab).
            throw ValidationException::withMessages(['amount' => 'Your balance changed. Check it and try again.']);
        }

        return back()->with('status', 'Withdrew '.Money::format($request->cents()).' from your simulated wallet.');
    }
}
