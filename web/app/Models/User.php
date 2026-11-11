<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// role_id and status are deliberately NOT fillable: a registration form must not be able to
// make someone an admin or mark them verified by adding a field to the request.
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * In-memory default, matching the column default, so a new User reports its status
     * before it is reloaded from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending_verification',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Whether the user has the given role, e.g. $user->hasRole(Role::INVESTOR).
     */
    public function hasRole(string $role): bool
    {
        return $this->role?->name === $role;
    }

    /**
     * Whether an administrator still has to verify this account.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending_verification';
    }

    /**
     * The name of the route for this user's portal: borrower.portal, investor.portal or
     * admin.portal. Every role has exactly one portal.
     */
    public function portalRoute(): string
    {
        return $this->role->name.'.portal';
    }

    public function borrowerProfile(): HasOne
    {
        return $this->hasOne(BorrowerProfile::class);
    }

    public function investorProfile(): HasOne
    {
        return $this->hasOne(InvestorProfile::class);
    }

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(KycDocument::class);
    }

    /**
     * Campaigns this user raised as a borrower.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'borrower_id');
    }

    /**
     * Pledges this user made as an investor.
     */
    public function pledges(): HasMany
    {
        return $this->hasMany(Pledge::class, 'investor_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(WalletAccount::class);
    }

    /**
     * Audit entries for actions this user performed.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
