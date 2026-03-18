<?php get_header(); ?>
<div class="hm-wrap" style="padding:6rem 1.5rem;text-align:center">
  <div style="font-size:4rem;margin-bottom:1rem">🏀</div>
  <h1 style="font-size:2.5rem;font-weight:900;margin-bottom:.75rem">404</h1>
  <p style="color:var(--hm-text-2);margin-bottom:2rem">
    <?php _e('Pagina non trovata. Forse stai cercando le stats di un giocatore svincolato?','hoopmetrics'); ?>
  </p>
  <a href="<?php echo esc_url(home_url('/')); ?>" class="hm-btn-primary">
    <?php _e('Torna alla Dashboard','hoopmetrics'); ?>
  </a>
</div>
<?php get_footer(); ?>
