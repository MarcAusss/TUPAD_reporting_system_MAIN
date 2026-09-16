import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import os from 'node:os';

// Auto-detect the machine's LAN IPv4 address so other devices on the
// same network can load Vite assets (HMR) without hardcoding an IP
// that changes across networks/DHCP leases. Override with VITE_HOST.
function getLanIp() {
    const interfaces = os.networkInterfaces();
    for (const entries of Object.values(interfaces)) {
        for (const iface of entries ?? []) {
            if (iface.family === 'IPv4' && !iface.internal) {
                return iface.address;
            }
        }
    }
    return 'localhost';
}

const host = process.env.VITE_HOST || getLanIp();

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: `http://${host}:5173`,
        // Vite's default CORS/allowed-hosts policy only trusts localhost-like
        // origins. Pages loaded from the LAN IP (http://<ip>:8000) are a
        // different origin than the Vite dev server (http://<ip>:5173), so
        // without this, asset requests are blocked by CORS entirely.
        cors: true,
        hmr: {
            host,
        },
        // watch: {
        //     ignored: ['**/storage/framework/views/**'],
        // },
    },
});
