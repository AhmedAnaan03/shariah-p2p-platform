<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
            'verification_submitted_at' => 'datetime',
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
     * Whether the user may still change their profile and upload documents. Allowed while the
     * account waits for review or after a rejection (to fix it). Locked once verified, so what
     * the administrator checked can't be changed afterwards, and while suspended.
     */
    public function canEditVerificationDetails(): bool
    {
        return in_array($this->status, ['pending_verification', 'rejected'], true);
    }

    /**
     * Borrowers and investors still waiting for verification, with everything the
     * VerificationChecklist needs loaded. Usage: User::awaitingVerification()->get().
     */
    #[Scope]
    protected function awaitingVerification(Builder $query): void
    {
        $query->where('status', 'pending_verification')
            ->whereHas('role', fn (Builder $roles) => $roles->whereIn('name', [Role::BORROWER, Role::INVESTOR]))
            ->with(['role', 'borrowerProfile', 'investorProfile', 'kycDocuments'])
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * The last step of the onboarding wizard (PB-53): send the account for verification. After
     * a rejection this also puts it back in the review queue and clears the old reason; editing
     * a rejected account's details no longer does that on its own, the user submits again once
     * they have fixed everything.
     */
    public function submitForVerification(): void
    {
        // status, status_reason and verification_submitted_at are not mass-assignable.
        if ($this->status === 'rejected') {
            $this->status = 'pending_verification';
            $this->status_reason = null;
        }

        $this->verification_submitted_at = now();
        $this->save();
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

    /**
     * The user's identity documents (national ID, business registration, ...), the ones
     * account verification is about. A credit report attached to a financing request (PB-14)
     * is also a kyc_documents row, but it belongs to that request (Campaign::creditReport) and
     * is decided with the request, so it is left out here, and so is any upload a newer one has
     * replaced. Every verification query (the checklist, the admin review page, the decision)
     * goes through this relation.
     */
    public function kycDocuments(): HasMany
    {
        // Only the current document in each slot (PB-53): a replaced upload is kept, with its
        // file, but no longer counts or shows.
        return $this->hasMany(KycDocument::class)->whereNull('campaign_id')->whereNull('replaced_at');
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
