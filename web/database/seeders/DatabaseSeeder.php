<?php

namespace Database\Seeders;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\User;
use App\Services\WalletLedger;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Password for every demo account. It meets Password::defaults() so it can also be typed
     * into the change-password form as the "current password".
     */
    public const DEMO_PASSWORD = 'Demo#2026';

    /**
     * Demo accounts for trying the portals locally (`php artisan migrate:fresh --seed`), each
     * with complete, realistic dummy data (PB-59): a structured address, a financial profile for
     * borrowers, and a demo PDF in every document slot (DemoData).
     *
     *   admin@example.com             administrator (the only way to get one: registration never offers the role)
     *   borrower@example.com          verified individual borrower
     *   sme@example.com               verified SME borrower
     *   investor@example.com          verified investor, risk acknowledged
     *   pending-borrower@example.com  individual borrower who has submitted and is waiting in the admin queue
     *   shariah-investor@example.com  verified investor with Shariah-only mode on (PB-31)
     *
     * Wallets (PB-18): investor MVR 50,000 (less a MVR 1,000 pledge to "Coconut oil press" and
     * MVR 15,000 pledged to the two paid-out campaigns), shariah-investor MVR 40,000 (less MVR 5,000),
     * sme MVR 5,000 plus the MVR 20,000 paid out to it, borrower the MVR 30,000 paid out to it.
     * Paid out (PB-34): sme's "Laundry machines for the guest house" (Murabahah) and borrower's
     * "Food truck for Malé evenings" (Mudarabah) have schedules; move them with Admin → Demo controls. Two campaigns close 5 and 10 minutes after seeding
     * (see MarketplaceCampaigns::funding); the 10-minute one, "Solar water heaters for the guest
     * house", is sme@example.com's own, so its funding progress shows on that borrower's request page.
     *   new-investor@example.com      investor who has only registered (onboarding from the start)
     */
    public function run(): void
    {
        $password = self::DEMO_PASSWORD;

        User::factory()->admin()->create(['name' => 'Aminath Nashwa', 'email' => 'admin@example.com', 'password' => $password]);

        $borrower = User::factory()->borrower()->verified()->submitted()->create(['name' => 'Ahmed Shiyam', 'email' => 'borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->individual()->for($borrower)->create([
            'phone' => '+960 7712345', 'national_id_no' => 'A142857',
            ...DemoData::address('male'),
            ...DemoData::finances(monthlyIncome: 28_500, repayments: 3_200, years: 6, housing: 'rent', openLines: 2),
        ]);
        DemoData::documents($borrower, 'approved');

        $sme = User::factory()->borrower()->verified()->submitted()->create(['name' => 'Mariyam Zoona', 'email' => 'sme@example.com', 'password' => $password]);
        BorrowerProfile::factory()->for($sme)->create([
            'phone' => '+960 7798102', 'national_id_no' => 'A205118',
            'business_name' => 'Reef Breeze Guest House', 'business_registration_no' => 'SP-0217/2018',
            'sector' => 'tourism', 'years_operating' => 7,
            ...DemoData::address('fuvahmulah'),
            ...DemoData::finances(monthlyIncome: 95_000, repayments: 18_000, years: 7, housing: 'own_with_home_loan', openLines: 3, late: 1),
        ]);
        DemoData::documents($sme, 'approved');

        $investor = User::factory()->investor()->verified()->submitted()->create(['name' => 'Ibrahim Rasheed', 'email' => 'investor@example.com', 'password' => $password]);
        InvestorProfile::factory()->for($investor)->create([
            'phone' => '+960 9605511', 'national_id_no' => 'A318009',
            ...DemoData::address('hulhumale'),
            'risk_acknowledged_at' => now()->subDays(5),
        ]);
        DemoData::documents($investor, 'approved');

        $pending = User::factory()->borrower()->submitted()->create(['name' => 'Fathimath Shaheema', 'email' => 'pending-borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->individual()->for($pending)->create([
            'phone' => '+960 7920334', 'national_id_no' => 'A271644',
            ...DemoData::address('addu'),
            ...DemoData::finances(monthlyIncome: 18_000, repayments: 0, years: 2, housing: 'family_home', openLines: 0),
        ]);
        DemoData::documents($pending);

        User::factory()->investor()->create(['name' => 'Hassan Nizam', 'email' => 'new-investor@example.com', 'password' => $password]);

        // PB-31: an investor who only browses Shariah campaigns.
        $shariahInvestor = User::factory()->investor()->verified()->submitted()->create(['name' => 'Zeena Ahmed', 'email' => 'shariah-investor@example.com', 'password' => $password]);
        InvestorProfile::factory()->for($shariahInvestor)->create([
            'phone' => '+960 9614477', 'national_id_no' => 'A402219',
            ...DemoData::address('male_maafannu'),
            'risk_acknowledged_at' => now()->subDays(4),
            'shariah_only' => true,
        ]);
        DemoData::documents($shariahInvestor, 'approved');

        // The marketplace (PB-16, PB-17): twelve open campaigns across sectors and contracts.
        MarketplaceCampaigns::seed();

        // Simulated wallets (PB-18), through the ledger so they reconcile.
        $ledger = app(WalletLedger::class);
        $ledger->topUp($investor, 50_000_00);
        $ledger->topUp($shariahInvestor, 40_000_00);
        $ledger->topUp($sme, 5_000_00);

        // Pledges, and two campaigns closing within minutes to watch the deadline job (PB-19, PB-20).
        MarketplaceCampaigns::funding($investor, $sme);

        // Two campaigns already funded and paid out, with schedules (PB-34).
        MarketplaceCampaigns::paidOut($investor, $shariahInvestor, $sme, $borrower);
    }
}
