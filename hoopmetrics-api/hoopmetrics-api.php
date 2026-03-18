<?php
/**
 * Plugin Name:  HoopMetrics API
 * Description:  REST API per statistiche basket avanzate – Azure SQL backend.
 * Version:      1.0.0
 * Author:       Court Analytics Pro
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── Costanti plugin ────────────────────────────────────────────────────────
define( 'HM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HM_PLUGIN_URL', plugin_dir_url(  __FILE__ ) );

// Verifica che HM_ID_SALT sia definito (obbligatorio in wp-config.php)
if ( ! defined( 'HM_ID_SALT' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>HoopMetrics API:</strong> aggiungi <code>define(\'HM_ID_SALT\', \'...\')</code> in <code>wp-config.php</code>.'
            . '</p></div>';
    } );
}

// ── Autoload ───────────────────────────────────────────────────────────────
// ORDINE OBBLIGATORIO: helpers → db → query-builder → models → rest-api
$includes = [
    'includes/helpers.php',           // hm_age_from_birthdate(), hm_public_id()
    'includes/class-db.php',          // HM_DB  (connessione Azure SQL PDO)
    'includes/class-query-builder.php',// HM_Query_Builder
	'includes/class-anonymizer.php',  // HM_Anonymizer
    'includes/class-leaderboard.php', // HM_Leaderboard
    'includes/class-players.php',     // HM_Players
    'includes/class-teams.php',       // HM_Teams
    'includes/class-search.php',      // HM_Search
    'includes/class-rest-api.php',    // HM_Rest_API  (deve stare per ultima)
	
];

foreach ( $includes as $file ) {
    $path = HM_PLUGIN_DIR . $file;
    if ( ! file_exists( $path ) ) {
        // Log preciso: quale file manca
        error_log( "[HoopMetrics] File mancante: {$path}" );
        add_action( 'admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>'
                . "<strong>HoopMetrics API:</strong> file mancante: <code>{$file}</code>"
                . '</p></div>';
        });
        continue;
    }
    require_once $path;
}

// ── Registra REST routes ───────────────────────────────────────────────────
add_action( 'rest_api_init', function () {
    if ( class_exists( 'HM_Rest_API' ) ) {
        ( new HM_Rest_API() )->register_routes();
    } else {
        error_log( '[HoopMetrics] HM_Rest_API class non trovata — controlla class-rest-api.php' );
    }
} );

// ── Flush rewrite rules solo all'attivazione ───────────────────────────────
register_activation_hook( __FILE__, function () {
    flush_rewrite_rules();
    error_log( '[HoopMetrics] Plugin attivato ✅' );
} );

require_once plugin_dir_path(__FILE__) . 'includes/class-hm-admin.php';
if ( is_admin() ) {
    HM_Admin::init();
}
