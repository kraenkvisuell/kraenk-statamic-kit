/*
 * Image slideshow (Swiper 14) of the gallery set (partials/sets/gallery).
 * A separate Vite entry, loaded only by pages that render the set: the partial
 * pushes the vite tag into the layout's `head_scripts` section, ahead of
 * site.js, so the components are registered before Alpine starts.
 *
 * The set's `modus` picks the component around partials/gallery/slideshow:
 *
 * `inlineSlideshow` renders in place and builds its Swiper on init.
 *
 * `gallery(name)` is the popup behind the thumbnail grid. Several galleries can
 * appear on one page, so each is named after its set (`x-data="gallery('id')"`)
 * and opened by the `gallery-open` window event with `{ gallery: 'id', index: n }`
 * (1-based slide number) – every other gallery ignores the event. The overlay
 * drops in and rises out (x-transition in the partial) over the page, which
 * stays put: the site component locks the page scroll while gallery-opened/
 * -closed say an overlay is open.
 */
import Swiper from 'swiper'
import { Navigation, Keyboard } from 'swiper/modules'
import 'swiper/css'

// Swiper plus the caption of the current slide, shared by both components.
const slideshow = () => ({
    swiper: null,
    caption: '',
    credits: '',

    createSwiper() {
        return new Swiper(this.$refs.swiper, {
            modules: [Navigation, Keyboard],
            centeredSlides: true,
            slideToClickedSlide: true,
            preventInteractionOnTransition: true,
            navigation: {
                prevEl: this.$refs.prev,
                nextEl: this.$refs.next,
            },
            keyboard: { enabled: true },
            on: {
                slideChangeTransitionStart: () => { this.caption = ''; this.credits = '' },
                slideChangeTransitionEnd: () => this.updateCaption(),
            },
        })
    },

    // "3 / 12" (+ " – caption"), and the slide's credits next to it; both
    // come from the slide fields as data attributes (partials/gallery/slideshow).
    updateCaption() {
        const { activeIndex, slides } = this.swiper
        const { caption = '', credits = '' } = slides[activeIndex]?.dataset ?? {}

        this.caption = `${activeIndex + 1} / ${slides.length}` + (caption ? ` – ${caption}` : '')
        this.credits = credits
    },
})

document.addEventListener('alpine:init', () => {
    Alpine.data('inlineSlideshow', () => ({
        ...slideshow(),

        init() {
            this.swiper = this.createSwiper()
            this.updateCaption()
        },
    }))

    Alpine.data('gallery', (name) => ({
        ...slideshow(),
        isOpen: false,

        open({ gallery, index }) {
            if (gallery !== name) return

            this.$dispatch('site-close-overlays') // other overlays, before isOpen so we don't close ourselves
            this.isOpen = true
            this.$dispatch('gallery-opened') // the site component hides the menu button and locks the page scroll

            // wait for x-show: Swiper needs the popup visible to measure it
            this.$nextTick(() => {
                this.swiper ??= this.createSwiper()
                this.swiper.slideTo(index - 1, 0)
                this.updateCaption()
            })
        },

        close() {
            if (! this.isOpen) return

            this.isOpen = false
            this.$dispatch('gallery-closed')
        },
    }))
})
