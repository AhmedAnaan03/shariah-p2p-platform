<?php

namespace App\Models\Concerns;

use App\Models\Island;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The structured address both profiles share (PB-53): house/building name, road (magu),
 * island (and through it the atoll) and postcode. address_legacy holds the single-line text
 * an older profile had; address_needs_review is set until the user has checked the fields.
 */
trait HasStructuredAddress
{
    /** The address fields the address form saves. */
    public const ADDRESS_FIELDS = ['address_house', 'address_road', 'island_id', 'postcode'];

    public function island(): BelongsTo
    {
        return $this->belongsTo(Island::class);
    }

    /**
     * Every field filled in and, for a migrated address, checked by the user.
     */
    public function hasCompleteAddress(): bool
    {
        foreach (self::ADDRESS_FIELDS as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return ! $this->address_needs_review;
    }

    /**
     * One line for reading: "H. Sea Breeze, Majeedhee Magu, Malé, Malé City 20026". Falls back
     * to the legacy text while the address hasn't been completed.
     */
    public function addressLine(): ?string
    {
        if (! $this->hasCompleteAddress()) {
            return $this->address_legacy;
        }

        $island = $this->island;

        return implode(', ', array_filter([
            $this->address_house,
            $this->address_road,
            $island?->name,
            trim(($island?->atoll?->name ?? '').' '.$this->postcode),
        ]));
    }
}
