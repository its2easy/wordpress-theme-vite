<?php

/**
 * Main function that handles dynamic assets. It relies on `theme_get_entry_points_for_current_page()` defined in
 * the theme
 */
function theme_enqueue_vite_assets(): void {
    $is_dev_mode     = theme_is_dev_server();
    $entry_points    = theme_get_entry_points_for_current_page();
    $frontend_config = theme_get_frontend_config(); // shared variables between js and php
    $scripts_queue   = []; // scripts to include on the current page
    $styles_queue    = []; // styles to include on the current page

    // ======= DEV (assets from vite dev server (only entrypoints for current page))
    if ($is_dev_mode) {
        // In dev mode vite emits the same files as defined in build.rollupOptions.input in vite.config.js and these
        // names are returned from theme_get_entry_points_for_current_page().
        // There is no @vite/client script here because it's handled by vite-plugin-browser-sync
        foreach ($entry_points as $entry_point) {
            $scripts_queue[] = $entry_point; // path to src, for ex. "src/js/main.js", all deps are loaded from js files
        }
    }

    // ======= PROD (assets from vite manifest.json file)
    if (!$is_dev_mode) {
        try {
            $manifest = theme_get_vite_manifest_data($frontend_config['distFolder']);// vite manifest
        } catch (Exception $e) {
            wp_die($e->getMessage()); // optional
        }

        // process each entry point for current page and add its assets to the queue(s)
        foreach ($entry_points as $entry_point) {
            if (!isset($manifest[ $entry_point ])) continue; // entry is not present in manifest

            // $styles_queue is shared between all entry points so the order of .css files is important:
            // 1) styles of 'imports' 2) styles in 'css' of the $entry_point 3) $entry_point itself if it's a css entry
            $entry_styles = theme_get_styles_for_entry($entry_point, $manifest); // get 1) and 2)
            $styles_queue = array_merge($styles_queue, $entry_styles);

            // main file of the entry, path is a string in 'file' key (relative to build folder, like assets/main-8c0d.js)
            if (pathinfo($manifest[ $entry_point ]['file'], PATHINFO_EXTENSION) === 'js') {
                // only the entrypoints are added here, their js dependencies (from 'imports' key in manifest) are added
                // as <link rel=modulepreload> in another function
                $scripts_queue[] = $manifest[ $entry_point ]['file']; // js entry
            } else {
                $styles_queue[] = $manifest[ $entry_point ]['file']; // 3) css-only entry, should be added last
            }

        } // end of entry points
    } // end of prod mode

    $theme_folder = get_template();
    // Path to 'outDir' folder where the compiled files are located, should match `base` option in vite.config.js.
    // Dev mode has `"`base: '/'` => assets are served at the root of the dev server host
    $prod_assets_folder_url = "/wp-content/themes/$theme_folder/{$frontend_config['distFolder']}";

    // ======= Include assets from queues
    foreach (array_unique($styles_queue) as $css_file) { // CSS (.css files don't exist in dev mode)
        wp_enqueue_style($css_file, "$prod_assets_folder_url/$css_file", array(), null);
    }
    foreach (array_unique($scripts_queue) as $js_file) { // JS
        // $js_file is something like 'src/js/main.js' for dev and 'assets/main-123123.js' for prod
        $asset_path = ($is_dev_mode)
            ? "http://localhost:{$frontend_config['viteServerPort']}/$js_file"
            : "$prod_assets_folder_url/$js_file";
        wp_enqueue_script($js_file, $asset_path, array(), null, true);
    }
}
add_action('wp_enqueue_scripts', 'theme_enqueue_vite_assets');


