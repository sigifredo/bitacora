import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { readFileSync } from 'node:fs';

// La versión se define solo en backend/VERSION (la misma que sirve GET /api/version).
const version = readFileSync(new URL('../backend/VERSION', import.meta.url), 'utf8').trim();

export default defineConfig({
    // Ruta de despliegue, p. ej. VITE_BASE=/casiopea/ pnpm build. Por defecto, la raíz.
    base: process.env.VITE_BASE || '/',
    plugins: [vue()],
    define: { __APP_VERSION__: JSON.stringify(version) },
    server: {
        // El backend lo sirve Apache (sin reescritura, vía PATH_INFO): solo hay que ejecutar `pnpm dev`.
        // Otro destino: API_TARGET=http://127.0.0.1:8000 API_PREFIX= pnpm dev (servidor embebido de PHP).
        proxy: {
            '/api': {
                target: process.env.API_TARGET || 'http://localhost',
                changeOrigin: true,
                rewrite: path => (process.env.API_PREFIX ?? '/casiopea/index.php') + path,
                cookiePathRewrite: { '*': '/' },
            },
        },
    },
});
