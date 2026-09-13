/*
 * Fullscreen image gallery (Swiper 14) of the gallery set (partials/sets/gallery).
 * A separate Vite entry, loaded only by pages that render the set: the partial
 * pushes the vite tag into the layout's `head_scripts` section, ahead of
 * site.js, so the components are registered before Alpine starts.
 *
 * Several galleries can appear on one page, so each is named after its set
 * (`x-data="gallery('id')"`) and opened by the `gallery-open` window event with
 * `{ gallery: 'id', index: n }` (1-based slide number) – every other gallery
 * ignores the event. The overlay drops in and rises out (x-transition in the
 * partial) over the page, which stays put (see overlay.js for the scroll lock).
 *
 * `galleryGrid(total)` is the "show more" expander of the set's grid
 * (8 pictures, 8 more per click).
 */
import Swiper from 'swiper'
import { Navigation, Keyboard } from 'swiper/modules'
import 'swiper/css'
import { lockPage } from './overlay'

document.addEventListener('alpine:init', () => {
    Alpine.data('gallery', (name) => ({
        isOpen: false,
        swiper: null,
        caption: '',

        open({ gallery, index }) {
            if (gallery !== name) return

            this.$dispatch('site-close-overlays') // awards drawer, before isOpen so we don't close ourselves
            this.isOpen = true
            lockPage(true)

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
            lockPage(false)
        },

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
                    slideChangeTransitionStart: () => this.caption = '',
                    slideChangeTransitionEnd: () => this.updateCaption(),
                },
            })
        },

        // "3 / 12" (+ " – text" when a slide carries data-text)
        updateCaption() {
            const { activeIndex, slides } = this.swiper
            const text = slides[activeIndex]?.dataset.text

            this.caption = `${activeIndex + 1} / ${slides.length}` + (text ? ` – ${text}` : '')
        },
    }))

    Alpine.data('galleryGrid', (total) => ({
        visible: 8,
        total,

        showMore() {
            this.visible += 8
        },
    }))
})
