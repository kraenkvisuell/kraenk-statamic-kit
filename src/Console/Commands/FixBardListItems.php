<?php

namespace Kraenkvisuell\StatamicKit\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Statamic\Facades\Entry;

/**
 * Repairs invalid ProseMirror left by the v2 migration: list items whose
 * content is inline text directly (`listItem > text`) instead of a block
 * (`listItem > paragraph > text`). Bard's schema requires a block inside a
 * list item, so opening such a field in the CP throws "Called contentMatchAt
 * on a node with invalid content" and wipes the field.
 *
 * Walks every entry's data (Bard lives in replicator sets too, e.g. the
 * pages main_content), wraps each list item's inline run in a paragraph and
 * saves the changed entries in every site. Idempotent – valid list items are
 * left untouched.
 */
#[Signature('kit:fix-bard-list-items {--dry-run : Report the entries that would change, write nothing}')]
#[Description('Wrap bare text in Bard list items in a paragraph (fixes v2-migrated content)')]
class FixBardListItems extends Command
{
    /** Node types that are inline content, not blocks. */
    protected array $inline = ['text', 'hardBreak'];

    public function handle(): int
    {
        $changed = 0;

        foreach (Entry::all() as $entry) {
            $data = $entry->data()->all();
            $fixed = $this->walk($data);

            if (json_encode($fixed) === json_encode($data)) {
                continue;
            }

            $changed++;
            $this->line("  {$entry->collectionHandle()}/{$entry->slug()} ({$entry->locale()})");

            if (! $this->option('dry-run')) {
                $entry->data($fixed)->save();
            }
        }

        $this->newLine();
        $this->info(($this->option('dry-run') ? 'Dry run – ' : '').($changed
            ? "{$changed} entries ".($this->option('dry-run') ? 'would be' : 'were').' fixed.'
            : 'No invalid list items found.'));

        return self::SUCCESS;
    }

    /**
     * Recursively fix every listItem in an arbitrary data tree.
     */
    protected function walk(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (($value['type'] ?? null) === 'listItem') {
            $value['content'] = $this->wrapInlines($value['content'] ?? []);
        }

        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $value[$key] = $this->walk($child);
            }
        }

        return $value;
    }

    /**
     * Group runs of inline nodes into paragraphs, keep block nodes as they
     * are, and guarantee at least one block so the list item is never empty.
     *
     * @param  array<int, array<string, mixed>>  $content
     * @return array<int, array<string, mixed>>
     */
    protected function wrapInlines(array $content): array
    {
        $out = [];
        $buffer = [];

        $flush = function () use (&$out, &$buffer) {
            if ($buffer) {
                $out[] = ['type' => 'paragraph', 'content' => $buffer];
                $buffer = [];
            }
        };

        foreach ($content as $node) {
            if (in_array($node['type'] ?? null, $this->inline, true) || isset($node['text'])) {
                $buffer[] = $node;
            } else {
                $flush();
                $out[] = $node;
            }
        }

        $flush();

        return $out ?: [['type' => 'paragraph']];
    }
}
