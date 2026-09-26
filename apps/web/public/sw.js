/*
 * Cafeyar service worker: browser (Web Push) notifications only, no offline caching.
 * Served from the root of every host (the panel, and each café's own address), so a customer's
 * "your order is ready" and a cashier's "new order" open the right page on the right site.
 */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => {
  let data = {};
  try {
    data = event.data ? event.data.json() : {};
  } catch {
    data = {};
  }
  const title = typeof data.title === 'string' && data.title ? data.title : 'کافه‌یار';

  event.waitUntil(self.registration.showNotification(title, {
    body: typeof data.body === 'string' ? data.body : '',
    tag: typeof data.tag === 'string' ? data.tag : undefined,
    renotify: Boolean(data.tag),
    dir: 'rtl',
    lang: 'fa',
    icon: '/favicon.ico',
    badge: '/favicon.ico',
    data: { url: typeof data.url === 'string' ? data.url : '/' },
  }));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL(event.notification.data?.url || '/', self.location.origin);
  // Only ever open pages of this same site.
  if (target.origin !== self.location.origin) return;

  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const client of windows) {
      if (client.url.split('#')[0] === target.href.split('#')[0] && 'focus' in client) return client.focus();
    }
    return self.clients.openWindow(target.href);
  })());
});
