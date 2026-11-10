<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label'])]
class Role extends Model
{
    // The rows the roles migration inserts.
    public const BORROWER = 'borrower';

    public const INVESTOR = 'investor';

    public const ADMIN = 'admin';

    /**
     * Look up a role's id by its name, e.g. Role::idFor(Role::INVESTOR).
     */
    public static function idFor(string $name): int
    {
        return static::where('name', $name)->valueOrFail('id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
