<ui-widget title="">
    <div class="px-4 py-3">
        <p>
            @if ($id)
                <div class="flex items-center gap-2"><span class="flex items-center gap-2"><span
                            class="size-2 rounded-full bg-green-400"
                        ></span><span class="sr-only">Veröffentlicht</span><!----></span><a
                        class="line-clamp-1 overflow-hidden text-ellipsis"
                        href="/cp/collections/pages/entries/{{ $id }}"
                    >Startseite</a></div>
            @else
                Keine Startseite.
            @endif
        </p>
    </div>
</ui-widget>
