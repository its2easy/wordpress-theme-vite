Example of Vite integration with a WordPress theme, made to be easily used in other themes.

## Features

- support for different use cases:
  - multiple entry points
  - CSS-only entry points
  - conditionally included entry points (on the PHP side)
  - nested dependencies (any level)
  - dynamic imports (with nested dependencies too)
- dev server with auto-reload
- no need to define constants or env variables and change them every time you switch between `prod` and `dev` builds.
  The theme uses the browserSync header to check if it works on the dev server
- works with **Docker**
- automatic **modulepreload** links for dependencies
- ability to load compiled styles into the Gutenberg editor
- ability to define a local WP host in .env file and not store it in git
- SCSS, Autoprefixer (optional)
- easy to integrate with an existing theme
- compatible with PHP 7.4+

The theme contains a small amount of content that is used primarily to verify that the build is working correctly.
There are only a few files related to the asset compilation. The main files are `inc/vite-assets.php`, `vite.config.js`,
and `frontend-config.json`.

## How to use it with your theme?
1. Install packages ```npm install vite vite-plugin-browser-sync sass autoprefixer --save-dev```. *sass* and *autoprefixer*
   are optional.
2. Copy `inc/vite-assets.php` to your theme and include it in `functions.php`.
 ```php
require get_template_directory() . '/inc/vite-assets.php';
```
3. Copy `postcss.config.cjs` and `.browserslistrc` if you need Autoprefixer.
4. Copy `vite.config.js` to the root of the theme. Replace `build.rollupOptions.input` with your theme entry points.
5. Copy `.env.example` as `.env` and change the `PROXY_SOURCE` variable to the domain where your local WP site is running
   (ONLY used in DEV mode).
 This value is used to specify a proxy for browserSync in cases where the theme is used in WP instances running
 on different domains. If you don't need this, just hardcode your domain in `plugins.VitePluginBrowserSync.bs.proxy.target`
 option in `vite.config.js`
6. Copy `frontend-config.js` to the theme root. Change the `themeName` value to the name of the theme folder, which will
 be inside the running WP instance. The folder name may be different if the theme is mounted to a Docker container, for example.
 Change other values only if they conflict with something in your theme:
   - `distFolder` - change if this folder is already used for other purposes, it will be used as `build.outDir` vite option
   - `viteServerPort` - port where the vite dev server emits its assets (on localhost)
   - `devModeProxyHeader` - custom header name, which is used to check dev mode
7. Create a function `theme_get_entry_points_for_current_page()` (example in `functions.php`) that returns a list of
  entry points that need to be included on the current page. If you want to use a
  function with a different name, just rename `theme_get_entry_points_for_current_page()` calls in `vite-assets.php`.
8. Add `import 'vite/modulepreload-polyfill';` to the beginning of the main js entry point of the theme
  (example in `src/js/main-entry point.js`)
9. Add scripts to `package.json`:
```json
"scripts": {
    "start": "vite"
    "build": "vite build",
    },
```


## Development
```bash
# start dev server
npm start

# build for production
npm run build
```
Theme relies on `manifest.json` in prod mode, so `npm run build` must be run at least once

#### How to add a new entry point?
Add the path to the source file in two places:
- `build.rollupOptions.input` in `vite.config.js` - add an entry to the option object where value is the path
 to the file (relative to the config), like `page_1: 'src/js/page-1-entrypoint.js'`, where `page_1`
is arbitrary name
- your `theme_get_entry_points_for_current_page()` function - add a string with the path to the returned array. The
path must be the same as in `build.rollupOptions.input`

#### How to reference a theme image in SCSS/CSS?
```scss
// absolute path
background-image: url('/wp-content/themes/theme/assets/img/wp-logo.png');

// path from a scss variable + image
background-image: url('#{$img-path}/wp-logo.png');

// custom function that adds an absolute path
background-image: img-url('wp-logo.png');
```
It is possible to use an alias (defined in `vite.config.js`) if you want vite to handle an asset (copy to dist,
serve on dev server host, add hash to filename, etc.)
```scss
background-image: url('@img/wp-logo.png'); // vite alias as prefix
```

### Dev mode

#### How does dev mode work with WP?
When the dev server starts, it runs on its host (**localhost:3005**) simultaneously with the local WP instance
(**mywebsite.local**). BrowserSync (**localhost:3000**) uses WP host (**mywebsite.local**) as a proxy, replaces all WP
links (**mywebsite.local**) with its own (**localhost:3000**), and adds a custom
header to all the requests. This header is used on the PHP side to replace the origin of all the dynamic scripts and styles
(handled by vite) with the dev server host (**localhost:3005**).

#### Access to the site (in dev mode) via LAN (Wi-Fi)
To access website via LAN (Wi-Fi) network, enable `server.host: true` in `vite.config.js` and start the server.
Then replace `localhost` with your external ip (like 192.169.0.100) in `theme_enqueue_vite_assets()` (in `vite-assets.php`).
This could be automated with [vite-plugin-dev-manifest](https://github.com/owlsdepartment/vite-plugin-dev-manifest) that specifies an external host in its config

### Other

#### How to send data from PHP to JS?

Example in `theme_output_js_data()` in `functions.php`

#### How to add compiled assets to the Gutenberg editor?

Example in `theme_add_editor_styles()` in `functions.php`
