<?php

namespace Kraenkvisuell\StatamicKit\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Moves every Postgres id sequence past the highest id in its table. After
 * importing rows with explicit ids (a database copy from another environment)
 * the sequences still start at 1, so the next insert collides with an
 * existing primary key. Only tables with an integer `id` column and at least
 * one row are touched; a sequence that is already ahead stays where it is
 * (GREATEST), so the command is idempotent.
 */
#[Signature('site:reset-postgres-keys {--dry-run : Report the sequences that would change, write nothing}')]
#[Description('Advance the Postgres id sequences past the highest id of each table')]
class ResetPostgresKeys extends Command
{
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $changed = 0;

        foreach (Schema::getTableListing() as $table) {
            $table = Str::afterLast($table, '.');

            if (! Schema::hasColumn($table, 'id') || ! stristr(Schema::getColumnType($table, 'id'), 'int')) {
                continue;
            }

            $maxId = DB::table($table)->max('id');

            if ($maxId === null) {
                continue;
            }

            $sequence = "{$table}_id_seq";
            $current = DB::selectOne("SELECT last_value, is_called FROM {$sequence}");
            $next = $current->is_called ? $current->last_value + 1 : $current->last_value;

            if ($next > $maxId) {
                $this->line("{$table}: sequence at {$next}, max id {$maxId} – fine");

                continue;
            }

            $changed++;
            $this->info(($dryRun ? 'would set' : 'set')." {$sequence} to {$maxId} (was {$next}, max id {$maxId})");

            if (! $dryRun) {
                DB::statement("SELECT setval('{$sequence}', {$maxId})");
            }
        }

        $this->info(($dryRun ? 'would change' : 'changed')." {$changed} sequences");

        return self::SUCCESS;
    }
}
