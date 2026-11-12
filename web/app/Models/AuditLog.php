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
     * Write one audit entry: who (the signed-in user unless $actor is given), did what
     * ($action, e.g. 'user.verified'), to which record ($subject), with any details, and from
     * which IP address. PB-41 builds the audit screens on these rows.
     *
     *   AuditLog::record('user.rejected', $user, ['reason' => '...']);
     *
     * @param  array<string, mixed>  $details
     */
    public static function record(string $action, ?Model $subject = null, array $details = [], ?User $actor = null): self
    {
        $entry = new self([
            'actor_id' => ($actor ?? auth()->user())?->id,
            'action' => $action,
            'details' => $details === [] ? null : $details,
            'ip_address' => request()?->ip(),
        ]);

        if ($subject !== null) {
            // Fills subject_type (the model's class) and subject_id.
            $entry->subject()->associate($subject);
        }

        $entry->save();

        return $entry;
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
