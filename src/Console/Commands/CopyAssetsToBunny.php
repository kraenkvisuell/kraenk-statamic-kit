<?php

namespace Kraenkvisuell\StatamicKit\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use League\Flysystem\StorageAttributes;

/**
 * Copies every file of the local asset container disk (public/assets/main,
 * including the .meta/ folder Statamic needs) to the bunny-assets S3 disk,
 * keeping the paths identical so the container can later be switched to
 * that disk. Files already present with the same size are skipped, so the
 * command can be re-run to sync only new files.
 */
#[Signature('kit:copy-assets-to-bunny
    {--from=assets_main : Source disk (the asset container\'s local disk)}
    {--to=bunny-assets : Destination disk}
    {--force : Overwrite files that already exist on the destination}
    {--dry-run : Only list what would be copied}')]
#[Description('Copy all files from the local assets disk to the bunny-assets disk')]
class CopyAssetsToBunny extends Command
{
    /** Files that never belong on the CDN. */
    protected array $ignored = ['.DS_Store', '.gitkeep', 'Thumbs.db'];

    public function handle(): int
    {
        $from = Storage::disk($this->option('from'));
        $to = Storage::disk($this->option('to'));

        $files = collect($from->allFiles())
            ->reject(fn (string $path) => in_array(basename($path), $this->ignored))
            ->values();

        $this->info(sprintf(
            'Found %d files on "%s" (%s).',
            $files->count(),
            $this->option('from'),
            Number::fileSize($files->sum(fn (string $path) => $from->size($path)))
        ));

        // One listing of the destination instead of a HEAD request per file.
        $existing = $this->option('force') ? [] : $this->existingSizes($to);

        $toCopy = $files->reject(fn (string $path) => isset($existing[$path]) && $existing[$path] === $from->size($path));
        $skipped = $files->count() - $toCopy->count();

        if ($skipped > 0) {
            $this->line("Skipping {$skipped} files that already exist with the same size.");
        }

        if ($toCopy->isEmpty()) {
            $this->info('Nothing to copy.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $toCopy->each(fn (string $path) => $this->line("  {$path}"));
            $this->info("Dry run: {$toCopy->count()} files would be copied.");

            return self::SUCCESS;
        }

        $failed = [];
        $bar = $this->output->createProgressBar($toCopy->count());
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar->start();

        foreach ($toCopy as $path) {
            $bar->setMessage($path);

            $stream = $from->readStream($path);

            if (! $stream || ! $to->writeStream($path, $stream)) {
                $failed[] = $path;
            }

            if (is_resource($stream)) {
                fclose($stream);
            }

            $bar->advance();
        }

        $bar->setMessage('');
        $bar->finish();
        $this->newLine(2);

        if ($failed) {
            $this->error(count($failed).' files could not be copied:');
            collect($failed)->each(fn (string $path) => $this->line("  {$path}"));

            return self::FAILURE;
        }

        $this->info(sprintf('Copied %d files to "%s".', $toCopy->count(), $this->option('to')));

        return self::SUCCESS;
    }

    /**
     * @return array<string, int> path => size
     */
    protected function existingSizes($disk): array
    {
        $sizes = [];

        foreach ($disk->listContents('', true) as $item) {
            /** @var StorageAttributes $item */
            if ($item->isFile()) {
                $sizes[$item->path()] = $item->fileSize();
            }
        }

        return $sizes;
    }
}
