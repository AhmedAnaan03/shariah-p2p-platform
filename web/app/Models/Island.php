<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An inhabited island, reference data for addresses (PB-53). Belongs to one atoll; the same
 * name can appear in several atolls.
 */
class Island extends Model
{
    public function atoll(): BelongsTo
    {
        return $this->belongsTo(Atoll::class);
    }

    /**
     * The id of one island by atoll and island name, e.g. Island::idFor('Malé City', 'Malé'),
     * for factories and seeders.
     */
    public static function idFor(string $atoll, string $island): int
    {
        return self::where('name', $island)->whereHas('atoll', fn ($query) => $query->where('name', $atoll))->valueOrFail('id');
    }
}
