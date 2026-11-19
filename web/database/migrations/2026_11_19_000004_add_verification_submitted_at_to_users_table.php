<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-53: the onboarding wizard ends with "Submit for verification". The admin queue now lists
 * a pending account only once it has been submitted (and is complete).
 *
 * Existing pending users who already met the old "ready for review" rule (a profile, a
 * national ID document that isn't rejected, and a business registration for an SME) were in
 * the queue, so they count as submitted. Under the new rules they still have to check their
 * migrated address and fill any new document slots before they show in the queue again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('verification_submitted_at')->nullable()->after('status_reason');
        });

        DB::statement(<<<'SQL'
            UPDATE users u
            SET u.verification_submitted_at = u.updated_at
            WHERE u.status = 'pending_verification'
              AND (
                  EXISTS (SELECT 1 FROM investor_profiles ip WHERE ip.user_id = u.id)
                  OR EXISTS (SELECT 1 FROM borrower_profiles bp WHERE bp.user_id = u.id)
              )
              AND EXISTS (
                  SELECT 1 FROM kyc_documents d
                  WHERE d.user_id = u.id AND d.campaign_id IS NULL
                    AND d.document_type = 'national_id' AND d.status <> 'rejected'
              )
              AND NOT EXISTS (
                  SELECT 1 FROM borrower_profiles bp
                  WHERE bp.user_id = u.id AND bp.borrower_type = 'sme'
                    AND NOT EXISTS (
                        SELECT 1 FROM kyc_documents d
                        WHERE d.user_id = u.id AND d.campaign_id IS NULL
                          AND d.document_type = 'business_registration' AND d.status <> 'rejected'
                    )
              )
            SQL);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verification_submitted_at');
        });
    }
};
