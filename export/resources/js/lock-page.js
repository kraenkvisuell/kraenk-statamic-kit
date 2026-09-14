/*
 * Scroll lock for full-height overlays below the navi (the gallery in
 * gallery.js): overflow hidden on <html> keeps the scroll position, so nothing
 * jumps when they close.
 */
export const lockPage = (locked) => {
    document.documentElement.style.overflow = locked ? 'hidden' : ''
}
