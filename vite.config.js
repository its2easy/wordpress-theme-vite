import { resolve } from 'path';
import { defineConfig } from 'vite';
import VitePluginBrowserSync from 'vite-plugin-browser-sync' // https://github.com/Applelo/vite-plugin-browser-sync
import 'dotenv/config'; // https://github.com/motdotla/dotenv

// shared config for js and php. config.themeName - it's possible to get themeName from process.cwd() but in some cases
// (like docker) theme folder name in project is not the same as folder name inside WP, so themeName is in config.
import config from './frontend-config.json';

export default defineConfig({
    /**
     * Usually `base` points to the folder where the entire compiled site is located, which will be site root. But in
     * WP project site root is always a WP site root, but compiled assets are in the theme folder.
     *
     * Vite uses paths {base}/{build.assetsDir}/chunk-name.js to reference its dynamic assets. These paths should
     * point to the files, created in `build.outDir` folder inside the theme. One way is to use base: '/',
     * wp root as vite root and path to build.outDir (wp-content/.../dist/assets) as build.assetsDir, but it won't work.
     * The other way is to use relative url path to `build.outDir` as `base`.
     *
     * [prod build]: base is used by vite to create URLs for dynamically imported js (if it has dependencies!) because
     * it (and its css) are inserted in <head> of a page as rel=modulepreload and can't be file relative (./chunk2.js)
     * but should be domain relative (/wp-content/themes/theme/dist/assets/chunk2.js)
     *
     * [dev build]: base is used as an url where vite (not WP!) server serves its assets. Scheme is
     * {server.host}:{server.port}/{base}/{build.assetsDir}/chunk.js. This path should be used in wp_enqueue_script()
     * in dev mode. `base` could be different for prod and dev, but this config uses one value for simplicity.
     */
    base: `/wp-content/themes/${config.themeName}/${config.distFolder}`,
    publicDir: false, // disable copying `public/` to outDir
    build: {
        outDir: resolve(__dirname, `./${config.distFolder}`),
        manifest: true, // need for wordpress to enqueue files (option works only for prod build)
        target: 'modules', // esbuild target, same as .browserslistrc
        rollupOptions: {
            input: {
                // paths (object values) are relative to vite config or root option. Object keys are arbitrary, but
                // they will be used in the names of the compiled chunks
                main: 'src/js/main-entrypoint.js',
                style_only: 'src/scss/style-only-entrypoint.scss',
                frontpage: 'src/js/frontpage-entrypoint.js',
                page_1: 'src/js/page-1-entrypoint.js',
                page_2: 'src/js/page-2-entrypoint.js',
            },
            external: [ // https://github.com/vitejs/vite/issues/10766
                /wp-content[\/\\]themes[\/\\][\w-]+[\/\\]assets/, // don't precess absolute paths to the theme static assets
            ],
        },
        reportCompressedSize: false,
    },
    plugins: [
        VitePluginBrowserSync({
            bs: {
                //port: 3010, // default is 3000, change if you have conflicts
                proxy: {
                    target: process.env.PROXY_SOURCE, // host from local php server
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
                logLevel: 'info', // plugin overrides it with 'silent', causing 'Proxying' url not displaying
            },
        }),
        // legacy({ // example for polyfills
        //     modernPolyfills: true, // entry name in manifest.json is 'vite/legacy-polyfills'
        //     polyfills: false,
        //     renderLegacyChunks: false,
        // }),
    ],
    server: {
        // host: 'localhost', // default
        port: config.viteServerPort, // vite server port 3005
        strictPort: true, // match exactly because it used on PHP side
        cors: true, // required to load scripts from custom host (vite server)
        // origin: `http://localhost:${config.viteServerPort}`, // this is required for `resolve.alias` for images in dev mode
    },
    css: {
        devSourcemap: true,
    },
    // strip comments from imported packages (~5-10kb), 'external' and 'linked' options don't work
    // https://github.innominds.com/vitejs/vite/discussions/5329
    esbuild: { legalComments: 'none' },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'src'),
            // File will be copied to `build.outDir` and url() in css will be `base` + asset filename.
            // In dev mode Vite serves it at dev server origin, but paths in css are with browserSync origin.
            // To make it work add `http://localhost:${config.viteServerPort}` as `server.origin`
            '@img': resolve(__dirname, 'assets/img'), // doesn't work in dev mode!
        }
    },
});

// To check stats: npx vite-bundle-visualizer --output dist/stats.html

// core-js and polyfills: polyfills could be added with @vitejs/plugin-legacy and modernPolyfills: true, BUT the `targets`
// option is for legacy polyfills, and targets for modernPolyfills are hardcoded for now (v5.2.0). (https://github.com/vitejs/vite/blob/main/packages/plugin-legacy/src/index.ts)
// So despite 'plugin-legacy' uses `useBuiltIns: "usage"` the polyfill chunk is big and there is no way to
// exclude some polyfills. The only reasonable way to polyfill features is to add each of them MANUALLY to the array
// in `modernPolyfills` option.
// todo check https://github.com/vitejs/vite/issues/14527 for modernTargets

// Long scss compilation: slow 'sass' package, maybe the things will change in the future.
// Workarounds: use sass-embedded https://github.com/vitejs/vite/issues/6736#issuecomment-1492974590, disable
// css.devSourcemap, replace @import with @use (not sure)
