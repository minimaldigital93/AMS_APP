<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Store/delete-from-disk logic for polymorphic multi-file attachments,
 * shared by Business Expense receipts and Tenant ID documents so the
 * Storage:: calls live in one place instead of duplicated per controller.
 *
 * A filesystem and a database cannot share one transaction, so the two halves
 * are ordered to fail safe:
 *  - store:  files are written inside a DB transaction; if any file or row
 *    fails, the rows roll back AND every file already written in this call is
 *    removed, so a half-saved upload leaves nothing behind.
 *  - delete: row first, then file. The reverse order can leave a row pointing
 *    at a file that is gone (a visible 404 on someone's record); this order can
 *    only leave an orphan file, which is logged and costs nothing but space.
 */
class AttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Attachment>
     */
    public function storeMany(Model $attachable, array $files, string $kind, string $diskFolder): Collection
    {
        /** @var array<int, string> $written paths written in THIS call, for cleanup */
        $written = [];

        try {
            return DB::transaction(function () use ($attachable, $files, $kind, $diskFolder, &$written) {
                $nextSort = (int) $attachable->attachments()->max('sort_order');

                return collect($files)->map(function (UploadedFile $file) use ($attachable, $kind, $diskFolder, &$nextSort, &$written) {
                    $path = $file->store($diskFolder, Attachment::DISK);

                    // store() reports a failed write as false on this disk.
                    if (! is_string($path) || $path === '') {
                        throw new RuntimeException('Could not write the uploaded file to disk.');
                    }

                    $written[] = $path;

                    return $attachable->attachments()->create([
                        'kind' => $kind,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'sort_order' => ++$nextSort,
                        'uploaded_by' => Auth::id(),
                    ]);
                });
            });
        } catch (Throwable $e) {
            // Rows are already rolled back; remove the files that went down first.
            $this->removeFiles($written);

            throw $e;
        }
    }

    public function delete(Attachment $attachment): void
    {
        $path = $attachment->path;

        $attachment->delete();

        $this->removeFiles([$path]);
    }

    public function deleteAllFor(Model $attachable): void
    {
        $attachable->attachments->each(fn (Attachment $attachment) => $this->delete($attachment));
    }

    /**
     * Best-effort removal, used after the database side is settled. It never
     * throws: whatever cannot be removed is logged as an orphan.
     *
     * @param  array<int, string>  $paths
     */
    private function removeFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths, fn ($p) => is_string($p) && $p !== ''));

        if ($paths === []) {
            return;
        }

        try {
            if (Storage::disk(Attachment::DISK)->delete($paths) === false) {
                Log::warning('Attachment file left behind (orphan)', ['paths' => $paths]);
            }
        } catch (Throwable $e) {
            Log::warning('Attachment file left behind (orphan)', ['paths' => $paths, 'reason' => $e->getMessage()]);
        }
    }
}
