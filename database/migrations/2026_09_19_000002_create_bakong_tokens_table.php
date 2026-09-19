<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Bakong Open API access token.
 *
 * Bakong issues a JWT to an INTEGRATOR — an (email, organization, project)
 * triple — through a two-step, human-in-the-loop flow: POST /v1/request_token
 * emails a 20-character code, POST /v1/verify exchanges that code for the
 * token, and POST /v1/renew_token later returns a fresh one for the same email.
 *
 * WHY THIS IS A TABLE AND NOT AN ENVIRONMENT VARIABLE:
 *
 *  - The token is obtained at RUNTIME from an emailed code. There is no moment
 *    at deploy time when its value is known, so a .env entry would always be
 *    written by hand after the fact.
 *  - It EXPIRES (NBC's sample tokens carry an exp roughly 93 days out) and must
 *    be renewed without a deployment.
 *  - A rotated secret in .env outlives its rotation — in shell history, in the
 *    config cache, in whatever backed the file up. Encrypted at rest in a row
 *    that can simply be replaced is the smaller blast radius.
 *
 * expires_at is read out of the JWT's own `exp` claim locally. Asking the API
 * when a token expires would spend a metered request to learn something the
 * token already states in plain sight.
 *
 * `token` is cast `encrypted` on the model, so the column holds ciphertext and
 * a database dump does not hand anyone a working credential.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakong_tokens', function (Blueprint $table) {
            $table->id();

            // The integrator identity the token was issued for. Unique because
            // renew_token is keyed on the email alone — two rows for one email
            // would make "which token is current?" ambiguous at the exact
            // moment (an expiring credential) when it must not be.
            $table->string('email');
            $table->string('organization')->nullable();
            $table->string('project')->nullable();

            // The JWT. Encrypted at rest; nullable because the row is created
            // by `bakong:token request` BEFORE the emailed code has been
            // exchanged, so that the pending registration is visible rather
            // than living only in someone's memory.
            $table->text('token')->nullable();

            // Decoded from the JWT's exp claim, never from an API call.
            $table->timestamp('expires_at')->nullable();

            // When the emailed code was successfully exchanged. Null means the
            // registration is still pending a code.
            $table->timestamp('verified_at')->nullable();

            // Last time this token was renewed, for operator visibility.
            $table->timestamp('renewed_at')->nullable();

            $table->timestamps();

            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakong_tokens');
    }
};
