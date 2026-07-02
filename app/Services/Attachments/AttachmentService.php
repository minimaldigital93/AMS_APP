<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Store/delete-from-disk logic for polymorphic multi-file attachments,
 * shared by Business Expense receipts and Tenant ID documents so the
 * Storage:: calls live in one place instead of duplicated per controller.
 */
class AttachmentService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Attachment>
     */
    public function storeMany(Model $attachable, array $files, string $kind, string $diskFolder): Collection
    {
        $nextSort = (int) $attachable->attachments()->max('sort_order');

        return collect($files)->map(function (UploadedFile $file) use ($attachable, $kind, $diskFolder, &$nextSort) {
            $path = $file->store($diskFolder, 'public');

            return $attachable->attachments()->create([
                'kind' => $kind,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sort_order' => ++$nextSort,
                'uploaded_by' => auth()->id(),
            ]);
        });
    }

    public function delete(Attachment $attachment): void
    {
        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();
    }

    public function deleteAllFor(Model $attachable): void
    {
        $attachable->attachments->each(fn (Attachment $attachment) => $this->delete($attachment));
    }
}
