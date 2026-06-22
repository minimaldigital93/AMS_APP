<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Properties — the new top of the property tree: Account → Property (a building)
 * → Floor → Room (apartment) → Tenant. Each property may be assigned to a single
 * supervisor, who then only sees that property's floors/rooms/tenants.
 *
 * account_id / supervisor_id are plain indexed columns (no FK constraint) to
 * match the existing multi-tenant column convention (see account_id elsewhere).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('supervisor_id')->nullable();
            $table->string('name');
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('supervisor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
