<?php

namespace App\Http\Controllers\Borrower;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\RepaymentRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repayment\PayInstalmentRequest;
use App\Http\Requests\Repayment\PayOffRequest;
use App\Models\Campaign;
use App\Services\RepaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Repaying from the wallet (PB-35): the next instalment, or everything left at once. Both routes
 * are in the borrower group; someone else's campaign answers 404 (CampaignPolicy::view), and
 * RepaymentService checks everything again under the campaign's lock. A refusal comes back as a
 * notice on the campaign page, never a second charge.
 */
class RepaymentController extends Controller
{
    public function store(PayInstalmentRequest $request, Campaign $campaign, RepaymentService $repayments): RedirectResponse
    {
        Gate::authorize('view', $campaign);

        try {
            $repayment = $repayments->payNext($request->user(), $campaign, (int) $request->validated('schedule_id'));
        } catch (RepaymentRefused $refused) {
            return back()->with('notice', $refused->getMessage());
        } catch (InsufficientFunds) {
            return back()->with('notice', 'Your wallet doesn’t hold enough for this instalment. Top up your wallet, then pay it.');
        }

        $campaign->refresh();
        $paid = Money::format(Money::fromDatabase($repayment->amount));

        return back()->with('status', $campaign->status === Campaign::STATUS_COMPLETED
            ? "You paid the last instalment, {$paid}. This campaign is now paid in full."
            : "You paid {$paid}. It has been shared out to the investors.");
    }

    public function payOff(PayOffRequest $request, Campaign $campaign, RepaymentService $repayments): RedirectResponse
    {
        Gate::authorize('view', $campaign);

        try {
            $count = $repayments->payOff($request->user(), $campaign, $request->cents());
        } catch (RepaymentRefused $refused) {
            return back()->with('notice', $refused->getMessage());
        } catch (InsufficientFunds) {
            return back()->with('notice', 'Your wallet doesn’t hold the '.Money::format($request->cents()).' needed to pay off. Top up your wallet, then try again.');
        }

        return back()->with('status', 'You paid off '.Money::format($request->cents())." ({$count} ".($count === 1 ? 'instalment' : 'instalments').'). This campaign is now paid in full.');
    }
}
