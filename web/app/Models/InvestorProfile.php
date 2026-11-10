<?php

namespace App\Models;

use Database\Factories\InvestorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['phone', 'national_id_no', 'address', 'shariah_only'])]
#[Hidden(['national_id_no'])]
class InvestorProfile extends Model
{
    /** @use HasFactory<InvestorProfileFactory> */
    use HasFactory;

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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
