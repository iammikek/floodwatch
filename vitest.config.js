import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@cockpit': fileURLToPath(new URL('./resources/js/cockpit', import.meta.url)),
        },
    },
    test: {
        environment: 'happy-dom',
        include: ['resources/js/cockpit/**/*.{test,spec}.{js,ts}'],
    },
});
