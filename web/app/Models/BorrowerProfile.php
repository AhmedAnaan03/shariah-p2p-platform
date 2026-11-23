<?php

namespace App\Models;

use App\Models\Concerns\HasStructuredAddress;
use Database\Factories\BorrowerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'borrower_type', 'phone', 'national_id_no', 'address_house', 'address_road', 'island_id', 'postcode',
    'business_name', 'business_registration_no', 'sector', 'years_operating',
    // The financial profile (PB-58).
    'monthly_income', 'monthly_debt_repayments', 'employment_years', 'housing',
    'open_credit_lines', 'late_payments_2y', 'public_records',
])]
// Kept out of arrays/JSON (and so out of React page props) unless a page asks for it.
#[Hidden(['national_id_no'])]
class BorrowerProfile extends Model
{
    /** @use HasFactory<BorrowerProfileFactory> */
    use HasFactory, HasStructuredAddress;

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

    /**
     * The financial profile (PB-58): what the risk model needs, declared once in the onboarding
     * wizard's finance step, checked by an administrator, and copied into each financing
     * request when it is submitted (CampaignFinancials).
     */
    public const FINANCIAL_FIELDS = [
        'monthly_income', 'monthly_debt_repayments', 'employment_years', 'housing',
        'open_credit_lines', 'late_payments_2y', 'public_records',
    ];

    public function hasFinancialProfile(): bool
    {
        foreach (self::FINANCIAL_FIELDS as $field) {
            if ($this->{$field} === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * The financial profile as stored, for a request's snapshot.
     *
     * @return array<string, mixed>
     */
    public function financialProfile(): array
    {
        return $this->only(self::FINANCIAL_FIELDS);
    }

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
            'monthly_income' => 'decimal:2',
            'monthly_debt_repayments' => 'decimal:2',
            'employment_years' => 'integer',
            'open_credit_lines' => 'integer',
            'late_payments_2y' => 'integer',
            'public_records' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
