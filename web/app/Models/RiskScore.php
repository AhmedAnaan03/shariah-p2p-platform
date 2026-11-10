<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'input_features', 'probability_of_default', 'grade', 'top_factors',
    'model_version', 'error_message', 'scored_at',
])]
class RiskScore extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            // JSON columns come back as PHP arrays.
            'input_features' => 'array',
            'top_factors' => 'array',
            'probability_of_default' => 'decimal:4',
            'attempts' => 'integer',
            'scored_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
