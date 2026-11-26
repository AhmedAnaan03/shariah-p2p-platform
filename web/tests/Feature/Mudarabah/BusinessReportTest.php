<?php

namespace Tests\Feature\Mudarabah;

use App\Exceptions\InsufficientFunds;
use App\Exceptions\ReportRefused;
use App\Models\AuditLog;
use App\Models\BusinessReport;
use App\Models\Campaign;
use App\Models\InvestorPayout;
use App\Models\KycDocument;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletTransaction;
use App\Notifications\BusinessReportDecided;
use App\Notifications\CampaignCompleted;
use App\Services\BusinessReportService;
use App\Services\WalletLedger;
use App\Support\MudarabahCapital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesWallets;
use Tests\TestCase;

/**
 * PB-29: a Mudarabah business reports each period; an administrator approves or rejects it; an
 * approved profit first restores earlier genuine losses and the rest is shared at the agreed
 * ratio; a genuine loss reduces the investors' capital in proportion, unless it was caused by
 * negligence or misconduct; the last period settles the capital and completes the campaign.
 *
 * The campaign: MVR 10,000 over 3 months, 60 : 40, funded 6,000 (Aisha) + 4,000 (Badr) and paid
 * out on 26 Nov 2026. Planned capital returns of 3,333.33, 3,333.33 and 3,333.34 on 26 Dec 2026,
 * 26 Jan 2027 and 26 Feb 2027. The business's wallet holds the MVR 10,000 paid out, plus a top-up.
 */
class BusinessReportTest extends TestCase
{
    use MakesWallets, RefreshDatabase;

    private Campaign $campaign;

    private User $aisha;

    private User $badr;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 10:00:00');
        Notification::fake();
        Storage::fake('kyc');

