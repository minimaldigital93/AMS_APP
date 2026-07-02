<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills the new `attachments` table from the old single-file columns,
 * then drops those columns. business_expenses.attachment carries real
 * production files and must be migrated; tenants.document_path has never had
 * an upload path wired to it (dead column) so its backfill is a safety net,
 * not an expected data mover.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mimeFromPath = function (string $path): string {
            return str_ends_with(strtolower($path), '.pdf') ? 'application/pdf' : 'image/jpeg';
        };

        DB::table('business_expenses')
            ->whereNotNull('attachment')
            ->orderBy('id')
            ->select('id', 'account_id', 'user_id', 'attachment', 'attachment_size', 'created_at', 'updated_at')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('attachments')->insert([
                        'account_id' => $row->account_id,
                        'attachable_type' => 'App\\Models\\BusinessExpense',
                        'attachable_id' => $row->id,
                        'kind' => 'business_expense',
                        'path' => $row->attachment,
                        'original_name' => basename($row->attachment),
                        'mime_type' => 'application/pdf',
                        'size' => $row->attachment_size ?? 0,
                        'sort_order' => 0,
                        'uploaded_by' => $row->user_id,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });

        DB::table('tenants')
            ->whereNotNull('document_path')
            ->orderBy('id')
            ->select('id', 'account_id', 'document_path', 'document_size', 'created_at', 'updated_at')
            ->chunk(500, function ($rows) use ($mimeFromPath) {
                foreach ($rows as $row) {
                    DB::table('attachments')->insert([
                        'account_id' => $row->account_id,
                        'attachable_type' => 'App\\Models\\Tenants',
                        'attachable_id' => $row->id,
                        'kind' => 'tenant_document',
                        'path' => $row->document_path,
                        'original_name' => basename($row->document_path),
                        'mime_type' => $mimeFromPath($row->document_path),
                        'size' => $row->document_size ?? 0,
                        'sort_order' => 0,
                        'uploaded_by' => null,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            });

        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropColumn(['attachment', 'attachment_size']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['document_path', 'document_size']);
        });
    }

    public function down(): void
    {
        Schema::table('business_expenses', function (Blueprint $table) {
            $table->string('attachment')->nullable()->after('note');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('photo_path');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_path');
        });
    }
};
