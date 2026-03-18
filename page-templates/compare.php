<?php
/**
 * Template Name: Confronto Giocatori
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header(); ?>

<div class="hm-wrap" style="margin-top:1rem;">
  <nav class="breadcrumb" aria-label="Percorso">
    <a href="<?php echo esc_url( home_url('/') ); ?>">Home</a> ·
    <span><?php _e('Confronto giocatori','hoopmetrics'); ?></span>
  </nav>
</div>

<div class="hm-wrap" id="hm-compare-root" style="margin-top:1.5rem;">
  <header class="section-head">
    <div>
      <h2>⚔️ Confronto giocatori</h2>
      <p class="sub">Seleziona due giocatori per confrontare RAPTOR, LEBRON, On/Off, efficienza e splits.</p>
    </div>
  </header>

  <!-- Mount point gestito da JS (hm-compare.js in api.js) -->
  <div style="padding:3rem 0;text-align:center;color:var(--hm-text-2);">
    <div style="font-size:2rem;margin-bottom:.75rem;">🏀</div>
    <p><?php _e('Interfaccia di confronto in caricamento…','hoopmetrics'); ?></p>
  </div>
</div>

<?php get_footer(); ?>
