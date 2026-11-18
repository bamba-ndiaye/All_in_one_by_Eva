import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            strictPort: true,
            origin: 'http://localhost:5173',
            // Comme « origin » est fixé, le plugin Laravel n'autoriserait que http://localhost:5173 :
            // le navigateur bloquerait alors tout le JS/CSS de l'appli servie sur :8000.
            // On autorise donc l'appli en local (localhost / 127.0.0.1, tout port) et APP_URL.
            cors: {
                origin: [
                    /^https?:\/\/(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/,
                    ...(env.APP_URL ? [env.APP_URL.replace(/\/$/, '')] : []),
                ],
            },
            hmr: {
                host: 'localhost',
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
    };
});
