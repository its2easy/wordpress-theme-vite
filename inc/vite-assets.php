<?php

/**
 * Main function that handles dynamic assets. It relies on the function (`theme_get_entry_points_for_current_page()`
 * by default) defined in the theme
 */
function theme_enqueue_vite_assets(): void {
    // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- to be used in any theme
    $entry_points_func   = apply_filters('theme_assets_entry_points_function', 'theme_get_entry_points_for_current_page');
    $entry_points        = call_user_func($entry_points_func); // function must be defined in the theme
    $is_dev_mode         = theme_is_dev_server();
    $frontend_config     = theme_get_frontend_config(); // shared variables between js and php
    $default_dist_folder = 'dist';

    if ($is_dev_mode) { // DEV
        theme_enqueue_dev_assets($entry_points, $frontend_config['viteServerPort']);
    } else { // PROD (assets from vite manifest.json file)
        try {
            $manifest = theme_get_vite_manifest_data($default_dist_folder);// vite manifest
        } catch (Exception $e) {
            return; // no manifest file at this point, so don't include anything
            //wp_die($e->getMessage()); // optional
        }
        theme_enqueue_prod_assets($entry_points, $manifest, $default_dist_folder);
    }
}
add_action('wp_enqueue_scripts', 'theme_enqueue_vite_assets');


/**
 * Enqueue assets from vite dev server (only entrypoints for current page). In dev mode, vite emits the same files as
 * defined in build.rollupOptions.input in vite.config.js. There is no @vite/client script here because it's handled
 * by vite-plugin-browser-sync
 * @param array $entry_points
 * @param string $dev_server_port
 *
 * @return void
 */
function theme_enqueue_dev_assets(array $entry_points, string $dev_server_port): void {
    // $entry_point is path to src, for ex. "src/js/main.js", all deps are loaded from js files,
    // CSS is imported from js. Dev server base is '/' so no 'wp-content/themes/...' in path
    foreach ($entry_points as $entry_point) {
        wp_enqueue_script_module(
            "theme-vite-entrypoint-$entry_point",
            "http://localhost:$dev_server_port/$entry_point",
            array(),
            null,
        );
    }
}

/**
 * Enqueue assets for production mode that are defined in vite's manifest.json. All the filenames have their hash in name
 * @param array $entry_points
 * @param array<string, array> $manifest
 * @param string $dist_folder
 *
 * @return void
 */
function theme_enqueue_prod_assets(array $entry_points, array $manifest, string $dist_folder = 'dist'): void {
    $scripts_queue = []; // scripts to include on the current page
    $styles_queue  = []; // styles to include on the current page

    // process each entry point for the current page and add its assets to the queue(s)
    foreach ($entry_points as $entry_point) {
        if (!isset($manifest[ $entry_point ])) continue; // entry is not present in manifest

        // $styles_queue is shared between all entry points, so the order of .css files is important:
        // 1) styles of 'imports' 2) styles in 'css' of the $entry_point 3) $entry_point itself if it's a CSS entry
        $entry_styles = theme_get_styles_for_entry($entry_point, $manifest); // get 1) and 2)
        $styles_queue = array_merge($styles_queue, $entry_styles);

        // main file of the entry, path is a string in 'file' key (relative to build folder, like assets/main-8c0d.js)
        if (pathinfo($manifest[ $entry_point ]['file'], PATHINFO_EXTENSION) === 'js') {
            // only the entrypoints are added here, their js dependencies (from 'imports' key in manifest) are added
            // as <link rel=modulepreload> in another function
            $scripts_queue[ $entry_point ] = $manifest[ $entry_point ]['file']; // js entry
        } else {
            $styles_queue[] = $manifest[ $entry_point ]['file']; // 3) css-only entry, should be added last
        }

    } // end of entry points

    $theme_folder = get_template();
    // Path to 'outDir' folder where the compiled files are located, should match `base` option in vite.config.js.
    $prod_assets_folder_url = "/wp-content/themes/$theme_folder/$dist_folder";

    // ======= Include assets from queues
    foreach (array_unique($styles_queue) as $css_file) { // CSS
        wp_enqueue_style($css_file, "$prod_assets_folder_url/$css_file", array(), null);
    }
    foreach (array_unique($scripts_queue) as $entry => $js_file) { // JS
        // $entry is something like 'src/page/main.js' and $js_file is like 'assets/main-123123.js'
        $module_dependencies = theme_get_assets_from_dependencies($entry, $manifest, 'js');
        foreach ($module_dependencies as $module_dep) {
            // register every dependency for the current script for modulepreload
            wp_register_script_module($module_dep, "$prod_assets_folder_url/$module_dep", [], null);
        }
        // script will be used like <script type="module" ... > and $module_dependencies will become <link rel="modulepreload" ...>
        wp_enqueue_script_module($js_file, "$prod_assets_folder_url/$js_file", $module_dependencies, null);
    }
}


// ============================ HELPERS ============================

/**
 * Returns a config with variables shared between js and php. Config is a part of the theme, so the function doesn't
 * check if the file exists
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
 * @param string $folder Folder with the manifest file
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
    // $_SERVER['HTTP_HOST'] and $_SERVER['SERVER_NAME'] could be from docker host, so check a custom BS proxy header.
    // Alternative to this approach is to set env var or php const, and change it every time you switch between prod
    // and dev modes 👎, or ping the dev server url (👎 for frontend because of the timeout), or check for
    // the existence of manifest.dev.json from vite-plugin-dev-manifest plugin
    $frontend_config = theme_get_frontend_config();
    if (function_exists('getallheaders')) { // apache specific!, add a polyfill if you use nginx
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
 * not included).
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
 * Collects all the CSS files for the $entry (and from all its dependencies) in the right order (except for the main
 * $entry 'file' if $entry is css-only)
 *
 * @param string $entry Name of entry
 * @param array<string, array> $manifest Vite manifest data
 * @return string[]
 */
function theme_get_styles_for_entry(string $entry, array $manifest): array {
    $styles = theme_get_assets_from_dependencies($entry, $manifest, 'css');

    // CSS for current entrypoint if exist ([] in 'css' key), added after all styles of the current entry dependencies
    if (isset($manifest[ $entry ]['css'])) {
        $styles = array_merge($styles, $manifest[ $entry ]['css']);
    }

    return $styles;
}
