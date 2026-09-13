<?php

namespace Kraenkvisuell\StatamicKit\Modifiers;

use Illuminate\Support\Number;
use Statamic\Modifiers\Modifier;

/**
 * Bytes → "210,6 KB" via Laravel's Number::fileSize, i.e. formatted for the
 * current language (see the kit's ServiceProvider). Statamic's own asset `size`
 * variable always uses a dot. Usage: {{ file:size_bytes | file_size }},
 * optional param: decimals (default 1).
 */
class FileSize extends Modifier
{
    public function index($value, $params)
    {
        if (! is_numeric($value)) {
            return '';
        }

        return Number::fileSize((float) $value, (int) ($params[0] ?? 1));
    }
}
