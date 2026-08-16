const CACHE = 'redvecinal-v9';
const STATIC = ['./public/assets/css/bootstrap.min.css','./public/assets/css/app.css','./public/assets/css/admin.css','./public/assets/css/admin-modules.css','./public/assets/js/bootstrap.bundle.min.js','./public/assets/js/app.js','./public/assets/img/icon.svg','./public/assets/img/icon-192.png','./public/assets/img/icon-512.png','./','./ingresar','./descargar-vecinos'];
self.addEventListener('install', (event) => event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(STATIC)).catch(() => {})));
self.addEventListener('activate', (event) => event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))));
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  event.respondWith(fetch(event.request).then((response) => { const copy = response.clone(); caches.open(CACHE).then((cache) => cache.put(event.request, copy)); return response; }).catch(() => caches.match(event.request).then((response) => response || caches.match('./'))));
});
