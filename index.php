<?php
/**
 * Homepage / index fallback – Court Analytics Pro
 */
get_header(); ?>

<main>
  <?php if ( is_home() || is_front_page() ) : ?>

    <div class="hm-welcome">
      <div style="font-size:4rem;margin-bottom:1.5rem">🏀</div>
      <h1>Court<em>Analytics</em> Pro</h1>
      <p>Statistiche avanzate di basket: RAPTOR, LEBRON, OnCourt, splits Casa/Trasferta e molto altro.</p>

      <div class="hm-welcome-grid">
        <div class="hm-welcome-pill"><div class="hm-welcome-pill-val">5</div><div class="hm-welcome-pill-lbl">Nazioni</div></div>
        <div class="hm-welcome-pill"><div class="hm-welcome-pill-val">4</div><div class="hm-welcome-pill-lbl">Competizioni</div></div>
        <div class="hm-welcome-pill"><div class="hm-welcome-pill-val">20+</div><div class="hm-welcome-pill-lbl">Metriche</div></div>
        <div class="hm-welcome-pill"><div class="hm-welcome-pill-val">∞</div><div class="hm-welcome-pill-lbl">Split</div></div>
      </div>

      <div style="display:flex;gap:.75rem;flex-wrap:wrap;justify-content:center">
        <a href="<?php echo esc_url( home_url('/leaderboard/') ); ?>" class="hm-btn hm-btn-primary">
          🏆 Vai alla Leaderboard
        </a>
        <?php if ( current_user_can('manage_options') ) : ?>
          <a href="<?php echo esc_url( admin_url('admin.php?page=hoopmetrics-diagnostic') ); ?>" class="hm-btn hm-btn-ghost">
            ⚙️ Diagnostica
          </a>
        <?php endif; ?>
      </div>
    </div>

  <?php else : ?>

    <div class="hm-wrap" style="padding:3rem 0">
      <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <article class="hm-card" style="padding:2rem;margin-bottom:1.5rem">
          <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:.75rem">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
          </h2>
          <div style="color:var(--hm-text-2)"><?php the_excerpt(); ?></div>
        </article>
      <?php endwhile; endif; ?>
    </div>

  <?php endif; ?>
</main>

<?php get_footer(); ?>
