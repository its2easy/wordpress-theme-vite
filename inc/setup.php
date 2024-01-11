<?php
add_action('after_setup_theme', 'theme_after_setup_theme');
function theme_after_setup_theme() {
    load_theme_textdomain('wordpress-theme-vite', get_template_directory() . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    // This theme uses wp_nav_menu() in one location.
    register_nav_menus(
        array(
            'primary' => 'Primary',
        )
    );

    add_theme_support(
        'html5',
        array(
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'script', // removes useless default type='text/javascript' for enqueued scripts
            'style',
        )
    );


    add_theme_support('responsive-embeds');

    // Gutenberg
    add_theme_support('wp-block-styles'); // includes inline dist/block-library/theme.min.css
    add_theme_support('editor-styles');
}

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
