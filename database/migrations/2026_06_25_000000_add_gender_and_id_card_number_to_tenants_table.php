<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'gender')) {
                $table->string('gender')->nullable()->after('name');
            }
            if (! Schema::hasColumn('tenants', 'id_card_number')) {
                $table->string('id_card_number')->nullable()->after('email');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'gender')) {
                $table->dropColumn('gender');
            }
            if (Schema::hasColumn('tenants', 'id_card_number')) {
                $table->dropColumn('id_card_number');
            }
        });
    }
};
