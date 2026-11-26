<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PB-29: the optional supporting document a Mudarabah business attaches to its period report
 * (accounts, a sales summary). Stored like every other document, encrypted on the private disk
 * (KycDocumentStore), and like a credit report it always belongs to a campaign: the CHECK that
 * keeps credit reports linked to their request now covers business reports too.
 *
 * Rolling back with business reports stored fails (MySQL refuses to drop an enum value rows
 * still use), which is the safe outcome: it never deletes a document.
 */
return new class extends Migration
{
    private const TYPES_BEFORE = "'national_id','business_registration','financial_statement','proof_of_address','other','credit_report','salary_slip'";

    public function up(): void
    {
        DB::statement('ALTER TABLE kyc_documents DROP CHECK kyc_documents_campaign_link_check');
        DB::statement('ALTER TABLE kyc_documents MODIFY document_type ENUM('.self::TYPES_BEFORE.",'business_report') NOT NULL");
        DB::statement(<<<'SQL'
            ALTER TABLE kyc_documents ADD CONSTRAINT kyc_documents_campaign_link_check CHECK (
                (document_type IN ('credit_report', 'business_report') AND campaign_id IS NOT NULL)
                OR (document_type NOT IN ('credit_report', 'business_report') AND campaign_id IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kyc_documents DROP CHECK kyc_documents_campaign_link_check');
        DB::statement('ALTER TABLE kyc_documents MODIFY document_type ENUM('.self::TYPES_BEFORE.') NOT NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE kyc_documents ADD CONSTRAINT kyc_documents_campaign_link_check CHECK (
                (document_type = 'credit_report' AND campaign_id IS NOT NULL)
                OR (document_type <> 'credit_report' AND campaign_id IS NULL)
            )
            SQL);
    }
};
