/*
 * Global Alpine component on <body>: the menu state, and whether a gallery
 * overlay is open (gallery.js dispatches gallery-opened/-closed; the menu
 * button hides meanwhile). Section links are plain #anchors; the browser handles
 * scrolling and the address bar. Both the menu and the gallery cover the
 * viewport, so this component locks the page scroll while one of them is open:
 * overflow hidden on <html> keeps the scroll position, so nothing jumps when
 * they close.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('site', () => ({
        menuOpen: false,
        galleryOpen: false,

        // The window events <body> reacts to (x-bind="listeners" next to x-data).
        listeners: {
            ['@site-close-overlays.window']() {
                this.menuOpen = false
            },
            ['@keydown.escape.window']() {
                this.menuOpen = false
            },
            ['@gallery-opened.window']() {
                this.galleryOpen = true
            },
            ['@gallery-closed.window']() {
                this.galleryOpen = false
            },
        },

        get pageLocked() {
            return this.menuOpen || this.galleryOpen
        },

        init() {
            this.$watch('pageLocked', (locked) => {
                document.documentElement.style.overflow = locked ? 'hidden' : ''
            })
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
