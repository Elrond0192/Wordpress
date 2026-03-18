<!DOCTYPE html>
<html <?php language_attributes(); ?> data-theme="dark">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <?php wp_head(); // OBBLIGATORIO - carica CSS, JS e tutto il resto ?>
</head>
<body <?php body_class('hm-body'); ?>>
<?php wp_body_open(); ?>

<nav id="hm-navbar">
  <div class="hm-wrap hm-nav-inner">

    <a href="<?php echo esc_url( home_url('/') ); ?>" class="hm-logo">
      <span class="hm-logo-icon">🏀</span>
      Court<span style="color:var(--hm-text)">Analytics</span>
    </a>

    <div class="hm-nav-links">
      <?php
      $items = [
        home_url('/')              => 'Dashboard',
        home_url('/leaderboard/') => 'Leaderboard',
        home_url('/squadra/')     => 'Squadre',
        home_url('/confronto/')   => 'Confronto',
      ];
      $current = trailingslashit( home_url( add_query_arg([]) ) );
      foreach ( $items as $url => $label ) :
        $is_active = ( $current === trailingslashit($url) );
      ?>
        <a href="<?php echo esc_url($url); ?>"
           class="hm-nav-link <?php echo $is_active ? 'active' : ''; ?>">
          <?php echo esc_html($label); ?>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="hm-nav-actions">
      <div style="position:relative">
        <input id="hm-global-search"
               type="search"
               placeholder="Cerca giocatore…"
               autocomplete="off"
               class="hm-search-input">
        <div id="hm-search-results" class="hm-search-dropdown" hidden></div>
      </div>
      <button id="hm-theme-toggle" class="hm-theme-btn" aria-label="Cambia tema">
        <span id="hm-theme-icon">🌙</span>
      </button>
    </div>

  </div>
</nav>
