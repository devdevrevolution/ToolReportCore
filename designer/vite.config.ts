import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import dts from 'vite-plugin-dts'
import { resolve } from 'path'

export default defineConfig({
    plugins: [
        vue(),
        dts({
            tsconfigPath: resolve(__dirname, 'tsconfig.json'),
            outDir: resolve(__dirname, 'dist'),
            include: ['src/**/*.ts', 'src/**/*.vue'],
            exclude: ['src/main.ts', 'src/App.vue', 'src/vite-env.d.ts'],
        }),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
        },
    },
    build: {
        lib: {
            entry: resolve(__dirname, 'src/index.ts'),
            name: 'ToolreportDesigner',
            fileName: 'toolreport-designer',
        },
        rollupOptions: {
            external: ['vue', 'pinia', 'axios'],
            output: {
                globals: {
                    vue: 'Vue',
                    pinia: 'Pinia',
                    axios: 'axios',
                },
            },
        },
    },
})
