<?php
/**
 * functions.php – HoopMetrics Court Analytics Pro
 * Fix enqueue: usa get_stylesheet_directory_uri() NON get_template_directory_uri()
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Costanti tema ─────────────────────────────────────────────────────────
define( 'HM_THEME_VERSION', '1.0.2' );
define( 'HM_THEME_DIR',     get_stylesheet_directory() );
define( 'HM_THEME_URI',     get_stylesheet_directory_uri() );

// ── Enqueue CSS e JS ──────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {

    // CSS principale
    wp_enqueue_style(
        'hm-style',
        HM_THEME_URI . '/style.css',
        [],
        HM_THEME_VERSION
    );

    // main.js  (tema corrente, NON Astra)
    wp_enqueue_script(
        'hm-main',
        HM_THEME_URI . '/assets/js/main.js',
        [],                  // nessuna dipendenza
        HM_THEME_VERSION,
        true                 // footer = true
    );

    // api.js – dipende da main.js
    wp_enqueue_script(
        'hm-api',
        HM_THEME_URI . '/assets/js/api.js',
        [ 'hm-main' ],
        HM_THEME_VERSION,
        true
    );

	wp_enqueue_style('hm-leaderboard-extra',
    HM_PLUGIN_URL . 'assets/css/leaderboard-extra.css', [], '1.0');

    // Inietta configurazione JS (nonce, URL API, stato default)
    wp_localize_script( 'hm-main', 'HM_CONFIG', [
        'rest_url'       => esc_url_raw( rest_url( 'hoopmetrics/v1' ) ),
        'nonce'          => wp_create_nonce( 'wp_rest' ),
        'default_nation' => 'GRC',
        'default_year'   => '2024',
        'seasons'        => [ '2024', '2023', '2022' ],
        'ajax_url'       => admin_url( 'admin-ajax.php' ),
    ] );

} );

// ── Theme supports ────────────────────────────────────────────────────────
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form','comment-form','gallery','caption' ] );
} );

// ── Registra menu ─────────────────────────────────────────────────────────
add_action( 'after_setup_theme', function () {
    register_nav_menus( [
        'primary' => 'Menu Principale HoopMetrics',
        'footer'  => 'Footer HoopMetrics',
    ] );
} );

// ── Rimuovi versione WP dall'head (sicurezza) ─────────────────────────────
remove_action( 'wp_head', 'wp_generator' );
