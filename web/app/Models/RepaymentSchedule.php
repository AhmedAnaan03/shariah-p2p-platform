<?php

namespace App\Models;

use App\Exceptions\ContractRuleViolation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One instalment of a funded campaign.
 */
#[Fillable(['instalment_no', 'due_date', 'capital_due', 'return_due'])]
class RepaymentSchedule extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'due',
    ];

    protected function casts(): array
    {
        return [
            'instalment_no' => 'integer',
            'due_date' => 'date',
            'capital_due' => 'decimal:2',
            'return_due' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Model events: "saving" runs before every insert and update made through Eloquent.
     */
    protected static function booted(): void
    {
        static::saving(function (RepaymentSchedule $schedule) {
            $schedule->ensureReturnMatchesContract();
        });
    }

    /**
     * A Mudarabah instalment must not carry a predetermined return: investors' returns come
     * from profit the business reports later (PB-29), so a fixed figure here would be a
     * guaranteed return. Conventional and Murabahah instalments always state their interest or
     * markup share. MySQL can't check this, because the contract type lives in another table.
     *
     * @throws ContractRuleViolation
     */
    public function ensureReturnMatchesContract(): void
    {
        // Read the contract type fresh from the database rather than a cached relation.
        $contractType = $this->campaign()->value('contract_type');

        if ($contractType === null) {
            return; // No such campaign: the campaign_id foreign key rejects the row instead.
        }

        if ($contractType === Campaign::CONTRACT_MUDARABAH && $this->return_due !== null) {
            throw new ContractRuleViolation(
                'A Mudarabah instalment cannot have a predetermined return_due; '
                .'returns depend on the profit the business reports.'
            );
        }

        if ($contractType !== Campaign::CONTRACT_MUDARABAH && $this->return_due === null) {
            throw new ContractRuleViolation("A {$contractType} instalment must state its return_due.");
        }
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Repayment::class);
    }
}
