<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Durable accounting for every Bakong Open API request — the ones that were
 * made AND the ones that were refused before they left.
 *
 * Why a table rather than the cache counters the KHQRPay integration uses:
 *
 *  1. THE CEILING MUST SURVIVE A CACHE FLUSH. The KHQR daily budget lives in
 *     Cache, so `php artisan cache:clear` in production hands the day a fresh
 *     allowance — which is exactly the command someone reaches for when the
 *     gateway is misbehaving. Counting rows here instead means the ceiling is
 *     a fact about the day, not about the cache store's current contents.
 *
 *  2. "WHY DID AMS_APP USE 73 BAKONG REQUESTS TODAY?" has to have an answer
 *     with rows in it. A per-day total cannot tell you that 60 of them were one
 *     abandoned checkout; a per-call log can, and that is the first question of
 *     any quota investigation.
 *
 * Refused attempts are recorded too (allowed = false + blocked_reason), because
 * the interesting finding is usually the call that was ABOUT to happen. They
 * never count toward the ceiling.
 *
 * NOTHING SECRET IS STORED HERE: no token, no Authorization header, no request
 * body. Only which endpoint, why, for which payment, and what came back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakong_api_calls', function (Blueprint $table) {
            $table->id();

            // The calendar day Bakong meters against. Stored explicitly rather
            // than derived from created_at at query time so the ceiling's count
            // is a plain indexed lookup and never depends on the database's
            // timezone handling of a timestamp.
            $table->date('called_on');

            // Which documented endpoint, e.g. '/v1/check_transaction_by_md5'.
            $table->string('endpoint', 64);

            // WHY this request exists. Required — a request nobody can account
            // for is how the last quota leak went unnoticed for two months, so
            // the reason is a parameter at the call site, not a guess here.
            $table->string('reason', 32);

            // Which allowance it was charged to. One token today ('platform'),
            // but a landlord who registers their own integrator token later
            // gets their own budget, and this is where that split lives.
            $table->string('target', 16)->default('platform');

            // The payment this was about, when it was about one. No FK
            // constraint on purpose: this is an append-only audit trail and it
            // must outlive whatever it describes — a purged account must not
            // take the record of its Bakong spend with it.
            $table->unsignedBigInteger('khqr_payment_id')->nullable();

            // Did the request actually leave this server? Only true rows count
            // against the daily ceiling.
            $table->boolean('allowed');

            // For a refusal: which gate stopped it (khqr_disabled, cooldown,
            // budget, ...). Null when the request was made.
            $table->string('blocked_reason', 32)->nullable();

            // What came back. Bakong's envelope carries BOTH a responseCode
            // (0 success / 1 fail) and a narrower errorCode, and they answer
            // different questions — a 200 with responseCode 1 and errorCode 1
            // is an honest "transaction not found", while errorCode 6 on the
            // same HTTP status is our token being rejected. Keeping them apart
            // is what lets a refusal be told from a verdict later.
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->integer('response_code')->nullable();
            $table->integer('error_code')->nullable();

            // How the application read the answer: ok / paid / unpaid /
            // refused / error. The interpretation, not the raw response.
            $table->string('outcome', 16)->nullable();

            // Round-trip time, for spotting a gateway going slow before it
            // starts timing out.
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamps();

            // The ceiling's own query: today's allowed calls for one target.
            $table->index(['called_on', 'target', 'allowed']);
            // The investigation's query: today's calls broken down by reason.
            $table->index(['called_on', 'reason']);
            // "What did this one checkout cost?"
            $table->index('khqr_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakong_api_calls');
    }
};
