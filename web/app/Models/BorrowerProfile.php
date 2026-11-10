<?php

namespace App\Models;

use Database\Factories\BorrowerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'borrower_type', 'phone', 'national_id_no', 'address', 'business_name',
    'business_registration_no', 'sector', 'years_operating', 'declared_annual_income',
])]
// Kept out of arrays/JSON (and so out of React page props) unless a page asks for it.
#[Hidden(['national_id_no'])]
class BorrowerProfile extends Model
{
    /** @use HasFactory<BorrowerProfileFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            // Encrypted with APP_KEY on save, decrypted on read: the database only holds ciphertext.
            'national_id_no' => 'encrypted',
            'years_operating' => 'integer',
            'declared_annual_income' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
