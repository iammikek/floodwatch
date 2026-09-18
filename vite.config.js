import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Sail publishes host VITE_PORT → container :5173. Keep listen port 5173 inside
// the container; advertise the host port for HMR / public/hot.
export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const hostVitePort = Number(env.VITE_PORT) || 5173;

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/admin-chart.js',
                    'resources/js/cockpit/main.js',
                ],
                refresh: true,
            }),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            // Browser reaches Vite via Sail's published host port (VITE_PORT→5173).
            origin: `http://localhost:${hostVitePort}`,
            // Page is served from APP_URL (:80), modules from VITE_PORT — allow that origin.
            cors: {
                origin: [
                    'http://localhost',
                    'http://127.0.0.1',
                    `http://localhost:${hostVitePort}`,
                    `http://127.0.0.1:${hostVitePort}`,
                ],
            },
            hmr: {
                host: 'localhost',
                port: hostVitePort,
            },
        },
    };
});
