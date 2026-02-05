import './bootstrap';

// Leaflet is lazy-loaded when map is needed; preload on search start for parallel load
window.__loadLeaflet = function () {
    if (window.L) return Promise.resolve(window.L);
    return Promise.all([
        import('leaflet/dist/leaflet.css'),
        import('leaflet'),
    ]).then(([, m]) => {
        window.L = m.default;
        return m.default;
    });
};

// Alpine is provided by Livewire (@livewireScripts) to avoid multiple instances
