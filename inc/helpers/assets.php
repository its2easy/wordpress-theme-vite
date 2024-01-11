<?php

/**
 * Returns a config with variables shared between js and php. Config is a part of the theme so the function doesn't
 * check if file exists
 *
 * @return array<string, string|int>
 */
function theme_get_frontend_config(): array {

    $theme_path = get_template_directory();
    // phpcs:ignore WordPress.WP.AlternativeFunctions -- todo check wp alternative
    $config = file_get_contents("$theme_path/frontend-config.json");
    return json_decode($config, true);
}

/**
 * Checks if current environment is dev (by BrowserSync custom header)
 *
 * @return bool
 */
function theme_is_dev_server(): bool {
    // $_SERVER['HTTP_HOST'] and $_SERVER['SERVER_NAME'] could be from docker host, so check custom BS proxy header.
    // Alternative to this approach is to set env var or php const, and change it every time you switch between prod
    // and dev modes 👎, or check for the existence of the manifest.dev.json from plugin👎
    $frontend_config = theme_get_frontend_config();
    if (function_exists('getallheaders')) { // apache specific!, if you use nginx add polyfill for it
        $headers           = getallheaders();
        $proxy_header_name = $frontend_config['devModeProxyHeader'];// set by browserSync in vite.config.js
        if (isset($headers[ $proxy_header_name ])) { // value is not important, check the presence of the header
            return true;
        }
    }
    return false;
}

/**
 * Returns an array with build data from manifest.json.
 * Manifest example: https://vitejs.dev/guide/backend-integration.html
 *
 * @param string $folder Folder with manifest file
 * @return array
 * @throws Exception
 */
function theme_get_vite_manifest_data(string $folder): array {
    $theme_path             = get_template_directory();
    $manifest_path          = "$theme_path/$folder/manifest.json";
    $resolved_manifest_path = realpath($manifest_path);

    if (!is_file($resolved_manifest_path) || !is_readable($resolved_manifest_path)) {
        throw new Exception("Can't load vite manifest file: $manifest_path");
    }

    // phpcs:ignore WordPress.WP.AlternativeFunctions -- todo check wp alternative
    $vite_manifest = file_get_contents($resolved_manifest_path);
    return json_decode($vite_manifest, true);
}

/**
 * The function recursively searches for dependencies of the passed $entry. With $assetType='js' they are the scripts
 * that should be preloaded with <link rel='modulepreload' />, with $assetType='css' they are the styles from chunks
 * on which the current entry point depends
 *
 * @param string $entry Entry name (as in vite.config.js build.rollupOptions.input)
 * @param array $manifest Vite manifest data
 * @param string $asset_type 'js' or 'css'
 *
 * @return string[]
 */
function theme_get_assets_from_dependencies(string $entry, array $manifest, string $asset_type): array {
    if (!isset($manifest[ $entry ]['imports'])) return [];// skip if no entry or no 'imports' in this entry

    $assets = [];
    foreach ($manifest[ $entry ]['imports'] as $imports_entry) {  // 'imports' values are entry names, not file paths

        if (isset($manifest[ $imports_entry ]['imports'])) { // if entry from 'imports' has its own nested 'imports'
            // assuming that the manifest can't have cyclic dependencies, so there is no check for recursion depth
            $nested_assets = theme_get_assets_from_dependencies($imports_entry, $manifest, $asset_type);
            $assets        = array_merge($assets, $nested_assets);
        }

        // add the main asset(s) only after its dependencies
        if ($asset_type === 'css') {
            if (isset($manifest[ $imports_entry ]['css'])) {
                $assets = array_merge($assets, $manifest[ $imports_entry ]['css']);
            }
        } elseif ($asset_type === 'js') {
            $assets[] = $manifest[ $imports_entry ]['file']; // 'imports' file after its dependencies
        }

    } // foreach

    return $assets;
}

/**
 * Collects all the css files for the entry in the right order (except the main $entry 'file' if $entry is css-only)
 *
 * @param string $entry Name of entry
 * @param array $manifest Vite manifest data
 * @return string[]
 */
function theme_get_styles_for_entry(string $entry, array $manifest): array {
    $styles                 = [];
    $styles_of_dependencies = theme_get_assets_from_dependencies($entry, $manifest, 'css');
    $styles                 = array_merge($styles, $styles_of_dependencies);

    // css for current entrypoint if exist ([] in 'css' key), added after all styles of the current entry dependencies
    if (isset($manifest[ $entry ]['css'])) {
        $styles = array_merge($styles, $manifest[ $entry ]['css']);
    }

    return $styles;
}

