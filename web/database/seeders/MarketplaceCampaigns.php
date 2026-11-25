<?php

namespace Database\Seeders;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\RepaymentSchedule;
use App\Models\User;
use App\Services\PledgeService;
use App\Services\WalletLedger;

/**
 * Live campaigns for the marketplace (PB-16, PB-17, PB-31), shared by DatabaseSeeder (the demo
 * accounts) and ScreenshotSeeder: a spread of sectors, purposes, amounts, tenures and all three
 * contracts, more than one page of them (nine to a page), seven of them Shariah so Shariah-only
 * mode has something to show. One SME has an earlier campaign it is repaying, so its new
 * campaign's page has a repayment history.
 *
 * No risk scores: grades say "Not yet scored" until PB-25. Every name and business is invented.
 *
 * funding() (PB-18 to PB-20) adds money: four "crowd" investors pledge to a few campaigns so the
 * progress rings show something, and two campaigns close within minutes of seeding so both
 * deadline outcomes can be watched (`composer dev` runs the scheduler, or run
 * `php artisan campaigns:settle-deadlines` once the deadline has passed):
 *  - "Solar water heaters…" closes in 10 minutes with MVR 4,000 left: pledge it to see the funded path;
 *  - "Coconut oil press…" closes in 5 minutes far short of its target: every pledge is refunded.
 * All of it goes through the ledger and pledge services, so the seeded ledger reconciles.
 */
final class MarketplaceCampaigns
{
    /**
     * [borrower, business (null: individual), sector, contract, terms, purpose, title, amount, tenure, days left]
     *
     * @var list<array{0: string, 1: ?string, 2: ?string, 3: string, 4: array<string, mixed>, 5: string, 6: string, 7: int, 8: int, 9: int}>
     */
    private const CAMPAIGNS = [
        ['Ismail Naseer', 'Addu Fresh Catch', 'fisheries', 'conventional', ['interest_rate_percent' => 8.0], 'growth', 'Cold room for the fish market', 85_000, 24, 19],
        ['Hawwa Nashida', 'Reef Dive Supplies', 'tourism', 'murabahah', ['asset_description' => 'Dive compressor and 20 tanks', 'markup_amount' => 7_125], 'growth', 'Dive compressor and tanks', 95_000, 48, 5],
        ['Yoosuf Rameez', null, null, 'mudarabah', ['investor_profit_share_percent' => 60], 'start_up', 'Guest house fit-out in Maafushi', 300_000, 60, 12],
        ['Shifa Abdulla', 'Sunset View Inn', 'tourism', 'conventional', ['interest_rate_percent' => 7.5], 'growth', 'Solar panels for the guest house', 220_000, 36, 26],
        ['Ahmed Faisal', 'Green Atoll Farms', 'agriculture', 'mudarabah', ['investor_profit_share_percent' => 55], 'start_up', 'Hydroponic greenhouse', 150_000, 36, 9],
        ['Mohamed Nihad', 'Island Movers', 'transport', 'murabahah', ['asset_description' => 'Two-tonne pickup truck', 'markup_amount' => 26_400], 'growth', 'Delivery pickup truck', 240_000, 48, 15],
        ['Aminath Leena', 'Bright Minds Tutoring', 'education', 'conventional', ['interest_rate_percent' => 9.0], 'growth', 'Tutoring centre classroom fit-out', 40_000, 12, 3],
        ['Mariyam Shaufa', null, null, 'conventional', ['interest_rate_percent' => 10.5], 'education', 'Diploma course fees', 30_000, 18, 28],
        ['Hussain Zahir', 'Thinadhoo Marine Works', 'manufacturing', 'mudarabah', ['investor_profit_share_percent' => 50], 'growth', 'Boatyard welding equipment', 600_000, 60, 17],
        ['Ali Shareef', 'Atoll Mart', 'retail', 'conventional', ['interest_rate_percent' => 8.5], 'growth', 'Point-of-sale system for three shops', 25_000, 6, 7],
        ['Fathimath Rishfa', 'Care Plus Clinic', 'health', 'murabahah', ['asset_description' => 'Portable ultrasound machine', 'markup_amount' => 12_000], 'growth', 'Clinic ultrasound machine', 160_000, 36, 11],
    ];

    private const DESCRIPTIONS = [
        'start_up' => 'The money pays for setting up: equipment, fit-out and the first months of stock. Quotes from two suppliers are attached to the request.',
        'growth' => 'The money pays for equipment that lets the business serve more customers. The supplier quote was checked by the administrator before approval.',
        'education' => 'The money pays the course fees in instalments to the college, so the borrower can study while working.',
        'personal' => 'The money covers a one-off personal cost, repaid from a regular salary.',
    ];

    public static function seed(): void
    {
        $addresses = array_keys(DemoData::ADDRESSES);

        foreach (self::CAMPAIGNS as $index => [$name, $business, $sector, $contract, $terms, $purpose, $title, $amount, $tenure, $days]) {
            $borrower = self::borrower($name, $business, $sector, $addresses[$index % count($addresses)]);

            Campaign::factory()->{$contract}()->live(now()->addDays($days))->for($borrower, 'borrower')->create([
                'title' => $title,
                'purpose' => $purpose,
                'description' => self::DESCRIPTIONS[$purpose],
                'business_background' => $business ? "{$business} has traded for several years and is verified on Amanah P2P." : null,
                'amount_requested' => $amount,
                'tenure_months' => $tenure,
                'submitted_at' => now()->subDays(10),
                ...$terms,
            ]);
        }

        self::bakeryWithRepaymentHistory();
    }

