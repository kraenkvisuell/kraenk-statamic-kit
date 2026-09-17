// Alpine components that need no Antlers live in these modules; components that do
// are registered in a <script> at the end of their Antlers file. Alpine itself
// ships with Livewire ({{ livewire:scripts }} in the layout, which starts both on
// DOMContentLoaded – after this deferred module has registered its components).
import './site-component'
import './video-consent'
import './video-player'

// Vimeo player SDK for the videoPlayer component (video-player.js).
import VimeoPlayer from '@vimeo/player'
window.Vimeo = { Player: VimeoPlayer }
