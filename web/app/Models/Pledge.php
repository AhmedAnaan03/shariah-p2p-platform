<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['amount'])]
class Pledge extends Model
{
    /**
     * Pledges that count towards a campaign's funding: held in escrow or paid out to the
     * borrower. Refunded ones don't (PB-16, PB-17).
     */
    public const COUNTED_STATUSES = ['held', 'settled'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'held',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
}
