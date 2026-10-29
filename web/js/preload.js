// Vue, fetched early on a visit the service worker doesn't control yet (the first). With the
// service worker in control, the import comes from its cache, and a preload beside it would be a
// second fetch that's never used (Chrome: "cross-world service worker resource mismatch"). A
// classic script after the import map, so the preload never comes before it.
(() => {
    if (navigator.serviceWorker?.controller) return;
    const link = document.createElement('link');
    link.rel = 'modulepreload';
    link.href = 'https://cdn.jsdelivr.net/npm/vue@3.5.44/dist/vue.esm-browser.prod.js';
    link.integrity = 'sha384-Yy+wUwjypE8OL2+tZ7lLLMsIVMFBXibFJCdzCWWWXnuVVdiuS3gViCyubnRyWnSP';
    link.crossOrigin = 'anonymous';
    document.head.append(link);
})();
