import './bootstrap';

// Leaflet is lazy-loaded when map is needed; preload on search start for parallel load
window.__loadLeaflet = function () {
    if (window.L && window.L.MarkerClusterGroup) return Promise.resolve(window.L);
    return Promise.all([
        import('leaflet/dist/leaflet.css'),
        import('leaflet'),
    ]).then(([, m]) => {
        window.L = m.default;
        return Promise.all([
            import('leaflet.markercluster/dist/MarkerCluster.css'),
            import('leaflet.markercluster/dist/MarkerCluster.Default.css'),
            import('leaflet.markercluster'),
        ]).then(() => window.L);
    });
};

// Alpine is provided by Livewire (@livewireScripts) to avoid multiple instances
