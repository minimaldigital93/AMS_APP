<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic multi-file attachments, shared by business-expense receipts and
 * tenant ID documents. Replaces the old single-scalar-column approach
 * (business_expenses.attachment, tenants.document_path) so a record can carry
 * more than one file. `kind` distinguishes the two feature areas without
 * needing two near-identical tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->nullable()->index();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('kind')->default('general');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index(['account_id', 'attachable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
