// Alpine components that need no Antlers live in these modules; components that do
// are registered in a <script> at the end of their Antlers file. Alpine itself
// ships with Livewire.
import './video-consent'
import './video-player'

// Vimeo player SDK for the videoPlayer component (video-player.js).
import VimeoPlayer from '@vimeo/player'
window.Vimeo = { Player: VimeoPlayer }

import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'

document.addEventListener('alpine:init', () => {
    // x-fade: adds `is-faded-in` once the element scrolls into view (templates
    // react with `opacity-0 is-faded-in:opacity-100`, editor-content.css for text).
    // Works for elements added later too, since Alpine initialises them.
    const fadeObserver = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (! entry.isIntersecting) continue

            entry.target.classList.add('is-faded-in')
            fadeObserver.unobserve(entry.target)
        }
    })

    Alpine.directive('fade', (el, _, { cleanup }) => {
        fadeObserver.observe(el)
        cleanup(() => fadeObserver.unobserve(el))
    })
})

Livewire.start()
