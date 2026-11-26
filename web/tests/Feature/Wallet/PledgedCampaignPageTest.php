<?php

namespace Tests\Feature\Wallet;

use App\Models\Campaign;
use App\Models\Pledge;
use App\Models\User;
use App\Notifications\FundingOutcome;
use App\Policies\CampaignPolicy;
use App\Services\PledgeService;
use App\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-19, PB-20: an investor can always open a campaign they pledged to, whatever has happened
 * to it since (funded, not funded, repaying, ...), and the page says where it stands in place of
 * the pledge form. Everyone else still gets 404 for a campaign that isn't open (PB-17), and the
 * marketplace still lists open campaigns only.
 */
class PledgedCampaignPageTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-25 10:00:00');
        Notification::fake();
    }

    private function page(Campaign $campaign): string
    {
        return "/investor/campaigns/{$campaign->id}";
    }

    /** A campaign funded by $investor's pledge of the whole target. */
    private function fundedBy(User $investor, int $targetMvr = 5_000): Campaign
    {
        $campaign = $this->liveCampaign(targetMvr: $targetMvr);
        app(PledgeService::class)->pledge($investor, $campaign, $targetMvr * 100);

        return $campaign->refresh();
    }

    /** A campaign that missed its target: $investor pledged MVR 1,000, then it was refunded. */
    private function failedWith(User $investor): Campaign
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000, deadline: now()->addDay());
        app(PledgeService::class)->pledge($investor, $campaign, 1_000_00);
        Carbon::setTestNow(now()->addDays(2));
        app(SettlementService::class)->settleDue();

        return $campaign->refresh();
    }

    public function test_pledging_the_last_amount_lands_on_the_funded_campaign_page_with_the_success_message(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000);
        app(PledgeService::class)->pledge($this->investor(topUpCents: 6_000_00), $campaign, 6_000_00);
        $last = $this->investor(topUpCents: 5_000_00);

        $this->actingAs($last)->from($this->page($campaign))->followingRedirects()
            ->post("/investor/campaigns/{$campaign->id}/pledges", ['amount' => 4_000])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Investor/Campaigns/Show')
                ->where('flash.status', 'Your pledge of MVR 4,000.00 completed this campaign’s funding.')
                ->where('campaign.raised', 10_000)
                ->where('campaign.amount_requested', 10_000)
                ->where('standing.status', Campaign::STATUS_FUNDED)
                ->where('standing.title', 'Fully funded')
                ->where('pledge.allowed', false)
                ->has('myPledges', 1)
                ->where('myPledges.0.amount', '4000.00')
                ->where('myPledges.0.status', 'held'));

        $this->assertSame(Campaign::STATUS_FUNDED, $campaign->fresh()->status);
    }

    public function test_an_investor_who_pledged_can_view_a_funded_campaign(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $campaign = $this->fundedBy($investor);

        $this->actingAs($investor)->get($this->page($campaign))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('standing.status', 'funded')
                ->where('standing.body', 'The target was reached. The money stays in the campaign’s escrow until it is paid out to the borrower.')
                ->where('myPledges.0.status', 'held')
                ->where('shariahOnlyNote', null));
    }

    public function test_an_investor_who_pledged_can_view_a_failed_campaign_and_sees_the_refund(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $campaign = $this->failedWith($investor);
        $this->assertSame(Campaign::STATUS_FAILED, $campaign->status);

        $this->actingAs($investor)->get($this->page($campaign))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('standing.status', 'failed')
                ->where('standing.title', 'Not funded')
                ->where('standing.body', 'The target wasn’t reached by the deadline, so every pledge was refunded to the investors’ wallets.')
                ->where('campaign.raised', 0)
                ->where('myPledges.0.amount', '1000.00')
                ->where('myPledges.0.status', 'refunded'));
    }

    public function test_every_later_status_shows_its_own_message_and_each_pledge_its_status(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $campaign = $this->fundedBy($investor);
        Pledge::where('campaign_id', $campaign->id)->update(['status' => 'settled']); // paid out (PB-34)

        foreach (['repaying' => 'Funded and repaying', 'completed' => 'Fully repaid', 'defaulted' => 'Defaulted'] as $status => $title) {
            $campaign->forceFill(['status' => $status])->save();

            $this->actingAs($investor)->get($this->page($campaign))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('standing.status', $status)->where('standing.title', $title)
                    ->where('myPledges.0.status', 'settled'));
        }
    }

    public function test_a_pledger_sees_funding_closed_while_the_deadline_waits_to_be_settled(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $campaign = $this->liveCampaign(targetMvr: 10_000, deadline: now()->addDay());
        app(PledgeService::class)->pledge($investor, $campaign, 1_000_00);
        Carbon::setTestNow(now()->addDays(2)); // past the deadline, the scheduled job hasn't run

        $this->actingAs($investor)->get($this->page($campaign))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('standing.status', 'closed')->where('standing.title', 'Funding has closed')
                ->where('myPledges.0.status', 'held'));
    }

    public function test_an_open_campaign_has_no_standing_so_the_pledge_form_shows(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $campaign = $this->liveCampaign(targetMvr: 10_000);

        $this->actingAs($investor)->get($this->page($campaign))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('standing', null)->where('pledge.allowed', true)->has('myPledges', 0));
    }

    public function test_an_investor_who_did_not_pledge_gets_404_for_funded_and_failed_campaigns(): void
    {
        $funded = $this->fundedBy($this->investor(topUpCents: 5_000_00));
        $failed = $this->failedWith($this->investor(topUpCents: 5_000_00));
        $stranger = $this->investor(topUpCents: 5_000_00);

        $this->actingAs($stranger)->get($this->page($funded))->assertNotFound();
        $this->actingAs($stranger)->get($this->page($failed))->assertNotFound();
    }

    public function test_the_marketplace_lists_open_campaigns_only_even_for_an_investor_who_pledged(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $funded = $this->fundedBy($investor);
        $open = $this->liveCampaign(targetMvr: 10_000);

        $this->actingAs($investor)->get('/investor/campaigns')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.total', 1)->where('campaigns.data.0.id', $open->id));
        $this->assertSame(Campaign::STATUS_FUNDED, $funded->status);
    }

    public function test_a_shariah_only_investor_can_still_view_a_conventional_campaign_they_pledged_to_with_a_note(): void
    {
        $investor = $this->investor(topUpCents: 6_000_00);
        $open = $this->liveCampaign(targetMvr: 10_000, contract: 'conventional');
        app(PledgeService::class)->pledge($investor, $open, 1_000_00);
        $funded = $this->fundedBy($investor);
        $this->assertFalse($open->isShariah());
        $investor->investorProfile->forceFill(['shariah_only' => true])->save(); // turned on afterwards

        $this->actingAs($investor)->get($this->page($open))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Investor/Campaigns/Show')
                ->where('shariahOnlyNote', CampaignPolicy::SHARIAH_ONLY_PLEDGED)
                ->where('standing', null)
                ->where('pledge.allowed', false)
                ->where('myPledges.0.status', 'held'));
        $this->actingAs($investor)->get($this->page($funded))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('shariahOnlyNote', CampaignPolicy::SHARIAH_ONLY_PLEDGED)->where('standing.status', 'funded'));

        // Pledging to it again is still refused, and it stays out of their marketplace.
        $this->actingAs($investor)->post("/investor/campaigns/{$open->id}/pledges", ['amount' => 100])->assertForbidden();
        $this->actingAs($investor)->get('/investor/campaigns')
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.total', 0));
    }

    public function test_a_shariah_only_investor_who_did_not_pledge_is_still_sent_back_to_the_marketplace(): void
    {
        $campaign = $this->liveCampaign(targetMvr: 10_000, contract: 'conventional');

        $this->actingAs($this->investor(shariahOnly: true))->get($this->page($campaign))
            ->assertRedirect('/investor/campaigns')->assertSessionHas('notice', CampaignPolicy::SHARIAH_ONLY_HIDDEN);
    }

    public function test_the_wallet_history_links_each_pledge_and_refund_to_its_campaign(): void
    {
        $investor = $this->investor(topUpCents: 5_000_00);
        $campaign = $this->failedWith($investor); // a pledge, then its refund

        $this->actingAs($investor)->get('/investor/wallet')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 3)
                ->where('transactions.data.0.type', 'pledge_refund')
                ->where('transactions.data.0.campaign_url', route('investor.campaigns.show', $campaign))
                ->where('transactions.data.1.type', 'pledge')
                ->where('transactions.data.1.campaign_url', route('investor.campaigns.show', $campaign))
                ->where('transactions.data.2.type', 'top_up')
                ->where('transactions.data.2.campaign_url', null));
        $this->get(route('investor.campaigns.show', $campaign))->assertOk(); // and the link opens
    }

    public function test_the_funded_email_links_investors_to_the_campaign_and_the_refund_email_to_the_wallet(): void
    {
        $investor = $this->investor(topUpCents: 6_000_00);
        $funded = $this->fundedBy($investor);
        $failed = $this->failedWith($investor);

        Notification::assertSentTo($investor, FundingOutcome::class, function (FundingOutcome $n) use ($investor, $funded) {
            return $n->outcome === FundingOutcome::FUNDED_INVESTOR && $n->campaign->is($funded)
                && $n->toMail($investor)->actionUrl === route('investor.campaigns.show', $funded)
                && $n->toMail($investor)->actionText === 'View the campaign';
        });
        Notification::assertSentTo($investor, FundingOutcome::class, function (FundingOutcome $n) use ($investor, $failed) {
            return $n->outcome === FundingOutcome::REFUNDED_INVESTOR && $n->campaign->is($failed)
                && $n->toMail($investor)->actionUrl === route('investor.wallet.show');
        });
    }
}
