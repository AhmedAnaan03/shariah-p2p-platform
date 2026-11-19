<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An administrative atoll (or Malé City), reference data for addresses (PB-53). The rows come
 * from the create_atolls_and_islands migration and are never edited by the app.
 */
class Atoll extends Model
{
    public function islands(): HasMany
    {
        return $this->hasMany(Island::class)->orderBy('name');
    }

    /**
     * "Haa Alif (HA)", how the dropdown shows it. Malé City has no separate code to show.
     */
    public function label(): string
    {
        return $this->code === 'Malé' ? $this->name : "{$this->name} ({$this->code})";
    }

    /**
     * Every atoll north to south with its islands, for the address step's two dropdowns:
     * [{ value, label, islands: [{ value, label }] }]. About 190 islands, so the whole list
     * goes to the page once and the island dropdown filters it without another request.
     *
     * @return list<array{value: string, label: string, islands: list<array{value: string, label: string}>}>
     */
    public static function options(): array
    {
        return self::with('islands')->orderBy('sort_order')->get()
            ->map(fn (Atoll $atoll) => [
                'value' => (string) $atoll->id,
                'label' => $atoll->label(),
                'islands' => $atoll->islands->map(fn (Island $island) => ['value' => (string) $island->id, 'label' => $island->name])->all(),
            ])
            ->all();
    }
}