// add type="module" and crossorigin to vite scripts because they are es-modules (no native wp support yet)
function theme_modify_script_tag_for_modules($tag, $handle): string {
    if (strpos($handle, 'assets') !== false || // prod assets, 'assets' is default folder (`build.assetsDir`)
        strpos($handle, 'src') !== false) { // dev assets
        // remove type if there is one (for example if there is no add_theme_support('html5, ['script']) in the theme)
        $tag = preg_replace('/ type=([\'"])[^\'"]+\1/', '', $tag);
        $tag = str_replace(' src', ' type="module" crossorigin src', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'theme_modify_script_tag_for_modules', 15, 3);

// Add 'modulepreload' directives to <head>, because vite doesn't handle wp templates. Function relies on
// `theme_get_entry_points_for_current_page()` defined in the theme
// https://vitejs.dev/guide/features.html#preload-directives-generation
function theme_add_modulepreload_links(): void {
    if (theme_is_dev_server()) return; // 'modulepreload's are required only for prod

    $frontend_config = theme_get_frontend_config(); // shared vars between js and php
    try {
        $manifest = theme_get_vite_manifest_data($frontend_config['distFolder']); // vite manifest
    } catch (Exception $e) {
        wp_die($e->getMessage());
    }

    $theme                  = get_template();
    $prod_assets_folder_url = "/wp-content/themes/$theme/{$frontend_config['distFolder']}";
    $entry_points           = theme_get_entry_points_for_current_page(); // function must be defined in the theme

    $urls = [];
    foreach ($entry_points as $entry_point) {
        $urls = array_merge(
            $urls,
            theme_get_assets_from_dependencies($entry_point, $manifest, 'js')
        );
    }

    $preload_html = '';
    foreach (array_unique($urls) as $url) { // no 'as="script"' attr because it is default
        $preload_html .= "<link rel='modulepreload' href='$prod_assets_folder_url/$url' />\r\n";
    }
    echo $preload_html;
}
add_action('wp_head', 'theme_add_modulepreload_links', 15);


// ============================ HELPERS ============================

/**
 * Returns a config with variables shared between js and php. Config is a part of the theme so the function doesn't
 * check if file exists
 *
 * @return array<string, string|int>
 */
function theme_get_frontend_config(): array {
    $theme_path = get_template_directory();
    // phpcs:ignore WordPress.WP.AlternativeFunctions -- ok for local files
    $config = file_get_contents("$theme_path/frontend-config.json");
    return json_decode($config, true);
}

/**
 * Returns an array with build data from manifest.json.
 * Manifest example: https://vitejs.dev/guide/backend-integration.html
 *
 * @param string $folder Folder with manifest file
 * @return array<string, array>
 * @throws Exception
 */
function theme_get_vite_manifest_data(string $folder): array {
    $theme_path             = get_template_directory();
    $manifest_path          = "$theme_path/$folder/.vite/manifest.json"; // /.vite/ was added in vite v5
    $resolved_manifest_path = realpath($manifest_path);

    if (!is_file($resolved_manifest_path) || !is_readable($resolved_manifest_path)) {
        throw new Exception("Can't load vite manifest file: $manifest_path");
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions -- ok for local files
    $vite_manifest = file_get_contents($resolved_manifest_path);
    return json_decode($vite_manifest, true);
}

/**
 * Checks if current environment is dev (by BrowserSync custom header)
 *
 * @return bool
 */
function theme_is_dev_server(): bool {
    // $_SERVER['HTTP_HOST'] and $_SERVER['SERVER_NAME'] could be from docker host, so check custom BS proxy header.
    // Alternative to this approach is to set env var or php const, and change it every time you switch between prod
    // and dev modes 👎, or check for the existence of manifest.dev.json from vite-plugin-dev-manifest plugin
    $frontend_config = theme_get_frontend_config();
    if (function_exists('getallheaders')) { // apache specific!, if you use nginx add a polyfill
        $headers           = getallheaders();
        $proxy_header_name = $frontend_config['devModeProxyHeader']; // set by browserSync in vite.config.js
        if (isset($headers[ $proxy_header_name ])) { // value is not important, check only for the presence of the header
            return true;
        }
    }
    return false;
}

/**
 * The function recursively searches for dependencies of the $entry ('css' and 'file' of the passed $entry are
 * not included)
 * With $asset_type='js' they are the scripts that should be preloaded with <link rel='modulepreload' />,
 * with $asset_type='css' they are the styles from all the dependencies of $entry (not 'css' from $entry itself)
 *
 * @param string $entry Entry name (as in vite.config.js build.rollupOptions.input)
 * @param array<string, array> $manifest Vite manifest data
 * @param string $asset_type 'js' or 'css'
 *
 * @return string[]
 */
function theme_get_assets_from_dependencies(string $entry, array $manifest, string $asset_type): array {
    if (!isset($manifest[ $entry ]['imports'])) return [];// skip if no entry or no 'imports' in this entry

    $assets = [];
    foreach ($manifest[ $entry ]['imports'] as $imports_entry) {  // 'imports' values are entry names, not file paths

        if (isset($manifest[ $imports_entry ]['imports'])) { // if entry from 'imports' has its own nested 'imports'
            // assuming that the manifest.json can't have cyclic dependencies, so there is no check for recursion depth
            $nested_assets = theme_get_assets_from_dependencies($imports_entry, $manifest, $asset_type);
            $assets        = array_merge($assets, $nested_assets);
        }

        // add the main asset(s) only after its dependencies. ['css'] is always an array, ['file'] is always a string
        if ($asset_type === 'css') {
            if (isset($manifest[ $imports_entry ]['css'])) {
                $assets = array_merge($assets, $manifest[ $imports_entry ]['css']);
            }
        } elseif ($asset_type === 'js') {
            $assets[] = $manifest[ $imports_entry ]['file'];
        }

    } // foreach

    return $assets;
}

/**
 * Collects all the css files for the $entry (and from all its dependencies) in the right order (except for the main
 * $entry 'file' if $entry is css-only)
 *
 * @param string $entry Name of entry
 * @param array<string, array> $manifest Vite manifest data
 * @return string[]
 */
function theme_get_styles_for_entry(string $entry, array $manifest): array {
    $styles = theme_get_assets_from_dependencies($entry, $manifest, 'css');

    // css for current entrypoint if exist ([] in 'css' key), added after all styles of the current entry dependencies
    if (isset($manifest[ $entry ]['css'])) {
        $styles = array_merge($styles, $manifest[ $entry ]['css']);
    }

    return $styles;
}
