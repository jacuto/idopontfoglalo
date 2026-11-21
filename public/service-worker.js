/* Cache only explicitly public offline assets. Never cache pages, API data, uploads or credentials. */
const CACHE='raero-public-v1',PUBLIC=['/offline.html','/pwa/icon-180.png','/pwa/icon-192.png','/pwa/icon-512.png'];
self.addEventListener('install',event=>event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(PUBLIC))));
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('raero-public-')&&k!==CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',event=>{const request=event.request,url=new URL(request.url);if(request.method!=='GET'||url.origin!==self.location.origin)return;if(request.mode==='navigate'){event.respondWith(fetch(request).catch(()=>caches.match('/offline.html')));return;}if(PUBLIC.includes(url.pathname)&&!url.search)event.respondWith(caches.match(request).then(cached=>cached||fetch(request)));});
