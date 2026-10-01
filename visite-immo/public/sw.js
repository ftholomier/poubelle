// Service worker : l'interface s'ouvre même sans réseau. L'API n'est jamais mise en cache.

const CACHE = "visite-immo-v5";
const SHELL = ["./", "icon.svg", "manifest.webmanifest"]; // CSS et JS (versionnés) sont mis en cache au fil de l'eau

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener("activate", (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
  self.clients.claim();
});

// Réseau d'abord (pour toujours avoir la dernière version), cache en secours
self.addEventListener("fetch", (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== "GET" || url.origin !== location.origin || url.pathname.includes("/api/")) return;
  e.respondWith(
    fetch(e.request, { cache: "no-cache" }) // revalide toujours : une mise à jour du site est visible immédiatement
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy));
        return res;
      })
      .catch(() => caches.match(e.request).then((r) => r || caches.match("./"))),
  );
});
