<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail for money-affecting actions: subscription activation,
 * cancellation, refunds, and credential changes. Records who did what, to which
 * record, from where — the artifact a dispute or compliance review needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable()->index(); // null = system (webhook/cron)
            $table->string('actor_role', 32)->nullable();
            $table->string('action')->index();
            $table->nullableMorphs('auditable');
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
