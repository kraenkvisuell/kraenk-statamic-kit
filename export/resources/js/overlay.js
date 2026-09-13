/*
 * Full-height overlays below the navi (legal texts in partials/overlay, the
 * gallery in gallery.js). They drop in / rise out via x-transition in the
 * markup, and lock page scrolling while open – overflow hidden on <html>
 * keeps the scroll position, so nothing jumps when they close.
 */
export const lockPage = (locked) => {
    document.documentElement.style.overflow = locked ? 'hidden' : ''
}

document.addEventListener('alpine:init', () => {
    // Generic content overlay, opened with $dispatch('overlay-open', 'name')
    // (the name check happens in the partial's listener).
    Alpine.data('overlay', () => ({
        isOpen: false,

        open() {
            this.$dispatch('site-close-overlays') // other overlays, awards drawer – before isOpen so we don't close ourselves
            this.isOpen = true
            lockPage(true)
        },

        close() {
            if (! this.isOpen) return

            this.isOpen = false
            lockPage(false)
        },
    }))
})
