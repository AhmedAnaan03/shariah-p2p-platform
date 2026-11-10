<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrower_profiles', function (Blueprint $table) {
            $table->id();
            // unique(): a user has at most one borrower profile.
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('borrower_type', ['individual', 'sme']);
            $table->string('phone', 20);
            // text, not varchar: the model's `encrypted` cast stores ciphertext that is much
            // longer than the ID itself.
            $table->text('national_id_no');
            $table->text('address');
            $table->string('business_name')->nullable();
            $table->string('business_registration_no', 50)->nullable();
            $table->string('sector', 100)->nullable();
            $table->unsignedSmallInteger('years_operating')->nullable();
            $table->decimal('declared_annual_income', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_profiles');
    }
};
