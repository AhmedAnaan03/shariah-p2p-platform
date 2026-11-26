<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PB-34, PB-28: say in the database itself what each schedule column means for each contract.
 * The same table holds two different things:
 *
 * - Conventional and Murabahah: instalments the borrower owes (capital + interest or markup).
 * - Mudarabah: a planned capital return schedule, not a debt. Capital is returned according to
 *   the business's results; in a genuine business loss investors bear the loss of capital in
 *   proportion to their share (except negligence or misconduct, PB-29). return_due is NULL.
 *
 * Only the column comments change (MODIFY with the same definitions), so no data moves.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE repayment_schedules
                COMMENT = 'Instalments (conventional, Murabahah) or planned capital returns (Mudarabah, not a debt) of a funded campaign',
                MODIFY instalment_no SMALLINT UNSIGNED NOT NULL
                    COMMENT 'Instalment number, or planned capital return number for Mudarabah',
                MODIFY due_date DATE NOT NULL
                    COMMENT 'Due date; for Mudarabah the planned date of the capital return, not a debt due date',
                MODIFY capital_due DECIMAL(15,2) NOT NULL
                    COMMENT 'Capital in this instalment; for Mudarabah the planned capital return, returned according to the business results',
                MODIFY return_due DECIMAL(15,2) NULL
                    COMMENT 'Interest (conventional) or markup share (Murabahah); always NULL for Mudarabah, whose profit is reported later (PB-29)',
                MODIFY status ENUM('due', 'paid', 'late') NOT NULL DEFAULT 'due'
                    COMMENT 'For Mudarabah, late is never used as a debt: a passed planned date awaits the business report (PB-29)'
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE repayment_schedules
                COMMENT = '',
                MODIFY instalment_no SMALLINT UNSIGNED NOT NULL COMMENT '',
                MODIFY due_date DATE NOT NULL COMMENT '',
                MODIFY capital_due DECIMAL(15,2) NOT NULL COMMENT '',
                MODIFY return_due DECIMAL(15,2) NULL COMMENT '',
                MODIFY status ENUM('due', 'paid', 'late') NOT NULL DEFAULT 'due' COMMENT ''
            SQL);
    }
};
