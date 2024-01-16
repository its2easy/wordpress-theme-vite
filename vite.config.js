import { resolve } from 'path';
import { defineConfig, loadEnv } from 'vite';
import VitePluginBrowserSync from 'vite-plugin-browser-sync' // https://github.com/Applelo/vite-plugin-browser-sync

// shared config for js and php. config.themeFolder - it's possible to get themeFolder from process.cwd() but in some cases
// (like docker) theme folder name in project is not the same as folder name inside WP, so themeFolder is in config.
import config from './frontend-config.json';

export default defineConfig(({ mode }) => {
    const isDev = mode === 'development';
    const env = loadEnv('', process.cwd(), '');

    return {
        /**
         * Typically `base` is a path to a directory where compiled assets will be served. In WP project the assets are not
         * copied from `build.outDir` (inside the theme folder) which means `base` should be an absolute path to `build.outDir`.
         * Vite uses paths like {base}/{build.assetsDir}/chunk-name to load dependencies of dynamically imported modules,
         * because their urls are using in <link rel> and can't be relative to the file that imports a module
         * (should be '/wp-content/themes/theme/dist/assets/chunk2.js' instead of './chunk2.js'). It's also required for
         * static assets like images and fonts, if you want vite to handle them (copy to dist, add hash, etc.)
         *
         * [dev build]: base is used as an url where vite (not WP!) dev server serves its assets. Scheme is
         * {server.host}:{server.port}/{base}/{build.assetsDir}/chunk.js. This path should be used in wp_enqueue_script()
         * in dev mode.
         */
        base: isDev
            ? '/'
            : `/wp-content/themes/${config.themeFolder}/${config.distFolder}`,
        publicDir: false, // disable copying `public/` to outDir
        build: {
            outDir: resolve(__dirname, `./${config.distFolder}`),
            manifest: true, // need for wordpress to enqueue files (option works only for prod build)
            target: 'modules', // esbuild target, same as .browserslistrc
            rollupOptions: {
                input: {
                    // Object keys are arbitrary, but they will be used in the names of the compiled chunks. Paths
                    // (object values) are relative to vite config or root option.
                    main: 'src/js/main-entrypoint.js',
                    style_only: 'src/scss/style-only-entrypoint.scss',
                    frontpage: 'src/js/frontpage-entrypoint.js',
                    page_1: 'src/js/page-1-entrypoint.js',
                    page_2: 'src/js/page-2-entrypoint.js',
                },
                external: [ // https://github.com/vitejs/vite/issues/10766
                    /wp-content[\/\\]themes[\/\\][\w-]+[\/\\]assets/, // don't process absolute paths to the theme static assets
                ],
            },
            reportCompressedSize: false,
        },
        server: {
            //host: true, // true to make it accessible from your local network (Wi-Fi). Default is 'localhost'
            port: config.viteServerPort, // vite server port 3005
            strictPort: true, // match exactly because it used on PHP side
            cors: true, // required to load scripts from custom host (vite server)
            // this is required for `resolve.alias` for static assets (images, fonts, etc.) that are referenced in url()
            // in css in dev mode. With host: true replace localhost with external ip
            origin: `http://localhost:${config.viteServerPort}`,
        },
        plugins: [
            VitePluginBrowserSync({
                bs: {
                    //port: 3000, // default is 3000, change if you have conflicts
                    proxy: {
                        target: env.PROXY_SOURCE, // host from local server when WP is running, stored in .env file
                        proxyReq: [ // set header to check dev mode on WP side
                            function (proxyReq) {
                                proxyReq.setHeader(config.devModeProxyHeader, "1"); // value is not important
                            },
                        ],
                    },
                    files: [ // relative to cwd, not to config
                        './**/*.php', // all php
                        './assets/**/*', // static assets
                    ],
                    // `open` is explicitly specified because by default BS will open BS's host AND vite's
                    // 'localhost:{vite-port}/{base}', and 'base' is a path to build.outDir which is not a valid wp url
                    open: 'local',
                    notify: true,
                    codeSync: true, // override VitePluginBrowserSync default (false), required for 'files' option
                    watchEvents: ['change', 'add'], // default is only 'change'
                    ghostMode: false, // disable sync between devices, not always useful
                    logLevel: 'info', // plugin overrides it with 'silent', causing 'Proxying' url not displaying in terminal
                },
            }),
            // legacy({ // example for polyfills, see note at the bottom
            //     modernPolyfills: true, // entry name in manifest.json is 'vite/legacy-polyfills'
            //     polyfills: false,
            //     renderLegacyChunks: false,
            // }),
        ],
        css: {
            devSourcemap: true,
        },
        // strip comments from imported packages (~5-10kb), 'external' and 'linked' options don't work
        // https://github.innominds.com/vitejs/vite/discussions/5329
        esbuild: {legalComments: 'none'},
        resolve: {
            alias: {
                '@': resolve(__dirname, 'src'),
                // File will be copied to `build.outDir` and url() in css will be `base` + asset filename.
                // In dev mode Vite serves it at dev server origin, but paths in css are with browserSync origin.
                // To make it work add `http://localhost:${config.viteServerPort}` as `server.origin`
                '@img': resolve(__dirname, 'assets/img'), // doesn't work in dev mode!
            }
        },
    }
});

// To check the size of assets: npx vite-bundle-visualizer --output dist/stats.html

// core-js and polyfills: polyfills could be added with @vitejs/plugin-legacy and modernPolyfills: true, BUT the `targets`
// option is for legacy polyfills, and targets for modernPolyfills are hardcoded for now (v5.2.0). (https://github.com/vitejs/vite/blob/main/packages/plugin-legacy/src/index.ts)
// So despite 'plugin-legacy' uses `useBuiltIns: "usage"` the polyfill chunk is big and there is no way to
// exclude some polyfills. The only reasonable way to polyfill features is to add each of them MANUALLY to the array
// in `modernPolyfills` option.
// todo check https://github.com/vitejs/vite/issues/14527 for modernTargets

// Long scss compilation: slow 'sass' package, maybe the things will change in the future.
// Workarounds: use sass-embedded https://github.com/vitejs/vite/issues/6736#issuecomment-1492974590, disable
// css.devSourcemap, replace @import with @use (not sure)
