/** Guarda a página de ingressos e os arquivos dela no service worker, para abrir sem internet. */
export async function keepTicketsOffline() {
  if (import.meta.dev || !('serviceWorker' in navigator)) return
  try {
    await navigator.serviceWorker.register('/sw.js')
    const registration = await navigator.serviceWorker.ready
    const assets = performance.getEntriesByType('resource').map((entry) => entry.name).filter((url) => new URL(url).origin === location.origin && new URL(url).pathname.startsWith('/_nuxt/'))
    registration.active?.postMessage({ type: 'keep', urls: [location.pathname, ...assets] })
  } catch {
    return
  }
}
