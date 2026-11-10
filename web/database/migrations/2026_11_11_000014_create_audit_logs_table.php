<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who did what to which record. Append-only, so there is no updated_at.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // NULL when a scheduled job acted, not a person.
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 100); // e.g. kyc.approved, campaign.suspended
            $table->nullableMorphs('subject'); // subject_type + subject_id, e.g. a Campaign
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable(); // 45 fits an IPv6 address
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
