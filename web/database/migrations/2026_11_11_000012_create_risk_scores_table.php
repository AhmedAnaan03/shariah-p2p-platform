<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every scoring attempt for a campaign. The latest row is the current score.
     */
    public function up(): void
    {
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'scored', 'failed'])->default('pending');
            // The exact payload sent to the Flask service, so every score can be traced.
            $table->json('input_features');
            $table->decimal('probability_of_default', 5, 4)->nullable();
            $table->enum('grade', ['A', 'B', 'C', 'D', 'E'])->nullable();
            $table->json('top_factors')->nullable();
            $table->string('model_version', 50)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_message')->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE risk_scores ADD CONSTRAINT risk_scores_probability_check
                CHECK (probability_of_default IS NULL OR probability_of_default BETWEEN 0 AND 1)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
    }
};
