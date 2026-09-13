<?php

namespace Kraenkvisuell\StatamicKit\Modifiers;

use Statamic\Modifiers\Modifier;

class EnsureUrl extends Modifier
{
    public function index($value)
    {
        $value = trim($value);

        if (
            strpos($value, '://') > 0
            || substr($value, 0, 7) == 'mailto:'
        ) {
            return $value;
        }

        return 'http://'.$value;
    }
}
