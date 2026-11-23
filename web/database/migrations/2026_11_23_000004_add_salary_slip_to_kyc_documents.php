<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PB-58: a "salary slip" document type, the slot individual borrowers fill so an administrator
 * can check their declared monthly income (one PDF can contain several months).
 *
 * Rolling back with salary slips stored fails (MySQL refuses to drop an enum value rows still
 * use), which is the safe outcome: it never deletes a borrower's document.
 */
return new class extends Migration
{
    private const TYPES_BEFORE = "'national_id','business_registration','financial_statement','proof_of_address','other','credit_report'";

    public function up(): void
    {
        DB::statement('ALTER TABLE kyc_documents MODIFY document_type ENUM('.self::TYPES_BEFORE.",'salary_slip') NOT NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kyc_documents MODIFY document_type ENUM('.self::TYPES_BEFORE.') NOT NULL');
    }
};
