<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <header>
        <nav class="navbar navbar-expand-lg bg-light">
            <div class="container">
                <div class="header__logo"></div>

                <?php
                $locations  = get_nav_menu_locations();
                if (isset($locations[ 'primary' ])) {
                    $menu       = wp_get_nav_menu_object($locations[ 'primary' ]);
                    $menu_items = wp_get_nav_menu_items($menu);
                }
                if (isset($locations[ 'primary' ]) && isset($menu_items)) :
                ?>
                <ul class="navbar-nav mx-3">
                    <?php foreach ($menu_items as $menu_item) : ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $menu_item->url ?>"><?= $menu_item->title ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <span class="navbar-text">This is header</span>
            </div>
        </nav>
    </header>
