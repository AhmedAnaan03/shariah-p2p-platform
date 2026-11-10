<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A wallet belongs either to a user or, as its escrow, to a campaign.
     */
    public function up(): void
    {
        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['user', 'escrow']);
            // unique() allows many NULLs in MySQL, so each user and each campaign has at most
            // one wallet while the other owner column stays NULL.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE wallet_accounts ADD CONSTRAINT wallet_accounts_owner_check CHECK (
                (type = 'user' AND user_id IS NOT NULL AND campaign_id IS NULL)
                OR (type = 'escrow' AND campaign_id IS NOT NULL AND user_id IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_accounts');
    }
};
