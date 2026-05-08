// resources/js/nprogress.js
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'

// Optional: Customize NProgress
NProgress.configure({ showSpinner: false, speed: 400, minimum: 0.15 })

export default function setupProgress(router) {
    router.on('start', () => NProgress.start())
    router.on('finish', () => NProgress.done())
}
