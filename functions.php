<?php
/**
 * functions.php – HoopMetrics Court Analytics Pro
 * v1.1.0 — Fix: sicurezza header, enqueue indipendente dal plugin, security hardening.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Costanti tema ─────────────────────────────────────────────────────────
define( 'HM_THEME_VERSION', '1.0.3' );
define( 'HM_THEME_DIR',     get_stylesheet_directory() );
define( 'HM_THEME_URI',     get_stylesheet_directory_uri() );

// ── Enqueue CSS e JS ──────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', function () {

    // CSS principale — sempre dal tema, mai dal plugin
    wp_enqueue_style(
        'hm-style',
        HM_THEME_URI . '/style.css',
        [],
        HM_THEME_VERSION
    );

    // CSS leaderboard extra — caricato dal tema se esiste, altrimenti dal plugin
    $lb_css_theme  = HM_THEME_DIR . '/assets/css/leaderboard-extra.css';
    $lb_css_source = file_exists($lb_css_theme)
        ? HM_THEME_URI . '/assets/css/leaderboard-extra.css'
        : ( defined('HM_PLUGIN_URL') ? HM_PLUGIN_URL . 'assets/css/leaderboard-extra.css' : '' );

    if ( $lb_css_source ) {
        wp_enqueue_style(
            'hm-leaderboard-extra',
            $lb_css_source,
            ['hm-style'],
            HM_THEME_VERSION
        );
    }

    // Chart.js — caricato nell'<head> sulle pagine che lo usano (profilo giocatore)
    // File locale nella cartella del tema — non dipende da CDN esterni
    if ( is_page_template( 'page-templates/player.php' ) ) {
        wp_enqueue_script(
            'chartjs',
            HM_THEME_URI . '/assets/js/chart.umd.min.js',
            [],
            '4.4.1',
            false // <-- in <head>, non in footer
        );
    }

    // main.js — sempre dal tema corrente, non da Astra o da altri parent theme
    wp_enqueue_script(
        'hm-main',
        HM_THEME_URI . '/assets/js/main.js',
        [],
        HM_THEME_VERSION,
        true
    );

    // api.js — dipende da main.js
    wp_enqueue_script(
        'hm-api',
        HM_THEME_URI . '/assets/js/api.js',
        ['hm-main'],
        HM_THEME_VERSION,
        true
    );

    // Configurazione JS — nonce incluso per autenticare le REST API
    wp_localize_script( 'hm-main', 'HM_CONFIG', [
        'rest_url'       => esc_url_raw( rest_url( 'hoopmetrics/v1' ) ),
        'nonce'          => wp_create_nonce( 'wp_rest' ),
        'default_nation' => 'GRC',
        'default_year'   => '2024',
        'seasons'        => [ '2025', '2024', '2023', '2022' ],
        'ajax_url'       => admin_url( 'admin-ajax.php' ),
        'version'        => HM_THEME_VERSION,
    ]);

});

// ── Header di sicurezza ───────────────────────────────────────────────────
add_action( 'send_headers', function () {

    // Non indicizzare questo sito (dati privati)
    header('X-Robots-Tag: noindex, nofollow, noarchive');

    // Protezioni standard
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Nasconde il fatto che il sito usi WordPress
    header_remove('X-Powered-By');

    // Content Security Policy — permissiva in sviluppo per non bloccare REST API e Chart.js
    // In produzione restringere connect-src e script-src
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "connect-src *",   // permette fetch verso wp-json su qualsiasi porta/path (dev)
        "font-src 'self'",
        "frame-ancestors 'none'",
    ]);
    header("Content-Security-Policy: {$csp}");

});

// ── Rimuovi metadati WP che rivelano versione e struttura ─────────────────
remove_action( 'wp_head', 'wp_generator' );           // <meta name="generator">
remove_action( 'wp_head', 'rsd_link' );               // Really Simple Discovery
remove_action( 'wp_head', 'wlwmanifest_link' );       // Windows Live Writer
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'rest_output_link_wp_head' );  // nasconde URL REST dall'head
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

// ── Theme supports ────────────────────────────────────────────────────────
add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', ['search-form','comment-form','gallery','caption'] );
});

// ── Registra menu di navigazione ──────────────────────────────────────────
add_action( 'after_setup_theme', function () {
    register_nav_menus([
        'primary' => 'Menu Principale HoopMetrics',
        'footer'  => 'Footer HoopMetrics',
    ]);
});

// ── Blocca accesso diretto ai file PHP del tema via URL ───────────────────
// (Nginx/Apache rewrite preferibili, ma questo è un fallback WP-level)
add_action( 'init', function () {
    // Blocca tentativi di accesso diretto a file PHP sensibili
    $request = $_SERVER['REQUEST_URI'] ?? '';
    if ( preg_match('/diagnostic\.php|debug\.php/i', $request) ) {
        status_header(404);
        nocache_headers();
        exit('Not found.');
    }
});