<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PB-53: identity documents become fixed slots, one current document per type per user.
 *
 *   - replaced_at: set when a newer upload replaces a document. The old row and its encrypted
 *     file are kept (nothing is deleted), but only the current one counts or is shown.
 *   - current_slot: a generated column that holds the document type while the row is the
 *     current identity document, and NULL otherwise. A unique index on (user_id, current_slot)
 *     lets MySQL itself refuse a second current document in the same slot. NULLs never clash,
 *     so replaced rows and credit reports (which belong to a request) are not limited.
 *
 * Existing data: before slots, a user could upload the same type several times. For each user
 * and type the newest upload becomes the current document and the older ones are marked
 * replaced. Rolling back drops the two columns, so every row is as it was before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->timestamp('replaced_at')->nullable()->after('rejection_reason');
        });

        // Older uploads of a type the user uploaded again: replaced when the newer one arrived.
        DB::statement(<<<'SQL'
            UPDATE kyc_documents older
            JOIN kyc_documents newer
              ON newer.user_id = older.user_id
             AND newer.document_type = older.document_type
             AND newer.campaign_id IS NULL
             AND newer.id = (
                 SELECT MIN(n.id) FROM (SELECT id, user_id, document_type, campaign_id FROM kyc_documents) n
                 WHERE n.user_id = older.user_id AND n.document_type = older.document_type
                   AND n.campaign_id IS NULL AND n.id > older.id
             )
            SET older.replaced_at = COALESCE(newer.created_at, NOW())
            WHERE older.campaign_id IS NULL
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE kyc_documents
            ADD COLUMN current_slot VARCHAR(30)
                GENERATED ALWAYS AS (IF(replaced_at IS NULL AND campaign_id IS NULL, document_type, NULL)) STORED
                AFTER replaced_at
            SQL);

        Schema::table('kyc_documents', function (Blueprint $table) {
            $table->unique(['user_id', 'current_slot'], 'kyc_documents_one_per_slot');
        });
    }

    public function down(): void
    {
        // MySQL may have dropped the foreign key's own index on user_id when the unique index
        // (which starts with user_id) arrived. The key needs an index, so put it back first
        // if it is gone.
        $needsIndex = ! Schema::hasIndex('kyc_documents', 'kyc_documents_user_id_foreign');

        Schema::table('kyc_documents', function (Blueprint $table) use ($needsIndex) {
            if ($needsIndex) {
                $table->index('user_id', 'kyc_documents_user_id_foreign');
            }
            $table->dropUnique('kyc_documents_one_per_slot');
            $table->dropColumn(['current_slot', 'replaced_at']);
        });
    }
};
