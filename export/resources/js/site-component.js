/*
 * Global Alpine component on <body>: the menu state. Section links are plain #anchors; the browser handles
 * scrolling and the address bar. The menu covers the viewport, so the page
 * scroll is locked while it is open (lock-page.js).
 */
import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import { lockPage } from './lock-page'

document.addEventListener('alpine:init', () => {
    Alpine.data('site', () => ({
        menuOpen: false,

        init() {
            this.$watch('menuOpen', (open) => lockPage(open))
        },

        toggleMenu() {
            this.menuOpen = !this.menuOpen
        },

        // Following a navi link: close the menu and the overlays listening for
        // site-close-overlays (gallery).
        closeOverlays() {
            this.menuOpen = false
            this.$dispatch('site-close-overlays')
        },
    }))
})
