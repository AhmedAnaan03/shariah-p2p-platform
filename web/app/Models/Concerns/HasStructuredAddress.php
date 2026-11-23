<?php

namespace App\Models\Concerns;

use App\Models\Island;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The structured address both profiles share (PB-53): house/building name, road (magu),
 * island (and through it the atoll) and postcode.
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
     * Every address field filled in.
     */
    public function hasCompleteAddress(): bool
    {
        foreach (self::ADDRESS_FIELDS as $field) {
            if (blank($this->{$field})) {
                return false;
            }
        }

        return true;
    }

    /**
     * One line for reading: "H. Sea Breeze, Majeedhee Magu, Malé, Malé City 20026", or null
     * while the address hasn't been completed.
     */
    public function addressLine(): ?string
    {
        if (! $this->hasCompleteAddress()) {
            return null;
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
