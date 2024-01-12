<?php
// function returns the entry points for the current page only because not every asset needs to be included on every page
function theme_get_entry_points_for_current_page(): array {
    // order is important
    $entry_points = array( 'src/js/main-entrypoint.js' ); // files could be added conditionally
    $entry_points[] = 'src/scss/style-only-entrypoint.scss';

    if (is_front_page()) $entry_points[] = 'src/js/frontpage-entrypoint.js';
    if (is_page_template('page-templates/page-1.php')) $entry_points[] = 'src/js/page-1-entrypoint.js';
    if (is_page_template('page-templates/page-2.php')) $entry_points[] = 'src/js/page-2-entrypoint.js';
    return $entry_points;
}

function theme_scripts() {
    //if (is_admin()) return; // Fix wp5.8 new widgets which load frontend assets on backend widgets page

    $is_dev_mode     = theme_is_dev_server();
    $entry_points    = theme_get_entry_points_for_current_page();
    $frontend_config = theme_get_frontend_config(); // shared variables between js and php
    $scripts_queue   = []; // scripts to include on current page
    $styles_queue    = []; // styles to include on current page

    // ======= DEV (assets from vite dev server (only entrypoints for current page))
    if ($is_dev_mode) {
        // In dev mode vite emits the same files as defined in build.rollupOptions.input in vite.config.js and these
        // names are returned from theme_get_entry_points_for_current_page().
        // There is no @vite/client script here because it's handled by vite-plugin-browser-sync
        foreach ($entry_points as $entry_point) {
            $scripts_queue[] = $entry_point; // path to src, for ex. "src/js/main.js", css are inside js
        }
    }

    // ======= PROD (get paths from vite manifest.json file)
    if (!$is_dev_mode) {
        try {
            $manifest = theme_get_vite_manifest_data($frontend_config['distFolder']);// vite manifest
        } catch (Exception $e) {
            wp_die($e->getMessage()); // optional
        }

        // process each entry point for current page and add its assets to the queues
        foreach ($entry_points as $entry_point) {
            if (!isset($manifest[ $entry_point ])) continue; // entry is not present in manifest

            // $styles_queue is shared between all entry points so the order of .css files is important:
            // 1) styles of 'imports', 2) styles in 'css' of the $entry_point 3) $entry_point itself if it's a css file
            $entry_styles = theme_get_styles_for_entry($entry_point, $manifest); // returns 1) and 2)
            $styles_queue = array_merge($styles_queue, $entry_styles);

            // main file of the entry, path is a string in 'file' key (relative to build folder, like assets/main-8c0d.js)
            if (pathinfo($manifest[ $entry_point ]['file'], PATHINFO_EXTENSION) === 'js') {
                $scripts_queue[] = $manifest[ $entry_point ]['file']; // js entry
            } else {
                $styles_queue[] = $manifest[ $entry_point ]['file']; // 3) css-only entry, should be added last
            }

        } // end of entry points
    } // end of prod mode

    $theme_folder = get_template();
    // Path to 'outDir' folder where the compiled files are located, should match `base` option in vite.config.js
    // Dev mode assets don't require to be served from the same url, because these assets are not 'real' files but
    // for simplicity the path is the same for both modes (except for the host in dev mode)
    $assets_folder_url = "/wp-content/themes/$theme_folder/{$frontend_config['distFolder']}";

    // ======= Include assets from queues
    foreach (array_unique($styles_queue) as $css_file) { // CSS (.css files don't exist in dev mode)
        wp_enqueue_style($css_file, "$assets_folder_url/$css_file", array(), null);
    }
    foreach (array_unique($scripts_queue) as $js_file) { // JS
        // $js_file is something like 'src/js/main.js' for dev and 'assets/main-123123.js' for prod
        $asset_path = ($is_dev_mode)
            ? "http://localhost:{$frontend_config['viteServerPort']}$assets_folder_url/$js_file"
            : "$assets_folder_url/$js_file";
        wp_enqueue_script($js_file, $asset_path, array(), null, true);
    }

    // not included by default, but some plugins (for example in admin bar) may include it. Dequeue is not enough
    wp_deregister_script('jquery');
}
add_action('wp_enqueue_scripts', 'theme_scripts');


// add type="module" and crossorigin to vite scripts because they are es modules (no native wp support yet)
function theme_modify_script_tag($tag, $handle) {
    if (strpos($handle, 'assets') !== false || // prod assets
        strpos($handle, 'src') !== false) { // dev assets
        // remove type if there is one (for example if there is no add_theme_support('html5, ['script']) in the theme)
        $tag = preg_replace('/ type=([\'"])[^\'"]+\1/', '', $tag);
        $tag = str_replace(' src', ' type="module" crossorigin src', $tag);
    }
    return $tag;
}
add_filter('script_loader_tag', 'theme_modify_script_tag', 10, 3);

// Add module preload directives to <head>, because vite doesn't handle wp templates
// https://vitejs.dev/guide/features.html#preload-directives-generation
function theme_add_modulepreload_tags() {
    if (theme_is_dev_server()) return; // modulepreload only for prod

    $frontend_config = theme_get_frontend_config(); // shared vars between js and php
    try {
        $manifest = theme_get_vite_manifest_data($frontend_config['distFolder']);// vite manifest
    } catch (Exception $e) {
        wp_die($e->getMessage());
    }

    $theme             = get_template();
    $assets_folder_url = "/wp-content/themes/$theme/{$frontend_config['distFolder']}";
    $entry_points      = theme_get_entry_points_for_current_page();

    $urls = [];
    foreach ($entry_points as $entry_point) {
        $urls = array_merge(
            $urls,
            theme_get_assets_from_dependencies($entry_point, $manifest, 'js')
        );
    }

    $preload_html = '';
    foreach (array_unique($urls) as $url) {
        $preload_html .= "<link rel='modulepreload' href='$assets_folder_url/$url' />\r\n";
    }
    echo $preload_html;
}
add_action('wp_head', 'theme_add_modulepreload_tags', 20);


// Pass data to js (one 'phpData' object for all the scripts and pages)
function theme_output_js_data() {
    $data = [
        'ajax_url' => admin_url('admin-ajax.php'),
    ];
    ?>
    <script type="text/javascript">
        const phpData = <?= wp_json_encode($data) ?>;
    </script>
    <?php
}
add_action('wp_head', 'theme_output_js_data');

/**
 * (Optional) Add main compiled css files to the gutenberg editor. 'current_screen' is used to avoid downloading files (and
 * displaying errors) on all pages, as would be the case with 'after_setup_theme'
 *
 * @param $screen WP_Screen
 */
function theme_add_editor_styles($screen) {
    if ($screen->base !== 'post') return; // 'post_type' is not checked, assuming all CPTs could have gutenberg

    try {
        $front_build_config = theme_get_frontend_config(); // shared variables between js and php
        $manifest           = theme_get_vite_manifest_data($front_build_config['distFolder']);// vite manifest
        $css_files          = [];
        if (isset($manifest['src/js/main-entrypoint.js']['css'])) {
            // basic html styles are in app-entrypoint which is imported in main js entry (src/js/common.js)
            $css_files = $manifest['src/js/main-entrypoint.js']['css']; // strings like '{assetsDir}/chunk-1a2b3c.css'
        }
        foreach ($css_files as $css_file) {
            add_editor_style("{$front_build_config['distFolder']}/$css_file"); // path relative to the theme!
        }
    } catch (Exception $e) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- intentional error trigger for admin area
        trigger_error($e->getMessage(), E_USER_WARNING);// don't break the entire admin page
    }
}
add_action('current_screen', 'theme_add_editor_styles');
