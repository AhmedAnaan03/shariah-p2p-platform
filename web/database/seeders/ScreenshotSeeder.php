<?php

namespace Database\Seeders;

use App\Models\BorrowerProfile;
use App\Models\InvestorProfile;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Accounts in the states the UI screenshots need (PB-51): a borrower part-way through
 * verification, a verified investor, an investor with no profile yet (mode switch disabled), an
 * admin, and a verification queue with three complete accounts and one incomplete one.
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

        // Borrower: profile saved, ID uploaded, business certificate still missing.
        $borrower = User::factory()->borrower()->create(['name' => 'Ibrahim Waheed', 'email' => 'borrower@example.com', 'password' => $password]);
        BorrowerProfile::factory()->for($borrower)->create([
            'business_name' => 'Coral Cove Trading',
            'business_registration_no' => 'C-0412/2021',
            'sector' => 'fisheries',
            'years_operating' => 6,
            'declared_annual_income' => 840000,
            'address' => 'M. Blue Lagoon, Malé',
        ]);
        KycDocument::factory()->for($borrower)->create(['original_name' => 'national-id-front.jpg', 'mime_type' => 'image/jpeg', 'size_bytes' => 839_680]);

        // Investor: verified, acknowledged, documents approved.
        $investor = User::factory()->investor()->verified()->create(['name' => 'Aishath Rasheed', 'email' => 'investor@example.com', 'password' => $password]);
        InvestorProfile::factory()->for($investor)->create(['risk_acknowledged_at' => now()->subDays(3), 'address' => 'H. Sea Breeze, Hulhumalé']);
        KycDocument::factory()->for($investor)->create(['status' => 'approved', 'original_name' => 'id-card.pdf']);

        // Investor who has only registered: the mode switch is disabled until the profile exists.
        User::factory()->investor()->create(['name' => 'Hassan Nizam', 'email' => 'new-investor@example.com', 'password' => $password]);

        // The verification queue: three complete accounts, oldest first, and one incomplete.
        $queue = [
            ['Mariyam Shifza', 'investor', 5],
            ['Ahmed Saleem', 'borrower', 4],
            ['Fathimath Zaha', 'investor', 2],
        ];
        foreach ($queue as [$name, $role, $daysAgo]) {
            $user = User::factory()->{$role}()->create(['name' => $name, 'created_at' => now()->subDays($daysAgo)]);
            if ($role === 'borrower') {
                BorrowerProfile::factory()->individual()->for($user)->create();
            } else {
                InvestorProfile::factory()->for($user)->create();
            }
            KycDocument::factory()->for($user)->create();
        }
        User::factory()->borrower()->create(['name' => 'Still Registering']);
    }
}
