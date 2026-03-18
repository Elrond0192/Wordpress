<?php
/**
 * Template Name: Profilo Squadra
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
$public_id = isset($_GET['team']) ? sanitize_text_field( wp_unslash( $_GET['team'] ) ) : '';
$nation    = isset($_GET['nation']) ? sanitize_text_field( wp_unslash( $_GET['nation'] ) ) : 'GRC';
$season    = isset($_GET['season']) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : '2024';
$comp      = isset($_GET['comp']) ? sanitize_text_field( wp_unslash( $_GET['comp'] ) ) : 'RS';
?>

<div class="hm-wrap" style="margin-top:1rem;">
  <nav class="breadcrumb" aria-label="Percorso">
    <a href="<?php echo esc_url( home_url('/') ); ?>">Home</a> ·
    <a href="<?php echo esc_url( home_url('/squadre/') ); ?>">Squadre</a> ·
    <span id="hm-team-breadcrumb-name">Squadra</span>
  </nav>
</div>

<div class="hm-wrap" id="hm-team-root" data-hm-team-id="<?php echo esc_attr( $public_id ); ?>" data-hm-nation="<?php echo esc_attr( $nation ); ?>" data-hm-season="<?php echo esc_attr( $season ); ?>" data-hm-comp="<?php echo esc_attr( $comp ); ?>">
  <div style="padding:4rem 0;text-align:center;color:var(--hm-text-2);">
    <div style="font-size:2rem;margin-bottom:.5rem;">🏀</div>
    <p><?php _e('Caricamento profilo squadra…','hoopmetrics'); ?></p>
  </div>
</div>

<?php get_footer(); ?>
