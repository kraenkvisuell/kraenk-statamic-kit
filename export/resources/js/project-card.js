/*
 * Project card (partials/projects/project): the thumb video plays on hover, and while
 * cards are in view one of them plays "by scroll" at a time, switching every
 * 3 s in document order (was small-films.js). Lives here instead of the partial
 * because the partial renders once per card and needs no Antlers; imported by
 * site.js.
 */
const SWITCH_EVERY = 3000

// Which visible card plays by itself. Plain object – no reactivity needed.
const cycle = {
    visible: [],
    current: null,
    timer: null,

    setVisible(card, isVisible) {
        const index = this.visible.indexOf(card)

        if (isVisible && index === -1) this.visible.push(card)
        if (! isVisible && index !== -1) this.visible.splice(index, 1)

        if (! isVisible && this.current === card) {
            card.playByScroll(false)
            this.current = null
        }

        if (this.visible.length && ! this.timer) {
            this.timer = setInterval(() => this.next(), SWITCH_EVERY)
            this.next()
        }

        if (! this.visible.length && this.timer) {
            clearInterval(this.timer)
            this.timer = null
        }
    },

    next() {
        if (! this.visible.length) return

        const cards = this.visible.slice().sort((a, b) =>
            a.$root.compareDocumentPosition(b.$root) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1
        )
        const index = (cards.indexOf(this.current) + 1) % cards.length

        this.current?.playByScroll(false)
        this.current = cards[index]
        this.current.playByScroll(true)
    },
}

const cardOf = new WeakMap()
const inView = new IntersectionObserver((entries) => {
    for (const entry of entries) {
        cardOf.get(entry.target)?.setVisible(entry.isIntersecting)
    }
})

document.addEventListener('alpine:init', () => {
    Alpine.data('projectCard', () => ({
        hovered: false,
        byScroll: false, // → playing-by-scroll class, fades the video in like a hover

        init() {
            cardOf.set(this.$root, this)
            inView.observe(this.$root)
        },

        destroy() {
            inView.unobserve(this.$root)
            cycle.setVisible(this, false)
        },

        setVisible(isVisible) {
            cycle.setVisible(this, isVisible)
        },

        hover(isHovered) {
            this.hovered = isHovered

            if (isHovered) this.play()
            else if (! this.byScroll) this.stop()
        },

        playByScroll(on) {
            this.byScroll = on

            if (this.hovered) return

            on ? this.play() : this.stop()
        },

        play() {
            this.$refs.video?.play().catch(() => {})
        },

        stop() {
            const video = this.$refs.video
            if (! video) return

            video.pause()
            video.currentTime = 0
        },
    }))
})
