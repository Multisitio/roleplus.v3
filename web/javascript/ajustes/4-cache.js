function borrar_cache(no_redirigir) {
    if (typeof caches === 'undefined') {
        return alert('Cache no active.');
    }

    caches.delete('files');

    navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for (let registration of registrations) {
            registration.unregister()
        }
    })

    if (no_redirigir != 1) {
        location.href = '?no-cache=1';
    }

    alert('Cache deleted.');
}