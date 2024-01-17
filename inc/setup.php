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

// don't clutter devtools
remove_action('wp_head', 'print_emoji_detection_script', 7);

add_action('widgets_init', 'theme_widgets_init');
function theme_widgets_init() {
    register_sidebar(
        array(
            'name'          => 'Sidebar',
            'id'            => 'sidebar-1',
            'before_widget' => '<section id="%1$s" class="widget %2$s">',
            'after_widget'  => '</section>',
            'before_title'  => '<h2 class="widget-title">',
            'after_title'   => '</h2>',
        )
    );
}
