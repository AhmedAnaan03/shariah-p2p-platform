<?php

namespace Database\Seeders;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use App\Services\KycDocumentStore;
use App\Services\PledgeService;
use App\Services\WalletLedger;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Accounts in the states the UI screenshots need (PB-51): a borrower part-way through
 * onboarding with a migrated address to check (PB-53), a verified investor, an investor with no
 * profile yet (mode switch disabled), an investor at the documents step, an admin, and a
 * verification queue with three complete, submitted accounts and one incomplete one.
 * PB-13 to PB-15 add a verified borrower with requests in every state, a campaign review queue
 * (a Murabahah with a credit report first) and live campaigns for the marketplace. PB-16 and
 * PB-17 add MarketplaceCampaigns: more than a page of campaigns, one with a repayment history.
 *
 * Only for `npm run screenshots` / `npm run check:a11y`, which run it on their own throwaway
 * database (shariah_p2p_screenshots), never on the development or test database. Addresses,
 * financial profiles and documents (small demo PDFs) come from DemoData (PB-59). Password for all: DatabaseSeeder::DEMO_PASSWORD.
 */
class ScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        $password = DatabaseSeeder::DEMO_PASSWORD;

        User::factory()->admin()->create(['name' => 'Aminath Nashwa', 'email' => 'admin@example.com', 'password' => $password]);

        // SME borrower part-way through onboarding: details saved, address and finances not yet,
        // the ID card and registration certificate already uploaded.
        $borrower = User::factory()->borrower()->create(['name' => 'Ibrahim Waheed', 'email' => 'borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->withoutAddress()->withoutFinances()->for($borrower)->create([
            'phone' => '+960 7145734', 'national_id_no' => 'A964034',
            'business_name' => 'Coral Cove Trading', 'business_registration_no' => 'C-0412/2021',
            'sector' => 'fisheries', 'years_operating' => 6,
        ]);
        DemoData::documents($borrower, except: ['proof_of_address', 'financial_statement']);

        // Investor: verified, acknowledged, documents approved.
        $investor = User::factory()->investor()->verified()->submitted()->create(['name' => 'Aishath Rasheed', 'email' => 'investor@example.com', 'password' => $password]);
        InvestorProfile::factory()->for($investor)->create([
            'phone' => '+960 9601234', 'national_id_no' => 'A246810',
            ...DemoData::address('hulhumale'),
            'risk_acknowledged_at' => now()->subDays(3),
        ]);
        DemoData::documents($investor, 'approved');

        // Investor who has only registered: the mode switch is disabled until the profile exists.
        User::factory()->investor()->create(['name' => 'Hassan Nizam', 'email' => 'new-investor@example.com', 'password' => $password]);

        // Investor at the documents step: details and address saved, nothing uploaded yet.
        $onboarding = User::factory()->investor()->create(['name' => 'Mohamed Rilwan', 'email' => 'onboarding-investor@example.com', 'password' => $password]);
        InvestorProfile::factory()->for($onboarding)->create(['phone' => '+960 9775310', 'national_id_no' => 'A530177', ...DemoData::address('addu')]);

        // Individual borrower who has finished every step but not yet submitted: the review step.
        $ready = User::factory()->borrower()->create(['name' => 'Aminath Shiuna', 'email' => 'ready-borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->individual()->for($ready)->create([
            'phone' => '+960 7466133', 'national_id_no' => 'A801180',
            ...DemoData::address('male_maafannu'),
            ...DemoData::finances(monthlyIncome: 22_000, repayments: 2_500, years: 4, housing: 'rent', openLines: 1),
        ]);
        DemoData::documents($ready);

        // The verification queue: three complete, submitted accounts, oldest first, and one incomplete.
        $queue = [
            ['Mariyam Shifza', 'investor', 5, 'kulhudhuffushi'],
            ['Ahmed Saleem', 'borrower', 4, 'male'],
            ['Fathimath Zaha', 'investor', 2, 'fuvahmulah'],
        ];
        foreach ($queue as [$name, $role, $daysAgo, $address]) {
            $user = User::factory()->{$role}()->submitted()->create(['name' => $name, 'created_at' => now()->subDays($daysAgo)]);
            if ($role === 'borrower') {
                BorrowerProfile::factory()->individual()->for($user)->create([
                    ...DemoData::address($address),
                    ...DemoData::finances(monthlyIncome: 31_000, repayments: 4_000, years: 8, housing: 'own'),
                ]);
            } else {
                InvestorProfile::factory()->for($user)->create([...DemoData::address($address), 'risk_acknowledged_at' => now()->subDays($daysAgo)]);
            }
            DemoData::documents($user);
        }
        User::factory()->borrower()->create(['name' => 'Still Registering']);

        $this->campaigns($password);
        MarketplaceCampaigns::seed();

        // Wallets and pledges (PB-18 to PB-20).
        app(WalletLedger::class)->topUp($investor, 50_000_00);
        MarketplaceCampaigns::funding($investor);
        // The demo investor completes one campaign, so its funded page (reached from the wallet
        // history's link) can be checked: the status in place of the pledge form, and their
        // pledges with their status.
        $nearly = Campaign::where('title', 'Solar water heaters for the guest house')->sole();
        app(PledgeService::class)->pledge($investor, $nearly, PledgeService::remainingCents($nearly));
    }

    /**
     * Financing requests for the PB-13 to PB-15 pages.
     */
    private function campaigns(string $password): void
    {
        // A verified SME borrower with one request in each state.
        $faiz = User::factory()->borrower()->verified()->create(['name' => 'Mohamed Faiz', 'email' => 'verified-borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->for($faiz)->create([
            'business_name' => 'Faiz Fresh Catch', 'sector' => 'fisheries',
            ...DemoData::address('male'),
            ...DemoData::finances(monthlyIncome: 40_000, repayments: 6_000, years: 7, housing: 'own_with_home_loan', openLines: 3, late: 1),
        ]);
        DemoData::documents($faiz, 'approved');

        // Waiting for review, first in the admin queue: a Murabahah with its credit report.
        $van = Campaign::factory()->murabahah()->submitted()->for($faiz, 'borrower')->create([
            'title' => 'Refrigerated van for hotel deliveries',
            'purpose' => 'growth',
            'description' => "A second refrigerated van so we can deliver to resorts' supply boats twice a day.\nThe van is quoted at MVR 120,000 by the Malé dealer.",
            'business_background' => 'Family fish wholesaler in Malé, trading since 2015, supplying six resorts.',
            'amount_requested' => 120000,
            'markup_amount' => 9000,
            'tenure_months' => 36,
            'submitted_at' => now()->subDays(2),
        ]);
        app(KycDocumentStore::class)->store(
            $faiz,
            UploadedFile::fake()->createWithContent('mma-cis-report.pdf', DemoData::pdf("MMA credit report - {$faiz->name}")),
            KycDocument::TYPE_CREDIT_REPORT,
            $van,
        );

        Campaign::factory()->conventional()->live(now()->addDays(19))->for($faiz, 'borrower')->create([
            'title' => 'Cold room extension', 'purpose' => 'growth', 'amount_requested' => 85000, 'tenure_months' => 24, 'submitted_at' => now()->subDays(14),
        ]);
        Campaign::factory()->conventional()->submitted()->for($faiz, 'borrower')->create([
            'title' => 'Second fishing dhoni', 'purpose' => 'start_up', 'amount_requested' => 400000, 'tenure_months' => 60,
            'status' => 'rejected', 'status_reason' => 'The credit report shows two late payments, but the request says none.',
            'submitted_at' => now()->subDays(20),
        ]);

        // Another request in the admin queue. The marketplace's campaigns are MarketplaceCampaigns.
        $others = [
            ['Aminath Leena', 'Island Tutors', 'mudarabah', 'education', 'Tutoring centre in Hulhumalé', 60000, 12, 'submitted', 1],
        ];
        $addresses = ['hulhumale', 'male_maafannu', 'addu', 'kulhudhuffushi'];
        foreach ($others as $index => [$name, $business, $contract, $purpose, $title, $amount, $tenure, $status, $days]) {
            $user = User::factory()->borrower()->verified()->submitted()->create(['name' => $name]);
            $business
                ? BorrowerProfile::factory()->for($user)->create(['business_name' => $business, ...DemoData::address($addresses[$index])])
                : BorrowerProfile::factory()->individual()->for($user)->create(DemoData::address($addresses[$index]));
            DemoData::documents($user, 'approved');

            $factory = Campaign::factory()->{$contract}()->for($user, 'borrower');
            $factory = $status === 'live' ? $factory->live(now()->addDays($days)) : $factory->submitted();
            $factory->create(['title' => $title, 'purpose' => $purpose, 'amount_requested' => $amount, 'tenure_months' => $tenure, 'submitted_at' => now()->subDays($status === 'live' ? 10 : $days)]);
        }
    }
}