    /**
     * Wallets and pledges. $demoInvestor (investor@example.com) gets a pledge in the campaign that
     * will miss its target, so its refund shows up in that wallet.
     */
    public static function funding(?User $demoInvestor = null): void
    {
        $ledger = app(WalletLedger::class);
        $pledges = app(PledgeService::class);

        $crowd = collect(['Naail Hameed', 'Shirufa Ali', 'Imran Mohamed', 'Lamya Waheed'])->map(function (string $name) use ($ledger) {
            $investor = User::factory()->investor()->verified()->submitted()->create(['name' => $name]);
            InvestorProfile::factory()->for($investor)->create(['risk_acknowledged_at' => now()->subWeek()]);
            $ledger->topUp($investor, 200_000_00);

            return $investor;
        });

        // Progress on a few marketplace campaigns.
        foreach (['Cold room for the fish market' => [12_000, 18_000], 'Dive compressor and tanks' => [25_000, 20_000, 15_000], 'Hydroponic greenhouse' => [20_000]] as $title => $amounts) {
            $campaign = Campaign::where('title', $title)->firstOrFail();
            foreach ($amounts as $i => $mvr) {
                $pledges->pledge($crowd[$i], $campaign, $mvr * 100);
            }
        }

        // Closes in 10 minutes with MVR 4,000 left: pledge the rest to watch it become funded.
        $nearly = self::closingSoon('Ibrahim Shareef', 'Sunrise Lodge Thoddoo', 'murabahah', ['asset_description' => 'Solar water heaters for 12 rooms', 'markup_amount' => 3_200], 'Solar water heaters for the guest house', 40_000, 24, 10);
        foreach ([14_000, 12_000, 10_000] as $i => $mvr) {
            $pledges->pledge($crowd[$i], $nearly, $mvr * 100);
        }

        // Closes in 5 minutes far short of its target: every pledge will be refunded.
        $short = self::closingSoon('Mohamed Arif', 'Kaashidhoo Coconut Co.', 'mudarabah', ['investor_profit_share_percent' => 55], 'Coconut oil press', 60_000, 18, 5);
        foreach ([5_000, 4_000] as $i => $mvr) {
            $pledges->pledge($crowd[$i + 2], $short, $mvr * 100);
        }
        if ($demoInvestor !== null) {
            $pledges->pledge($demoInvestor, $short, 1_000_00);
        }
    }

    private static function closingSoon(string $name, string $business, string $contract, array $terms, string $title, int $amount, int $tenure, int $minutes): Campaign
    {
        $borrower = self::borrower($name, $business, 'food_and_beverage', 'addu');

        return Campaign::factory()->{$contract}()->live(now()->addMinutes($minutes))->for($borrower, 'borrower')->create([
            'title' => $title, 'purpose' => 'growth', 'description' => self::DESCRIPTIONS['growth'],
            'business_background' => "{$business} has traded for several years and is verified on Amanah P2P.",
            'amount_requested' => $amount, 'tenure_months' => $tenure, 'submitted_at' => now()->subDays(20),
            ...$terms,
        ]);
    }

    private static function borrower(string $name, ?string $business, ?string $sector, string $address): User
    {
        $user = User::factory()->borrower()->verified()->submitted()->create(['name' => $name]);
        $business
            ? BorrowerProfile::factory()->for($user)->create(['business_name' => $business, 'sector' => $sector, ...DemoData::address($address)])
            : BorrowerProfile::factory()->individual()->for($user)->create(DemoData::address($address));
        DemoData::documents($user, 'approved');

        return $user;
    }

    /**
     * An SME repaying an earlier Murabahah: eight instalments due so far, seven paid on time and
     * one four days late. Its new campaign's page shows that record.
     */
    private static function bakeryWithRepaymentHistory(): void
    {
        $borrower = self::borrower('Aishath Nuha', 'Hulhumalé Bakes', 'food_and_beverage', 'hulhumale');

        $earlier = Campaign::factory()->murabahah()->submitted()->for($borrower, 'borrower')->create([
            'title' => 'Bread slicer and display fridge', 'purpose' => 'growth', 'description' => self::DESCRIPTIONS['growth'],
            'amount_requested' => 30_000, 'markup_amount' => 2_400, 'asset_description' => 'Bread slicer and display fridge', 'tenure_months' => 12,
            'submitted_at' => now()->subMonths(9)->subDays(40),
        ]);
        $fundedAt = now()->subMonths(8)->subDays(10);
        // Workflow fields are set in code, never mass-assigned.
        $earlier->forceFill(['status' => 'repaying', 'approved_at' => $fundedAt->copy()->subDays(30), 'funding_deadline' => $fundedAt, 'funded_at' => $fundedAt])->save();

        foreach (range(1, 12) as $no) {
            $due = $fundedAt->copy()->addMonths($no)->startOfDay();
            $schedule = new RepaymentSchedule(['instalment_no' => $no, 'due_date' => $due->toDateString(), 'capital_due' => 2_500, 'return_due' => 200]);
            $schedule->campaign()->associate($earlier);
            if ($due->isPast()) {
                $schedule->forceFill(['status' => 'paid', 'paid_at' => $no === 5 ? $due->copy()->addDays(4)->setTime(10, 0) : $due->copy()->subDay()->setTime(10, 0)]);
            }
            $schedule->save();
        }

        Campaign::factory()->murabahah()->live(now()->addDays(22))->for($borrower, 'borrower')->create([
            'title' => 'Bakery deck oven and mixer', 'purpose' => 'growth', 'description' => self::DESCRIPTIONS['growth'],
            'business_background' => 'Hulhumalé Bakes supplies bread to four cafes and has repaid an earlier Amanah P2P campaign.',
            'asset_description' => 'Deck oven and spiral mixer', 'amount_requested' => 45_000, 'markup_amount' => 3_600, 'tenure_months' => 12,
            'submitted_at' => now()->subDays(6),
        ]);
    }
}
