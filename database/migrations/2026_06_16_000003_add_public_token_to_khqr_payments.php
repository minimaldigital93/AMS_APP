<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Unguessable URL id for the public checkout/status pages.
 *
 * `transaction_id` is a provider correlation id of the shape
 * SUB-{id}-{YmdHis}-{rand 100..999} — only ~900 random values over a known
 * second, and the subscription status endpoint is public + triggers a live
 * verify() call. That made transaction ids enumerable. URLs now use this
 * 40-char random token instead; transaction_id stays for provider matching only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->after('transaction_id');
        });

        // Backfill existing rows so the unique index can be added cleanly.
        DB::table('khqr_payments')->whereNull('public_token')->orderBy('id')
            ->each(function ($row) {
                DB::table('khqr_payments')->where('id', $row->id)
                    ->update(['public_token' => Str::random(40)]);
            });

        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->unique('public_token');
        });
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
