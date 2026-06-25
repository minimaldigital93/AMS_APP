<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The catalog of selectable UI themes.
     *
     * This is platform reference data (like plans) — it is intentionally NOT
     * account-scoped. The same five premium themes are offered to every user;
     * a user's chosen theme lives on `users.theme` (a slug into this table).
     */
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();          // e.g. executive-black
            $table->string('name');                    // e.g. Executive Black
            $table->string('description')->nullable();
            $table->enum('mode', ['light', 'dark'])->default('light');
            $table->json('tokens');                    // full CSS-variable design-token map
            $table->json('preview');                   // swatch colors for the thumbnail
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};
