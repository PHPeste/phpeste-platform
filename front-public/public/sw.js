const CACHE = 'phpeste-ingressos-v1'
const TICKETS = /^\/conta\/ingressos(\/|$)/
const ASSETS = /^\/_nuxt\//
const BUILDS = /^\/_nuxt\/builds\//

const sameOrigin = (url) => url.origin === self.location.origin
const allowed = (url) => sameOrigin(url) && (TICKETS.test(url.pathname) || ASSETS.test(url.pathname))

self.addEventListener('install', () => self.skipWaiting())

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    for (const name of await caches.keys()) {
      if (name !== CACHE) await caches.delete(name)
    }
    await self.clients.claim()
  })())
})

self.addEventListener('message', (event) => {
  const urls = Array.isArray(event.data?.urls) ? event.data.urls : []
  const wanted = urls.flatMap((value) => {
    try {
      const url = new URL(value, self.location.origin)
      return allowed(url) ? [url] : []
    } catch {
      return []
    }
  })
  event.waitUntil(keep(wanted))
})

async function keep(urls) {
  const cache = await caches.open(CACHE)
  await Promise.all(urls.map(async (url) => {
    const key = TICKETS.test(url.pathname) ? url.pathname : url.href
    if (ASSETS.test(url.pathname) && !BUILDS.test(url.pathname) && await cache.match(key)) return
    try {
      const response = await fetch(url.href, { credentials: 'same-origin' })
      if (response.ok) await cache.put(key, response)
    } catch {
      return
    }
  }))
}

async function networkFirst(request, key, fallback) {
  const cache = await caches.open(CACHE)
  try {
    const response = await fetch(request)
    if (response.ok) await cache.put(key, response.clone())
    return response
  } catch {
    return (await cache.match(key)) ?? (fallback ? await cache.match(fallback) : undefined) ?? Response.error()
  }
}

self.addEventListener('fetch', (event) => {
  const request = event.request
  if (request.method !== 'GET') return
  const url = new URL(request.url)
  if (!sameOrigin(url)) return

  if (request.mode === 'navigate' && TICKETS.test(url.pathname)) {
    event.respondWith(networkFirst(request, url.pathname, '/conta/ingressos'))
    return
  }
  if (BUILDS.test(url.pathname)) {
    event.respondWith(networkFirst(request, url.href))
    return
  }
  if (ASSETS.test(url.pathname)) {
    event.respondWith(caches.match(url.href).then((cached) => cached ?? fetch(request)))
  }
})
