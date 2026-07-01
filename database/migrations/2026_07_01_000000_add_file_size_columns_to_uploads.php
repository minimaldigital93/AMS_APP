<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Store the byte size of each uploaded file alongside its path so per-account
 * disk usage can be SUM()'d in SQL (see SuperAdmin\AccountsController) instead
 * of stat-ing every file on each page load. Sizes are kept in sync going
 * forward by the TracksFileSizes model hook; this migration backfills the
 * existing rows from the files currently on the `public` disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('photo_size')->nullable()->after('photo_path');
            $table->unsignedBigInteger('document_size')->nullable()->after('document_path');
        });

        Schema::table('business_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment');
        });

        $disk = Storage::disk('public');
        $sizeOf = function (?string $path) use ($disk): ?int {
            if (! filled($path)) {
                return null;
            }

            $full = $disk->path($path);

            return is_file($full) ? filesize($full) : null;
        };

        DB::table('tenants')
            ->where(function ($q) {
                $q->whereNotNull('photo_path')->orWhereNotNull('document_path');
            })
            ->orderBy('id')
            ->select('id', 'photo_path', 'document_path')
            ->chunk(500, function ($rows) use ($sizeOf) {
                foreach ($rows as $row) {
                    DB::table('tenants')->where('id', $row->id)->update([
                        'photo_size' => $sizeOf($row->photo_path),
                        'document_size' => $sizeOf($row->document_path),
                    ]);
                }
            });

        DB::table('business_expenses')
            ->whereNotNull('attachment')
            ->orderBy('id')
            ->select('id', 'attachment')
            ->chunk(500, function ($rows) use ($sizeOf) {
                foreach ($rows as $row) {
                    DB::table('business_expenses')->where('id', $row->id)->update([
                        'attachment_size' => $sizeOf($row->attachment),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['photo_size', 'document_size']);
        });

        Schema::table('business_expenses', function (Blueprint $table) {
            $table->dropColumn('attachment_size');
        });
    }
};
