<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-14: a borrower can attach their MMA Credit Information System report to a financing
 * request. It is stored like a KYC document (same private, encrypted disk and the same
 * protected route), so it is a kyc_documents row with:
 *   - document_type 'credit_report' (a new enum value), and
 *   - campaign_id: the request it belongs to.
 *
 * A CHECK keeps the two together: a credit report always belongs to a request, and an
 * identity document (national ID, business registration, ...) never does.
 */
return new class extends Migration
{
    private const TYPES_BEFORE = "'national_id','business_registration','financial_statement','proof_of_address','other'";

    public function up(): void
    {
        DB::statement('ALTER TABLE kyc_documents MODIFY document_type ENUM('.self::TYPES_BEFORE.",'credit_report') NOT NULL");

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->foreignId('campaign_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE kyc_documents ADD CONSTRAINT kyc_documents_campaign_link_check CHECK (
                (document_type = 'credit_report' AND campaign_id IS NOT NULL)
                OR (document_type <> 'credit_report' AND campaign_id IS NULL)
            )
            SQL);
    }

    /**
     * Rolling back with credit reports stored fails (MySQL refuses to drop an enum value that
     * rows still use), which is the safe outcome: it never deletes a borrower's document.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE kyc_documents DROP CHECK kyc_documents_campaign_link_check');

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
        });

        DB::statement('ALTER TABLE kyc_documents MODIFY document_type ENUM('.self::TYPES_BEFORE.') NOT NULL');
    }
};
