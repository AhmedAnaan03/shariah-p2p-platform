<?php

namespace Tests\Feature\Database;

use App\Models\Campaign;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsDatabaseConstraints;
use Tests\TestCase;

/**
 * Roles, account status and the users table's constraints.
 */
class UserRoleTest extends TestCase
{
    use AssertsDatabaseConstraints, RefreshDatabase;

    public function test_the_migration_creates_exactly_the_three_platform_roles(): void
    {
        $this->assertSame(['admin', 'borrower', 'investor'], Role::orderBy('name')->pluck('name')->all());
    }

    public function test_each_factory_state_gives_the_user_that_role(): void
    {
        $this->assertSame(Role::BORROWER, User::factory()->borrower()->create()->role->name);
        $this->assertSame(Role::INVESTOR, User::factory()->investor()->create()->role->name);
        $this->assertSame(Role::ADMIN, User::factory()->admin()->create()->role->name);
    }

    public function test_a_new_account_starts_pending_verification(): void
    {
        $user = new User(['name' => 'Aminath', 'email' => 'aminath@example.com', 'password' => 'Secret-pass-1']);
        $user->role_id = Role::idFor(Role::BORROWER);
        $user->save();

        $this->assertSame('pending_verification', $user->fresh()->status);
    }

    public function test_role_and_status_cannot_be_mass_assigned_from_request_data(): void
    {
        // A tampered registration request trying to make itself a verified admin.
        $user = new User([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'Secret-pass-1',
            'role_id' => Role::idFor(Role::ADMIN),
            'status' => 'verified',
        ]);

        $this->assertNull($user->role_id);
        $this->assertSame('pending_verification', $user->status);
    }

    public function test_a_user_without_a_role_is_rejected(): void
    {
        $this->assertQueryRejected("Column 'role_id' cannot be null", fn () => User::factory()->create([
            'role_id' => null,
        ]));
    }

    public function test_a_user_with_a_role_that_does_not_exist_is_rejected(): void
    {
        $this->assertQueryRejected('a foreign key constraint fails', fn () => User::factory()->create([
            'role_id' => 999,
        ]));
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $this->assertQueryRejected("Data truncated for column 'status'", fn () => User::factory()->create([
            'status' => 'banana',
        ]));
    }

    public function test_a_user_with_a_campaign_cannot_be_deleted(): void
    {
        $campaign = Campaign::factory()->create();

        $this->assertQueryRejected('Cannot delete or update a parent row', fn () => $campaign->borrower->delete());
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        User::factory()->investor()->create();

        $this->assertQueryRejected('Cannot delete or update a parent row', fn () => Role::where('name', Role::INVESTOR)->delete());
    }
}
