import uni from '@dcloudio/vite-plugin-uni';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [uni()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        port: 15174,
        proxy: {
            '/api': {
                target: 'http://mes.test',
                changeOrigin: true,
            },
        },
    },
});
