<?php
/**
 * Template Name: Profilo Giocatore
 * Descrizione: Scheda avanzata di un singolo giocatore.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

// ID pubblico dalla query (?id=hm_xxx)
$public_id = isset($_GET['id']) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
$nation    = isset($_GET['nation']) ? sanitize_text_field( wp_unslash( $_GET['nation'] ) ) : 'GRC';
$season    = isset($_GET['season']) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : '2024';
$comp      = isset($_GET['comp']) ? sanitize_text_field( wp_unslash( $_GET['comp'] ) ) : 'RS';

// Lasciamo il fetch dati al JS via /wp-json/hoopmetrics/v1/player
?>

<div class="hm-wrap" style="margin-top:1rem;">
  <nav class="breadcrumb" aria-label="Percorso">
    <a href="<?php echo esc_url( home_url('/') ); ?>">Home</a> ·
    <a href="<?php echo esc_url( home_url('/giocatori/') ); ?>">Giocatori</a> ·
    <span id="hm-player-breadcrumb-name">Profilo</span>
  </nav>
</div>

<div class="hm-wrap" id="hm-player-root" data-hm-player-id="<?php echo esc_attr( $public_id ); ?>" data-hm-nation="<?php echo esc_attr( $nation ); ?>" data-hm-season="<?php echo esc_attr( $season ); ?>" data-hm-comp="<?php echo esc_attr( $comp ); ?>">
  <!--
    Tutto il layout HTML del profilo giocatore verrà montato
    da JS (hm-player.js in api.js) utilizzando i componenti generici
    definiti in template-parts (player layout del messaggio precedente).

    Questo file fornisce solo il mount point e il contesto.
  -->
  <div style="padding:4rem 0;text-align:center;color:var(--hm-text-2);">
    <div style="font-size:2rem;margin-bottom:.5rem;">🏀</div>
    <p><?php _e('Caricamento profilo giocatore…','hoopmetrics'); ?></p>
  </div>
</div>

<?php get_footer(); ?>
