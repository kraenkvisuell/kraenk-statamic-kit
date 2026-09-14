/*
 * Global Alpine component on <body>: menu and partners drawer state (one of
 * them open at a time). Section links are plain #anchors; the browser handles
 * scrolling and the address bar. The menu covers the viewport, so the page
 * scroll is locked while it is open (lock-page.js).
 */
import { Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm'
import { lockPage } from './lock-page'

document.addEventListener('alpine:init', () => {
    Alpine.data('site', () => ({
        menuOpen: false,
        partnersOpen: false,

        init() {
            this.$watch('menuOpen', (open) => lockPage(open))
        },

        toggleMenu() {
            this.menuOpen = !this.menuOpen
            if (this.menuOpen) this.partnersOpen = false
        },

        togglePartners() {
            this.partnersOpen = !this.partnersOpen
            if (this.partnersOpen) this.menuOpen = false
        },

        // Following a navi link: close the menu, the partners drawer and the
        // overlays listening for site-close-overlays (gallery).
        closeOverlays() {
            this.menuOpen = false
            this.partnersOpen = false
            this.$dispatch('site-close-overlays')
        },
    }))
})
