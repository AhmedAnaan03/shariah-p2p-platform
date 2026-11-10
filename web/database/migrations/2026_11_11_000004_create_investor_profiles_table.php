<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->string('phone', 20);
            $table->text('national_id_no'); // encrypted cast, see borrower_profiles
            $table->text('address');
            // NULL until the investor accepts the capital-at-risk acknowledgement (PB-10).
            $table->timestamp('risk_acknowledged_at')->nullable();
            // Shariah-only marketplace toggle (PB-31).
            $table->boolean('shariah_only')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_profiles');
    }
};
