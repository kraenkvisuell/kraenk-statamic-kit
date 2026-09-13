/*
 * Consent for third-party videos (Vimeo/YouTube): `videoConsent` component,
 * used by partials/video/player (notice + accept button in place of the video)
 * and by the footer's revoke button. The choice lives in localStorage, so the
 * server never sees it: the HTML is identical for every visitor and the pages
 * stay static-cacheable – the browser decides what to render. Every instance
 * on a page follows one accept via the `video-consent-accepted` window event
 * (several videos in a post); revoking clears the choice and reloads the page.
 */
const KEY = 'third-party-videos'

function read() {
    try {
        return localStorage.getItem(KEY) === 'accepted'
    } catch {
        return false
    }
}

function write(accepted) {
    try {
        accepted ? localStorage.setItem(KEY, 'accepted') : localStorage.removeItem(KEY)
    } catch {}
}

document.addEventListener('alpine:init', () => {
    Alpine.data('videoConsent', () => ({
        accepted: read(),

        init() {
            this.onAccepted = () => { this.accepted = true }
            window.addEventListener('video-consent-accepted', this.onAccepted)
        },

        destroy() {
            window.removeEventListener('video-consent-accepted', this.onAccepted)
        },

        accept() {
            write(true)
            window.dispatchEvent(new CustomEvent('video-consent-accepted'))
        },

        revoke() {
            write(false)
            location.reload()
        },
    }))
})
