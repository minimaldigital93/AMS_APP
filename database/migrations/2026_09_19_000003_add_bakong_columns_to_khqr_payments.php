<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three additive columns so a payment row can describe a DIRECT Bakong
 * transaction as well as a KHQRPay one.
 *
 * Purely additive: nothing is renamed, nothing is dropped, no existing row is
 * touched, and every column is nullable. khqr_payments.provider already exists
 * and already defaults to 'khqrpay', and PaymentManager already resolves a
 * driver per row — so an old row keeps answering through KhqrPayGateway
 * forever while new rows are written as 'bakong'. That column, which predates
 * this migration, is the seam the whole migration hangs off.
 *
 * The table keeps its name. Renaming it to something provider-neutral would
 * touch every model, factory, test and foreign key in the payment domain to
 * buy nothing a comment cannot: it holds KHQR payment attempts, which is what
 * both providers mint.
 *
 * WHY qr_payload IS STORED RATHER THAN REBUILT. Under KHQRPay the gateway
 * minted the QR and handed back its own reference, so the payload was
 * disposable. Bakong has no QR endpoint at all: AMS builds the EMV string
 * itself, and check_transaction_by_md5 is keyed on "md5 hash got from QR string
 * encryption" — the md5 of that exact string. Rebuilding it later to re-derive
 * the md5 would mean every input (merchant name, city, currency, the account
 * id, even a trimmed whitespace) must still produce byte-identical output
 * months afterwards. One settings edit and a paid transaction becomes
 * permanently unverifiable. Storing the string removes the whole class of
 * problem: the payload IS the key, so it is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            // The exact EMV/KHQR string that was rendered to the payer.
            $table->text('qr_payload')->nullable()->after('qr_url');

            // md5(qr_payload) — the lookup key for check_transaction_by_md5.
            // Indexed because an inbound answer is matched back to its row by
            // this, and because it is how an operator finds a transaction they
            // are holding a QR for.
            $table->string('qr_md5', 32)->nullable()->after('qr_payload');

            // Bakong's own transaction hash, returned in the success envelope
            // alongside fromAccountId/toAccountId. Kept for audit and for
            // check_transaction_by_hash, which can answer about a settled
            // transaction after the md5's QR session is long gone.
            $table->string('provider_hash', 128)->nullable()->after('provider_ref');

            $table->index('qr_md5');
        });
    }

    public function down(): void
    {
        Schema::table('khqr_payments', function (Blueprint $table) {
            $table->dropIndex(['qr_md5']);
            $table->dropColumn(['qr_payload', 'qr_md5', 'provider_hash']);
        });
    }
};