        [$this->campaign, [$this->aisha, $this->badr]] = $this->paidOutCampaign('mudarabah', 10_000, 3, [6_000, 4_000]);
        app(WalletLedger::class)->topUp($this->campaign->borrower, 5_000_00);
        $this->admin = User::factory()->admin()->create();
    }

    private function service(): BusinessReportService
    {
        return app(BusinessReportService::class);
    }

    /** Submit the next period's report on its planned date (MVR amounts). */
    private function submit(float $revenue, float $costs, float $capital): BusinessReport
    {
        $schedule = RepaymentSchedule::where('campaign_id', $this->campaign->id)->where('status', '!=', 'paid')->orderBy('instalment_no')->first();
        Carbon::setTestNow($schedule->due_date->copy()->setTime(10, 0));

        return $this->service()->submit($this->campaign->borrower, $this->campaign, [
            'revenue' => (int) round($revenue * 100),
            'costs' => (int) round($costs * 100),
            'capital_returned' => (int) round($capital * 100),
        ]);
    }

    /** @return array<string, int> one investor's capital account */
    private function account(User $investor): array
    {
        return MudarabahCapital::accounts($this->campaign)[$investor->id];
    }

    public function test_tc1_an_approved_profit_is_shared_at_the_ratio_and_returned_capital_paid_pro_rata(): void
    {
        $report = $this->submit(5_000, 3_000, 3_333.33);
        $this->assertSame(['2000.00', 'submitted'], [(string) $report->profit, $report->status]);

        $this->service()->approve($this->admin, $report);

        $report->refresh();
        $this->assertSame(['0.00', '1200.00', '800.00'], [(string) $report->loss_restored, (string) $report->investors_profit, (string) $report->business_profit]);
        // Aisha: 2,000.00 capital + 720.00 profit. Badr: 1,333.33 + 480.00.
        $this->assertSame([2_720_00, 1_813_33], [$this->balance($this->aisha), $this->balance($this->badr)]);
        $this->assertSame(15_000_00 - 4_533_33, $this->balance($this->campaign->borrower), 'The business paid capital + the investors\' share; it keeps its 800.');
        $this->assertSame(1, WalletTransaction::where('type', WalletTransaction::MUDARABAH_PAYMENT)->where('direction', 'debit')->count());
        $this->assertSame(0, app(WalletLedger::class)->balance(WalletAccount::escrowFor($this->campaign)));
        $this->assertSame('paid', $report->repaymentSchedule->status);
        $this->assertSame(['capital' => 2_000_00, 'profit' => 720_00, 'outstanding' => 4_000_00], array_intersect_key($this->account($this->aisha), ['capital' => 0, 'profit' => 0, 'outstanding' => 0]));
        Notification::assertSentTo($this->campaign->borrower, BusinessReportDecided::class);
        Notification::assertSentTo($this->aisha, BusinessReportDecided::class, fn (BusinessReportDecided $n) => $n->lines()[1] === 'You received MVR 2,720.00: MVR 2,000.00 of capital, MVR 0.00 restoring earlier losses and MVR 720.00 as your profit share.');
        $this->assertLedgerReconciles();
    }

    public function test_tc2_a_genuine_loss_reduces_the_investors_capital_in_proportion_and_moves_no_money_for_it(): void
    {
        $report = $this->submit(2_000, 3_000, 0);
        $this->service()->approve($this->admin, $report, BusinessReport::CAUSE_BUSINESS);

        $this->assertSame('1000.00', (string) $report->fresh()->loss_borne);
        $this->assertSame([600_00, 400_00], [$this->account($this->aisha)['loss'], $this->account($this->badr)['loss']]);
        $this->assertSame([5_400_00, 3_600_00], [$this->account($this->aisha)['outstanding'], $this->account($this->badr)['outstanding']]);
        $this->assertSame([0, 0], [$this->balance($this->aisha), $this->balance($this->badr)]);
        $this->assertSame(0, WalletTransaction::where('type', WalletTransaction::MUDARABAH_PAYMENT)->count(), 'Nothing to pay: no transfer.');
        $this->assertSame(2, InvestorPayout::whereNull('transfer_ref')->where('loss_amount', '>', 0)->count(), 'The loss is recorded per investor.');
        Notification::assertSentTo($this->aisha, BusinessReportDecided::class, fn (BusinessReportDecided $n) => str_contains(implode(' ', $n->lines()), 'you bear your share, MVR 600.00, which reduces your capital'));
        $this->assertLedgerReconciles();
    }

    public function test_tc3_a_loss_followed_by_a_smaller_profit_only_restores_capital(): void
    {
        $this->service()->approve($this->admin, $this->submit(2_000, 3_000, 0), BusinessReport::CAUSE_BUSINESS); // loss 1,000
        $report = $this->submit(3_500, 3_000, 0); // profit 500
        $this->service()->approve($this->admin, $report);

        $report->refresh();
        $this->assertSame(['500.00', '0.00', '0.00'], [(string) $report->loss_restored, (string) $report->investors_profit, (string) $report->business_profit]);
        $this->assertSame([300_00, 200_00], [$this->balance($this->aisha), $this->balance($this->badr)]);
        $this->assertSame([300_00, 200_00], [$this->account($this->aisha)['unrestored'], $this->account($this->badr)['unrestored']], 'Half the loss is still to be restored.');
        $this->assertLedgerReconciles();
    }

    public function test_tc4_a_loss_followed_by_a_larger_profit_restores_it_first_and_shares_the_rest(): void
    {
        $this->service()->approve($this->admin, $this->submit(2_000, 3_000, 0), BusinessReport::CAUSE_BUSINESS); // loss 1,000
        $report = $this->submit(4_500, 3_000, 0); // profit 1,500: 1,000 restores, 500 is shared
        $this->service()->approve($this->admin, $report);

        $report->refresh();
        $this->assertSame(['1000.00', '300.00', '200.00'], [(string) $report->loss_restored, (string) $report->investors_profit, (string) $report->business_profit]);
        // Aisha: 600 restored + 180 profit; Badr: 400 + 120.
        $this->assertSame([780_00, 520_00], [$this->balance($this->aisha), $this->balance($this->badr)]);
        $this->assertSame([0, 0], [$this->account($this->aisha)['unrestored'], $this->account($this->badr)['unrestored']]);
        $row = InvestorPayout::where('business_report_id', $report->id)->where('investor_id', $this->aisha->id)->firstOrFail();
        $this->assertSame(['600.00', '180.00'], [(string) $row->loss_restored_amount, (string) $row->return_amount], 'Restoration and profit recorded separately.');
        $this->assertLedgerReconciles();
    }

    public function test_tc5_several_periods_end_in_a_final_settlement_that_completes_the_campaign(): void
    {
        // Period 1: a loss of 1,000 with 3,333.33 of capital returned. Period 2: a profit of 600,
        // all of it restoring. Period 3 (final): a profit of 2,000; 400 restores the rest of the
        // loss, 1,600 is shared (960 to the investors), and the rest of the capital is returned.
        $this->service()->approve($this->admin, $this->submit(2_000, 3_000, 3_333.33), BusinessReport::CAUSE_BUSINESS);
        $this->service()->approve($this->admin, $this->submit(3_600, 3_000, 3_333.33));
        $outstanding = MudarabahCapital::totals(MudarabahCapital::accounts($this->campaign))['outstanding'];
        $this->assertSame(10_000_00 - 6_666_66 - 1_000_00, $outstanding);

        $final = $this->submit(5_000, 3_000, $outstanding / 100);
        $this->service()->approve($this->admin, $final);

        $final->refresh();
        $this->assertSame(['400.00', '960.00', '640.00'], [(string) $final->loss_restored, (string) $final->investors_profit, (string) $final->business_profit]);
        $this->assertSame(Campaign::STATUS_COMPLETED, $this->campaign->fresh()->status);
        $totals = MudarabahCapital::totals(MudarabahCapital::accounts($this->campaign));
        $this->assertSame([0, 0, 1_000_00, 1_000_00, 960_00], [$totals['outstanding'], $totals['unrestored'], $totals['loss'], $totals['restored'], $totals['profit']]);
        // The investors got back exactly their capital (9,000 returned + 1,000 restored) plus 960 profit.
        $this->assertSame(10_960_00, $this->balance($this->aisha) + $this->balance($this->badr));
        $this->assertSame([6_576_00, 4_384_00], [$this->balance($this->aisha), $this->balance($this->badr)], '60% and 40% of everything.');
        Notification::assertSentTo($this->aisha, CampaignCompleted::class, fn (CampaignCompleted $n) => $n->lines()[1] === 'You received MVR 5,400.00 of capital back and MVR 576.00 as your profit share.');
        Notification::assertSentTo($this->campaign->borrower, CampaignCompleted::class);
        $this->assertLedgerReconciles();
    }

    public function test_tc6_a_loss_from_negligence_stays_with_the_business_is_not_restored_from_profit_and_must_be_settled(): void
    {
        $report = $this->submit(2_000, 3_000, 0);
        $this->service()->approve($this->admin, $report, BusinessReport::CAUSE_NEGLIGENCE, 'Stock was left unrefrigerated against the agreed plan.');

        $report->refresh();
        $this->assertSame(['negligence', '0.00', '1000.00'], [$report->loss_cause, (string) $report->loss_borne, (string) $report->business_liable_loss]);
        $this->assertSame([6_000_00, 4_000_00], [$this->account($this->aisha)['outstanding'], $this->account($this->badr)['outstanding']], 'The investors\' capital is not reduced.');
        $audit = AuditLog::where('action', 'business_report.approved')->latest('id')->first();
        $this->assertSame(['negligence', 'Stock was left unrefrigerated against the agreed plan.'], [$audit->details['loss_cause'], $audit->details['reason']]);
        $this->assertSame($this->admin->id, $audit->actor_id);
        Notification::assertSentTo($this->badr, BusinessReportDecided::class, fn (BusinessReportDecided $n) => str_contains(implode(' ', $n->lines()), 'the business is liable for it and the investors\' capital is not reduced'));

        // A later profit is shared at once: there is no investor loss to restore.
        $next = $this->submit(4_000, 3_000, 0);
        $this->service()->approve($this->admin, $next);
        $this->assertSame(['0.00', '600.00'], [(string) $next->fresh()->loss_restored, (string) $next->fresh()->investors_profit]);
        $this->assertLedgerReconciles();
    }

    public function test_tc7_a_negligence_finding_needs_a_reason(): void
    {
        $report = $this->submit(2_000, 3_000, 0);

        $this->expectException(ReportRefused::class);
        $this->expectExceptionMessage('Give the reason for finding negligence or misconduct.');
        $this->service()->approve($this->admin, $report, BusinessReport::CAUSE_NEGLIGENCE, ' ');
    }

    public function test_tc8_a_rejected_report_moves_nothing_and_a_new_one_can_be_submitted(): void
    {
        $report = $this->submit(5_000, 3_000, 3_333.33);
        $this->service()->reject($this->admin, $report, 'The costs do not match the attached accounts.');

        $report->refresh();
        $this->assertSame(['rejected', 'The costs do not match the attached accounts.'], [$report->status, $report->decision_reason]);
        $this->assertSame([0, 0], [$this->balance($this->aisha), $this->balance($this->badr)]);
        $this->assertSame('due', $report->repaymentSchedule->status);
        Notification::assertSentTo($this->campaign->borrower, BusinessReportDecided::class, fn (BusinessReportDecided $n) => $n->subject() === 'Your business report was not accepted');
        Notification::assertNotSentTo($this->aisha, BusinessReportDecided::class);

        $again = $this->submit(5_000, 3_200, 3_333.33);
        $this->assertSame('submitted', $again->status);
        $this->assertLedgerReconciles();
    }

    public function test_tc9_a_report_is_refused_before_its_planned_date_twice_for_one_period_and_decided_only_once(): void
    {
        $refusals = [];
        $try = function (callable $call) use (&$refusals) {
            try {
                $call();
            } catch (ReportRefused $e) {
                $refusals[] = $e->getMessage();
            }
        };

        // 26 Nov: period 1 is planned for 26 Dec.
        $try(fn () => $this->service()->submit($this->campaign->borrower, $this->campaign, ['revenue' => 100, 'costs' => 0, 'capital_returned' => 0]));
        $report = $this->submit(5_000, 3_000, 0);
        $try(fn () => $this->service()->submit($this->campaign->borrower, $this->campaign, ['revenue' => 100, 'costs' => 0, 'capital_returned' => 0]));
        $this->service()->approve($this->admin, $report);
        $try(fn () => $this->service()->reject($this->admin, $report, 'Changed my mind.'));

        $this->assertSame([
            'The report for period 1 can be submitted from its planned date, 26 Dec 2026.',
            'The report for period 1 is waiting for review.',
            'This report has already been decided.',
        ], $refusals);
    }

    public function test_tc10_a_final_report_that_does_not_settle_the_capital_is_refused(): void
    {
        $this->service()->approve($this->admin, $this->submit(3_000, 3_000, 3_333.33));
        $this->service()->approve($this->admin, $this->submit(3_000, 3_000, 3_333.33));

        $this->expectException(ReportRefused::class);
        $this->expectExceptionMessage('This is the final period, so it must settle the capital: MVR 3,333.34 would still be invested.');
        $this->submit(3_000, 3_000, 0);
    }

    public function test_tc11_approval_is_refused_when_the_business_wallet_cannot_pay_and_nothing_changes(): void
    {
        $report = $this->submit(5_000, 3_000, 3_333.33);
        app(WalletLedger::class)->withdraw($this->campaign->borrower, $this->balance($this->campaign->borrower) - 100_00);

        try {
            $this->service()->approve($this->admin, $report);
            $this->fail('Approval must be refused.');
        } catch (InsufficientFunds) {
        }

        $this->assertSame('submitted', $report->fresh()->status);
        $this->assertSame(0, InvestorPayout::count());
        $this->assertSame('due', $report->repaymentSchedule->fresh()->status);
        $this->assertLedgerReconciles();
    }

    public function test_tc12_a_supporting_document_is_stored_encrypted_against_the_campaign(): void
    {
        Carbon::setTestNow('2026-12-26 10:00:00');
        $report = $this->service()->submit($this->campaign->borrower, $this->campaign, ['revenue' => 500_00, 'costs' => 100_00, 'capital_returned' => 0], UploadedFile::fake()->createWithContent('accounts.pdf', '%PDF-1.4 demo accounts'));

        $document = $report->document;
        $this->assertSame([KycDocument::TYPE_BUSINESS_REPORT, $this->campaign->id], [$document->document_type, $document->campaign_id]);
        $stored = Storage::disk('kyc')->get($document->storage_path);
        $this->assertStringNotContainsString('demo accounts', $stored, 'Encrypted on disk.');
        $this->assertSame('%PDF-1.4 demo accounts', Crypt::decryptString($stored));
    }
}
