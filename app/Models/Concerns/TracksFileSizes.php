<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps a byte-size column in sync with the file referenced by a path column.
 *
 * A model declares its path => size column pairs in fileSizeColumns(). Whenever
 * a path column changes (upload, replace, or clear), the matching size column is
 * recomputed from the file's on-disk size on the `public` disk. This lets total
 * upload usage be SUM()'d in SQL instead of stat-ing every file at read time
 * (see SuperAdmin\AccountsController's disk-usage column, which scales with the
 * number of accounts rather than the number of files).
 *
 * Because the size is always re-derived from the current path on save, the two
 * columns can never drift — no matter which controller performed the upload, or
 * whether the row was created, its file replaced, or its path cleared. Deleting
 * the row drops its size from the SUM automatically.
 */
trait TracksFileSizes
{
    /**
     * Map of path column => size column to keep in sync.
     *
     * @return array<string, string>
     */
    abstract protected function fileSizeColumns(): array;

    public static function bootTracksFileSizes(): void
    {
        static::saving(function (Model $model) {
            /** @var Model&TracksFileSizes $model */
            $disk = Storage::disk('public');

            foreach ($model->fileSizeColumns() as $pathColumn => $sizeColumn) {
                if (! $model->isDirty($pathColumn)) {
                    continue;
                }

                $path = $model->getAttribute($pathColumn);

                if (! filled($path)) {
                    $model->setAttribute($sizeColumn, null);

                    continue;
                }

                $full = $disk->path($path);
                $model->setAttribute($sizeColumn, is_file($full) ? filesize($full) : null);
            }
        });
    }
}
