<?php

namespace App\Widgets;

use Illuminate\View\View;
use Statamic\Facades\Entry;
use Statamic\Widgets\Widget;

class HomepageLink extends Widget
{
    /**
     * The HTML that should be shown in the widget.
     *
     * @return string|View
     */
    public function html()
    {
        $homepage = Entry::query()->where('uri', '/')->first();
        $id = $homepage ? $homepage->id : '#';

        return view('widgets.homepage_link', ['id' => $id]);
    }
}
