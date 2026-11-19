<?php

namespace Database\Seeders;

use App\Models\BorrowerProfile;
use App\Models\Campaign;
use App\Models\InvestorProfile;
use App\Models\Island;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts in the states the UI screenshots need (PB-51): a borrower part-way through
 * onboarding with a migrated address to check (PB-53), a verified investor, an investor with no
 * profile yet (mode switch disabled), an investor at the documents step, an admin, and a
 * verification queue with three complete, submitted accounts and one incomplete one.
 * PB-13 to PB-15 add a verified borrower with requests in every state, a campaign review queue
 * (a Murabahah with a credit report first) and live campaigns for the marketplace.
 *
 * Only for `npm run screenshots`, which runs it on its own throwaway database
 * (shariah_p2p_screenshots), never on the development or test database. Documents are rows
 * only (no files), which is all the list pages read. Password for all: DatabaseSeeder::DEMO_PASSWORD.
 */
class ScreenshotSeeder extends Seeder
{
    public function run(): void
    {
        $password = DatabaseSeeder::DEMO_PASSWORD;

        User::factory()->admin()->create(['name' => 'Demo Admin', 'email' => 'admin@example.com', 'password' => $password]);

        // Borrower part-way through onboarding (PB-53): profile saved before addresses were
        // structured, so the address still has to be checked; ID and business certificate
        // uploaded, proof of address and financial statement still missing.
        $borrower = User::factory()->borrower()->create(['name' => 'Ibrahim Waheed', 'email' => 'borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->legacyAddress('M. Blue Lagoon, Malé', [
            'address_house' => 'M. Blue Lagoon',
            'island_id' => Island::idFor('Malé City', 'Malé'),
        ])->for($borrower)->create([
            'business_name' => 'Coral Cove Trading',
            'business_registration_no' => 'C-0412/2021',
            'sector' => 'fisheries',
            'years_operating' => 6,
            'declared_annual_income' => 840000,
        ]);
        KycDocument::factory()->for($borrower)->create(['original_name' => 'national-id-front.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 839_680]);
        KycDocument::factory()->for($borrower)->ofType('business_registration')->create(['original_name' => 'registration-certificate.pdf', 'size_bytes' => 245_760]);

        // Investor: verified, acknowledged, documents approved.
        $investor = User::factory()->investor()->verified()->create(['name' => 'Aishath Rasheed', 'email' => 'investor@example.com', 'password' => $password]);
        InvestorProfile::factory()->for($investor)->create([
            'risk_acknowledged_at' => now()->subDays(3),
            'address_house' => 'H. Sea Breeze', 'address_road' => 'Nirolhu Magu', 'island_id' => Island::idFor('Malé City', 'Hulhumalé'), 'postcode' => '23000',
        ]);
        KycDocument::factory()->for($investor)->create(['status' => 'approved', 'original_name' => 'id-card.pdf']);
        KycDocument::factory()->for($investor)->ofType('proof_of_address')->create(['status' => 'approved', 'original_name' => 'stelco-bill-october.pdf']);

        // Investor who has only registered: the mode switch is disabled until the profile exists.
        User::factory()->investor()->create(['name' => 'Hassan Nizam', 'email' => 'new-investor@example.com', 'password' => $password]);

        // Investor at the documents step: details and address saved, nothing uploaded yet.
        $onboarding = User::factory()->investor()->create(['name' => 'Mohamed Rilwan', 'email' => 'onboarding-investor@example.com', 'password' => $password]);
        InvestorProfile::factory()->for($onboarding)->create(['address_house' => 'G. Hiyaa', 'address_road' => 'Hithadhoo Magu', 'island_id' => Island::idFor('Seenu (Addu City)', 'Hithadhoo'), 'postcode' => '19020']);

        // Individual borrower who has finished every step but not yet submitted: the review step.
        $ready = User::factory()->borrower()->create(['name' => 'Aminath Shiuna', 'email' => 'ready-borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->individual()->for($ready)->create(['address_house' => 'Ma. Sosun Villa', 'address_road' => 'Sosun Magu', 'postcode' => '20187']);
        KycDocument::factory()->for($ready)->create(['original_name' => 'id-card-both-sides.pdf']);
        KycDocument::factory()->for($ready)->ofType('proof_of_address')->create(['original_name' => 'fenaka-bill-october.pdf']);

        // The verification queue: three complete, submitted accounts, oldest first, and one incomplete.
        $queue = [
            ['Mariyam Shifza', 'investor', 5],
            ['Ahmed Saleem', 'borrower', 4],
            ['Fathimath Zaha', 'investor', 2],
        ];
        foreach ($queue as [$name, $role, $daysAgo]) {
            $user = User::factory()->{$role}()->submitted()->create(['name' => $name, 'created_at' => now()->subDays($daysAgo)]);
            if ($role === 'borrower') {
                BorrowerProfile::factory()->individual()->for($user)->create();
            } else {
                InvestorProfile::factory()->for($user)->create(['risk_acknowledged_at' => now()->subDays($daysAgo)]);
            }
            KycDocument::factory()->for($user)->create();
            KycDocument::factory()->for($user)->ofType('proof_of_address')->create(['original_name' => 'council-letter.pdf']);
        }
        User::factory()->borrower()->create(['name' => 'Still Registering']);

        $this->campaigns($password);
    }

    /**
     * Financing requests for the PB-13 to PB-15 pages.
     */
    private function campaigns(string $password): void
    {
        // A verified SME borrower with one request in each state.
        $faiz = User::factory()->borrower()->verified()->create(['name' => 'Mohamed Faiz', 'email' => 'verified-borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->for($faiz)->create(['business_name' => 'Faiz Fresh Catch', 'sector' => 'fisheries', 'declared_annual_income' => 480000]);

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
        KycDocument::factory()->for($faiz)->ofType(KycDocument::TYPE_CREDIT_REPORT)->create([
            'campaign_id' => $van->id, 'original_name' => 'mma-cis-report.pdf', 'size_bytes' => 412_000,
        ]);

        Campaign::factory()->conventional()->live(now()->addDays(19))->for($faiz, 'borrower')->create([
            'title' => 'Cold room extension', 'purpose' => 'growth', 'amount_requested' => 85000, 'tenure_months' => 24, 'submitted_at' => now()->subDays(14),
        ]);
        Campaign::factory()->conventional()->submitted()->for($faiz, 'borrower')->create([
            'title' => 'Second fishing dhoni', 'purpose' => 'start_up', 'amount_requested' => 400000, 'tenure_months' => 60,
            'status' => 'rejected', 'status_reason' => 'The credit report shows two late payments, but the request says none.',
            'submitted_at' => now()->subDays(20),
        ]);

        // More requests in the admin queue, and campaigns in the marketplace.
        $others = [
            ['Aminath Leena', 'Island Tutors', 'mudarabah', 'education', 'Tutoring centre in Hulhumalé', 60000, 12, 'submitted', 1],
            ['Ali Shareef', 'Sun Island Cafe', 'conventional', 'growth', 'Kitchen equipment for a second cafe', 150000, 36, 'live', 26],
            ['Hawwa Nashida', 'Reef Dive Supplies', 'murabahah', 'growth', 'Dive compressor and tanks', 95000, 48, 'live', 5],
            ['Yoosuf Rameez', null, 'mudarabah', 'start_up', 'Guest house fit-out in Maafushi', 300000, 60, 'live', 12],
        ];
        foreach ($others as [$name, $business, $contract, $purpose, $title, $amount, $tenure, $status, $days]) {
            $user = User::factory()->borrower()->verified()->create(['name' => $name]);
            $business
                ? BorrowerProfile::factory()->for($user)->create(['business_name' => $business])
                : BorrowerProfile::factory()->individual()->for($user)->create();

            $factory = Campaign::factory()->{$contract}()->for($user, 'borrower');
            $factory = $status === 'live' ? $factory->live(now()->addDays($days)) : $factory->submitted();
            $factory->create(['title' => $title, 'purpose' => $purpose, 'amount_requested' => $amount, 'tenure_months' => $tenure, 'submitted_at' => now()->subDays($status === 'live' ? 10 : $days)]);
        }
    }
}
