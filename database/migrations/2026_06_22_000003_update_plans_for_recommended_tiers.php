<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recommended plan tiers carry four caps (Properties / Rooms / Floors / Staff)
 * and both a monthly and a yearly price.
 *
 *  - price_usd stays the MONTHLY price; price_yearly_usd is the discounted annual price.
 *  - max_apartments is renamed to max_rooms (the new user-facing term).
 *  - max_properties / max_staff are new caps (null = unlimited).
 *  - max_floors is kept but seeded null everywhere (floors are unlimited on every tier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_yearly_usd', 8, 2)->nullable()->after('price_usd');
            $table->unsignedInteger('max_properties')->nullable()->after('price_yearly_usd');
            $table->unsignedInteger('max_staff')->nullable()->after('max_floors');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->renameColumn('max_apartments', 'max_rooms');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->renameColumn('max_rooms', 'max_apartments');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_yearly_usd', 'max_properties', 'max_staff']);
        });
    }
};
