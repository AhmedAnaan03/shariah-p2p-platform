<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PB-35, PB-29: what each schedule status means now that instalments are paid and Mudarabah
 * periods are reported. Only the comment changes, so no data moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE repayment_schedules
                MODIFY status ENUM('due', 'paid', 'late') NOT NULL DEFAULT 'due'
                    COMMENT 'Instalments: late is set by repayments:mark-late after the due date (no fees). Mudarabah: paid once the period''s business report is approved; never late'
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE repayment_schedules
                MODIFY status ENUM('due', 'paid', 'late') NOT NULL DEFAULT 'due'
                    COMMENT 'For Mudarabah, late is never used as a debt: a passed planned date awaits the business report (PB-29)'
            SQL);
    }
};
