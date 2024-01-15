<?php
/**
 * Theme entry point
 */

if (!defined('ABSPATH')) exit;

require get_template_directory() . '/inc/setup.php'; // Theme setup
require get_template_directory() . '/inc/vite-assets.php'; // Theme scripts and styles


/**
 * Function returns a list of entrypoints that need to be included on the current page
 *
 * @return string[]
 */
function theme_get_entry_points_for_current_page(): array {
    // files will be included in the same order in which they were added
    $entry_points = array( 'src/js/main-entrypoint.js' );
    $entry_points[] = 'src/scss/style-only-entrypoint.scss'; // css-only entrypoint
    // files could be added conditionally
    if (is_front_page()) $entry_points[] = 'src/js/frontpage-entrypoint.js';
    if (is_page_template('page-templates/page-1.php')) $entry_points[] = 'src/js/page-1-entrypoint.js';
    if (is_page_template('page-templates/page-2.php')) $entry_points[] = 'src/js/page-2-entrypoint.js';
    return $entry_points;
}


// example of passing data to js (one 'phpData' object for all the scripts and pages, but data could be added conditionally)
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
add_action('wp_head', 'theme_output_js_data', 5);

/**
 * (Optional) Load main compiled css files in the gutenberg editor. 'current_screen' is used to avoid downloading files
 * (and displaying errors) on all pages, as would be the case with 'after_setup_theme'
 *
 * @param $screen WP_Screen
 */
function theme_add_editor_styles(WP_Screen $screen) {
    if ($screen->base !== 'post') return; // 'post_type' is not checked, assuming all CPTs could have gutenberg
    $main_entry = 'src/js/main-entrypoint.js';

    try {
        $frontend_config = theme_get_frontend_config(); // shared variables between js and php
        $manifest        = theme_get_vite_manifest_data($frontend_config['distFolder']);// vite manifest
        $css_files       = theme_get_styles_for_entry($main_entry, $manifest);
        if (pathinfo($manifest[ $main_entry ]['file'], PATHINFO_EXTENSION) === 'css') {
            $css_files[] = $manifest[ $main_entry ]['file']; // add if your entry is css-only
        }

        foreach ($css_files as $css_file) {
            add_editor_style("{$frontend_config['distFolder']}/$css_file"); // path relative to the theme!
        }
    } catch (Exception $e) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- intentional trigger_error for admin area
        trigger_error($e->getMessage(), E_USER_WARNING);// don't break the entire admin page
    }
}
add_action('current_screen', 'theme_add_editor_styles');
