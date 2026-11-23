<?php

namespace App\Models;

use App\Models\Concerns\HasStructuredAddress;
use Database\Factories\InvestorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// address_legacy and address_needs_review are set in code, never from a form (PB-53).
#[Fillable(['phone', 'national_id_no', 'address_house', 'address_road', 'island_id', 'postcode', 'shariah_only'])]
#[Hidden(['national_id_no'])]
class InvestorProfile extends Model
{
    /** @use HasFactory<InvestorProfileFactory> */
    use HasFactory, HasStructuredAddress;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'shariah_only' => false,
    ];

    protected function casts(): array
    {
        return [
            'national_id_no' => 'encrypted',
            'risk_acknowledged_at' => 'datetime',
            'shariah_only' => 'boolean',
            'address_needs_review' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
