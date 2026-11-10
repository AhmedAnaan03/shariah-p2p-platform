<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('investor_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            // held = in the campaign's escrow wallet; refunded = deadline missed;
            // settled = paid out to the borrower when the campaign was funded.
            $table->enum('status', ['held', 'refunded', 'settled'])->default('held');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE pledges ADD CONSTRAINT pledges_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('pledges');
    }
};
