<?php
/**
 * Theme entry point
 */

if (!defined('ABSPATH')) exit;

require get_template_directory() . '/inc/helpers/assets.php'; // Helpers
require get_template_directory() . '/inc/setup.php'; // Theme setup
require get_template_directory() . '/inc/enqueue.php'; // Theme scripts and styles
