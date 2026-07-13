<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-off migration for the 2026-07 security audit (G2): move every
 * attachments-table file from the world-readable public disk to the private
 * local disk. New uploads already land on the private disk; reads go through
 * the authenticated attachments.show route. Idempotent — files already moved
 * (or missing) are skipped with a note.
 */
class PrivatizeAttachments extends Command
{
    protected $signature = 'attachments:privatize {--dry-run : List what would move without touching files}';

    protected $description = 'Move attachment files from the public disk to the private local disk.';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk(Attachment::DISK);
        $dry = (bool) $this->option('dry-run');

        $moved = $skipped = $missing = 0;

        Attachment::withoutAccountScope()->orderBy('id')->each(function (Attachment $attachment) use ($public, $private, $dry, &$moved, &$skipped, &$missing) {
            if ($private->exists($attachment->path)) {
                $skipped++;

                return;
            }

            if (! $public->exists($attachment->path)) {
                $this->warn("  missing on both disks: #{$attachment->id} {$attachment->path}");
                $missing++;

                return;
            }

            if ($dry) {
                $this->line("  would move: {$attachment->path}");
                $moved++;

                return;
            }

            $private->writeStream($attachment->path, $public->readStream($attachment->path));

            // Delete the public copy only after the private write is verified.
            if ($private->exists($attachment->path)) {
                $public->delete($attachment->path);
                $moved++;
            } else {
                $this->error("  copy failed, public file kept: {$attachment->path}");
            }
        });

        $this->info(($dry ? '[dry-run] ' : '')."moved: {$moved}, already private: {$skipped}, missing: {$missing}");

        return self::SUCCESS;
    }
}
