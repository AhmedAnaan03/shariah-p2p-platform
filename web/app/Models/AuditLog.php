<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['actor_id', 'action', 'subject_type', 'subject_id', 'details', 'ip_address'])]
class AuditLog extends Model
{
    // Append-only table: it has created_at but no updated_at column.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    /**
     * The user who acted; null when a scheduled job acted.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * The record acted on (any model), via subject_type + subject_id.
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
