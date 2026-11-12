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

    public const TYPES = [
        'individual' => 'Individual',
        'sme' => 'Business (SME)',
    ];

    /**
     * The sectors an SME can choose (stored as the key). A fixed list rather than free text,
     * so the risk model (Sprint 3) gets clean categories instead of spelling variants.
     */
    public const SECTORS = [
        'agriculture' => 'Agriculture',
        'construction' => 'Construction',
        'education' => 'Education',
        'fisheries' => 'Fisheries',
        'food_and_beverage' => 'Food and beverage',
        'health' => 'Health',
        'manufacturing' => 'Manufacturing',
        'retail' => 'Retail and trade',
        'technology' => 'Technology',
        'tourism' => 'Tourism and hospitality',
        'transport' => 'Transport and logistics',
        'other_services' => 'Other services',
    ];

    /**
     * Fields that only apply to an SME. They are NULL for an individual.
     */
    public const BUSINESS_FIELDS = ['business_name', 'business_registration_no', 'sector', 'years_operating'];

    public function isSme(): bool
    {
        return $this->borrower_type === 'sme';
    }

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
