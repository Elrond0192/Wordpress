<?php
/**
 * Template Name: Dashboard
 * Descrizione: Homepage principale con KPI, nazioni, leaderboard e squadre.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header(); ?>

<section class="hero">
  <div class="hero-inner hm-wrap">
    <div>
      <div class="hero-badge">
        <span class="pulse"></span>
        Live · Stagione 2024
      </div>
      <h1>Advanced<br><em>Basketball</em><br>Analytics</h1>
      <p>
        RAPTOR, LEBRON, BPM, On/Off Court, Home/Away splits, Win Shares e molto altro.
        Dati avanzati da più nazioni e competizioni, aggiornati in tempo reale.
      </p>
      <div class="hero-cta">
        <a href="<?php echo esc_url( home_url('/giocatori/') ); ?>" class="hm-btn-primary">Esplora giocatori →</a>
        <a href="<?php echo esc_url( home_url('/squadre/') ); ?>" class="hm-btn-ghost">Confronta squadre</a>
      </div>
    </div>
    <div class="hero-pills">
      <?php
      // In produzione questi valori arrivano via REST: /hoopmetrics/v1/dashboard/summary
      // Qui solo placeholder lato PHP.
      $summary = [
        'players'   => '1.480',
        'teams'     => '60',
        'nations'   => '5',
        'comps'     => '4',
      ];
      ?>
      <div class="hero-pill">
        <div class="hero-pill-val"><?php echo esc_html( $summary['players'] ); ?></div>
        <div class="hero-pill-lbl">Giocatori</div>
      </div>
      <div class="hero-pill">
        <div class="hero-pill-val"><?php echo esc_html( $summary['teams'] ); ?></div>
        <div class="hero-pill-lbl">Squadre</div>
      </div>
      <div class="hero-pill">
        <div class="hero-pill-val"><?php echo esc_html( $summary['nations'] ); ?></div>
        <div class="hero-pill-lbl">Nazioni</div>
      </div>
      <div class="hero-pill">
        <div class="hero-pill-val"><?php echo esc_html( $summary['comps'] ); ?></div>
        <div class="hero-pill-lbl">Competizioni</div>
      </div>
    </div>
  </div>
</section>

<!-- Filter bar → JS: hm-dashboard.js (in api.js) aggiorna tutto via REST -->
<div class="filter-bar">
  <div class="filter-bar-inner hm-wrap">
    <span class="filter-label">Nazione</span>
    <button class="filter-btn hm-filter-btn" data-hm-nation="ALL">🌍 Tutte</button>
    <button class="filter-btn hm-filter-btn active" data-hm-nation="GRC">🇬🇷 Grecia</button>
    <button class="filter-btn hm-filter-btn" data-hm-nation="ITA">🇮🇹 Italia</button>
    <button class="filter-btn hm-filter-btn" data-hm-nation="DEU">🇩🇪 Germania</button>
    <button class="filter-btn hm-filter-btn" data-hm-nation="FRA">🇫🇷 Francia</button>
    <button class="filter-btn hm-filter-btn" data-hm-nation="SPA">🇪🇸 Spagna</button>

    <div class="filter-sep"></div>
    <span class="filter-label">Stagione</span>
    <select class="filter-select" id="hm-season-select">
      <option value="2024" selected>2024</option>
    </select>

    <div class="filter-sep"></div>
    <span class="filter-label">Competizione</span>
    <button class="filter-btn hm-filter-btn active" data-hm-comp="RS">RS</button>
    <button class="filter-btn hm-filter-btn" data-hm-comp="PO">PO</button>
    <button class="filter-btn hm-filter-btn" data-hm-comp="CUP">CUP</button>
    <button class="filter-btn hm-filter-btn" data-hm-comp="SUPERCUP">SUPERCUP</button>
  </div>
</div>

<div class="main hm-wrap">

  <!-- KPI ROW (popolato via REST, ma con fallback statico) -->
  <div class="section-head">
    <h2>📈 KPI globali</h2>
    <span class="sub">Top giocatori e impatto medio</span>
  </div>
  <div class="kpi-grid">
    <?php
    $kpis = [
      [ 'val' => '+7.4', 'lbl' => 'RAPTOR medio Top-50', 'delta' => '+0.6', 'color' => 'accent', 'icon' => '📊' ],
      [ 'val' => '+9.1', 'lbl' => 'LEBRON medio Top-50', 'delta' => '+1.1', 'color' => 'blue',   'icon' => '⚡' ],
      [ 'val' => '+3.8', 'lbl' => 'Diff. Casa vs Trasferta', 'delta' => '-0.3', 'color' => 'green', 'icon' => '🏠' ],
      [ 'val' => '101.2', 'lbl' => 'Def Rtg medio OnCourt', 'delta' => '-1.4', 'color' => 'purple', 'icon' => '🛡️' ],
    ];
    foreach ( $kpis as $kpi ) {
      set_query_var( 'hm_kpi', $kpi );
      get_template_part( 'template-parts/kpi-card' );
    }
    ?>
  </div>

  <!-- NATIONS OVERVIEW (solo GRC popolata ora) -->
  <div class="section-head" style="margin-top:1.5rem;">
    <h2>🌍 Panoramica per nazione</h2>
    <span class="sub">Stagione 2024 · Regular Season</span>
  </div>
  <div class="nations-grid">
    <?php
    $nations = [
      [ 'code' => 'GRC', 'flag' => '🇬🇷', 'name' => 'Grecia',   'league' => 'GBL 2024', 'players' => 254, 'avg_raptor' => '+2.4' ],
      [ 'code' => 'ITA', 'flag' => '🇮🇹', 'name' => 'Italia',   'league' => 'LBA 2024', 'players' => null, 'avg_raptor' => null ],
      [ 'code' => 'DEU', 'flag' => '🇩🇪', 'name' => 'Germania', 'league' => 'BBL 2024', 'players' => null, 'avg_raptor' => null ],
      [ 'code' => 'FRA', 'flag' => '🇫🇷', 'name' => 'Francia',  'league' => 'Pro A 2024', 'players' => null, 'avg_raptor' => null ],
      [ 'code' => 'SPA', 'flag' => '🇪🇸', 'name' => 'Spagna',  'league' => 'ACB 2024', 'players' => null, 'avg_raptor' => null ],
    ];
    foreach ( $nations as $nation ) :
      $url = home_url( '/giocatori/?nation=' . $nation['code'] );
    ?>
      <a href="<?php echo esc_url( $url ); ?>" class="nation-card">
        <span class="nation-flag"><?php echo esc_html( $nation['flag'] ); ?></span>
        <div class="nation-name"><?php echo esc_html( $nation['name'] ); ?></div>
        <div class="nation-league"><?php echo esc_html( $nation['league'] ); ?></div>
        <div class="nation-stats">
          <?php if ( $nation['players'] ) : ?>
            <span class="nation-stat-pill"><strong><?php echo (int) $nation['players']; ?></strong> giocatori</span>
            <span class="nation-stat-pill">avg <strong><?php echo esc_html( $nation['avg_raptor'] ); ?></strong> RAP</span>
          <?php else : ?>
            <span class="nation-stat-pill"><strong>—</strong> giocatori</span>
            <span class="nation-stat-pill">Coming soon</span>
          <?php endif; ?>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- COMPETIZIONI -->
  <div class="section-head">
    <h2>🏆 Competizioni disponibili</h2>
    <span class="sub">GRC · Stagione 2024</span>
  </div>
  <div class="comp-grid">
    <?php
    $comps = [
      [ 'type' => 'RS',       'icon' => '🏀', 'name' => 'Regular Season', 'desc' => 'Stagione regolare GBL' ],
      [ 'type' => 'PO',       'icon' => '🏆', 'name' => 'Playoff',        'desc' => 'Fase ad eliminazione' ],
      [ 'type' => 'CUP',      'icon' => '🥇', 'name' => 'Cup',            'desc' => 'Coppa nazionale' ],
      [ 'type' => 'SUPERCUP', 'icon' => '⭐', 'name' => 'Supercoppa',     'desc' => 'Trofeo di inizio stagione' ],
    ];
    foreach ( $comps as $comp ) :
      $url = home_url( '/leaderboard/?nation=GRC&season=2024&comp=' . $comp['type'] );
    ?>
      <a href="<?php echo esc_url( $url ); ?>" class="comp-card">
        <div class="comp-icon"><?php echo esc_html( $comp['icon'] ); ?></div>
        <div class="comp-info">
          <div class="comp-name"><?php echo esc_html( $comp['name'] ); ?></div>
          <div class="comp-desc"><?php echo esc_html( $comp['desc'] ); ?></div>
        </div>
        <span class="comp-badge"><?php echo esc_html( $comp['type'] ); ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- LEADERBOARD + SIDEBAR -->
  <div class="two-col">

    <!-- Leaderboard Giocatori (Top 7 GRC 2024 RS) -->
    <section>
      <div class="section-head">
        <h2>🏅 Leaderboard giocatori</h2>
        <a href="<?php echo esc_url( home_url('/leaderboard/') ); ?>" class="link-all">Vedi tutti →</a>
      </div>
      <div class="card">
        <div class="tab-row">
          <button class="tab-btn active" data-hm-metric="raptor">RAPTOR</button>
          <button class="tab-btn" data-hm-metric="lebron">LEBRON</button>
          <button class="tab-btn" data-hm-metric="bpm">BPM</button>
          <button class="tab-btn" data-hm-metric="netrtg">Net Rtg</button>
          <button class="tab-btn" data-hm-metric="ws">Win Shares</button>
        </div>
        <div class="hm-table-scroll">
          <table class="lb-table hm-table" id="hm-lb-players">
            <thead>
              <tr>
                <th>#</th>
                <th style="text-align:left">Giocatore</th>
                <th>RAPTOR ↓</th>
                <th>LEBRON</th>
                <th>BPM</th>
                <th>Net Rtg</th>
                <th>Min</th>
                <th>Percentile</th>
              </tr>
            </thead>
            <tbody>
              <?php
              // Placeholder statico: in prod viene popolato via REST → hm-api.js
              $sample_players = [
                [ 'name' => 'A. Koulis',     'team' => 'Panathinaikos', 'pos' => 'SG', 'rap' => '+8.4', 'leb' => '+9.7', 'bpm' => '+4.1', 'net' => '+6.2', 'min' => '31.4', 'pct' => 94 ],
                [ 'name' => 'N. Papas',      'team' => 'Olympiacos',    'pos' => 'PF', 'rap' => '+7.1', 'leb' => '+8.2', 'bpm' => '+3.6', 'net' => '+5.4', 'min' => '28.9', 'pct' => 88 ],
                [ 'name' => 'D. Mitoglou',   'team' => 'AEK Athens',    'pos' => 'SF', 'rap' => '+6.8', 'leb' => '+7.4', 'bpm' => '+3.2', 'net' => '+4.9', 'min' => '27.3', 'pct' => 83 ],
                [ 'name' => 'G. Spanoulis',  'team' => 'PAOK',          'pos' => 'PG', 'rap' => '+5.2', 'leb' => '+6.1', 'bpm' => '+2.9', 'net' => '+3.8', 'min' => '24.1', 'pct' => 74 ],
                [ 'name' => 'T. Kalaitzakis','team' => 'Aris',          'pos' => 'SG', 'rap' => '+4.4', 'leb' => '+5.0', 'bpm' => '+2.1', 'net' => '+2.9', 'min' => '26.8', 'pct' => 66 ],
                [ 'name' => 'M. Print',      'team' => 'Kolossos',      'pos' => 'C',  'rap' => '+3.1', 'leb' => '+3.8', 'bpm' => '+1.4', 'net' => '+1.8', 'min' => '22.5', 'pct' => 58 ],
                [ 'name' => 'V. Kouzelís',   'team' => 'Lavrio',        'pos' => 'PG', 'rap' => '-1.2', 'leb' => '-0.8', 'bpm' => '-1.8', 'net' => '-2.1', 'min' => '19.2', 'pct' => 22 ],
              ];
              $rank = 1;
              foreach ( $sample_players as $p ) {
                set_query_var( 'hm_player_row', [
                  'rank' => $rank++, 'name' => $p['name'], 'team' => $p['team'], 'pos' => $p['pos'],
                  'rap'  => $p['rap'], 'leb' => $p['leb'], 'bpm' => $p['bpm'], 'net' => $p['net'],
                  'min'  => $p['min'], 'pct' => $p['pct'],
                ] );
                get_template_part( 'template-parts/player-row' );
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Sidebar (MVP + Home/Away) -->
    <aside class="sidebar">
      <?php get_template_part( 'template-parts/sidebar-mvp' ); ?>
      <?php get_template_part( 'template-parts/sidebar-homeaway' ); ?>
    </aside>
  </div>

  <!-- Leaderboard squadre -->
  <div class="section-head">
    <h2>🏟️ Leaderboard squadre</h2>
    <a href="<?php echo esc_url( home_url('/squadre/') ); ?>" class="link-all">Vedi tutte →</a>
  </div>
  <?php get_template_part( 'template-parts/team-table' ); ?>

</div><!-- /.main -->

<?php get_footer(); ?>
