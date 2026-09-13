/*
 * Embedded video (partials/video/player): Vimeo through the player SDK – keys
 * can be vanity URLs (vimeo.com/user/film) that only the SDK resolves – or a
 * YouTube nocookie iframe. Reads data-key and data-autoplay from its root;
 * sizing is CSS in the partial. `Vimeo` is exposed on the window by site.js.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('videoPlayer', () => ({
        init() {
            const key = this.$root.dataset.key ?? ''
            if (! key) return

            const autoplay = this.$root.hasAttribute('data-autoplay')

            this.isYoutube(key) ? this.embedYoutube(key, autoplay) : this.embedVimeo(key, autoplay)
        },

        isYoutube(key) {
            key = key.toLowerCase()

            return key.includes('youtu.be/') || key.includes('youtube.com/')
        },

        embedVimeo(key, autoplay) {
            new Vimeo.Player(this.$refs.player, { url: key, autoplay, muted: false })
        },

        embedYoutube(key, autoplay) {
            let id = key.slice(key.lastIndexOf('/') + 1)
            try {
                const url = new URL(key)
                id = url.searchParams.get('v') ?? url.pathname.split('/').filter(Boolean).pop()
            } catch {}

            const iframe = document.createElement('iframe')
            iframe.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?rel=0&playsinline=1' + (autoplay ? '&autoplay=1' : '')
            iframe.allow = 'autoplay; fullscreen; picture-in-picture'
            iframe.allowFullscreen = true
            this.$refs.player.append(iframe)
        },
    }))
})
