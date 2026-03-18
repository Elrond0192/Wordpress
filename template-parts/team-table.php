<?php
/**
 * Tabella leaderboard squadre.
 * In prod: popolata via REST da AdvancedStats_Team_{N}_{S}_RS.
 */
?>
<div class="card">
  <div class="tab-row">
    <button class="tab-btn active" data-hm-team-metric="netrtg">Net Rating</button>
    <button class="tab-btn" data-hm-team-metric="ortg">ORtg</button>
    <button class="tab-btn" data-hm-team-metric="drtg">DRtg</button>
    <button class="tab-btn" data-hm-team-metric="pace">Pace</button>
    <button class="tab-btn" data-hm-team-metric="efg">eFG%</button>
    <button class="tab-btn" data-hm-team-metric="vsavg">vs Lg Avg</button>
  </div>
  <div class="hm-table-scroll">
    <table class="team-table hm-table" id="hm-team-table">
      <thead>
        <tr>
          <th>#</th>
          <th style="text-align:left">Squadra</th>
          <th>ORtg</th>
          <th>DRtg</th>
          <th>Net Rtg ↓</th>
          <th>Pace</th>
          <th>eFG%</th>
          <th>TS%</th>
          <th>vs Lg Avg</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sample_teams = [
          [ 'name' => 'Panathinaikos', 'short' => 'PAO', 'ortg' => 118.4, 'drtg' => 104.1, 'net' => 14.3, 'pace' => 78.2, 'efg' => 56.4, 'ts' => 62.8, 'delta' => 11.2, 'logo' => '#059669,#047857' ],
          [ 'name' => 'Olympiacos',    'short' => 'OLY', 'ortg' => 115.2, 'drtg' => 106.4, 'net' => 8.8,  'pace' => 74.8, 'efg' => 54.1, 'ts' => 60.2, 'delta' => 5.7,  'logo' => '#dc2626,#7f1d1d' ],
          [ 'name' => 'AEK Athens',    'short' => 'AEK', 'ortg' => 112.8, 'drtg' => 108.1, 'net' => 4.7,  'pace' => 72.1, 'efg' => 51.9, 'ts' => 57.4, 'delta' => 1.6,  'logo' => '#d97706,#78350f' ],
          [ 'name' => 'PAOK',          'short' => 'PAO', 'ortg' => 109.4, 'drtg' => 110.8, 'net' => -1.4, 'pace' => 70.4, 'efg' => 49.2, 'ts' => 55.1, 'delta' => -4.5, 'logo' => '#1e3a5f,#0f2040' ],
        ];
        $i = 1;
        foreach ( $sample_teams as $t ) :
          $delta_class = 'neu';
          if ( $t['delta'] > 0 ) $delta_class = 'pos';
          if ( $t['delta'] < 0 ) $delta_class = 'neg';
        ?>
        <tr>
          <td class="rank-num"><?php echo $i++; ?></td>
          <td>
            <div class="team-cell">
              <div class="team-logo-sm" style="background:linear-gradient(135deg,<?php echo esc_attr( $t['logo'] ); ?>)">
                <?php echo esc_html( $t['short'] ); ?>
              </div>
              <div>
                <div style="font-weight:600;font-size:.85rem"><?php echo esc_html( $t['name'] ); ?></div>
                <div style="font-size:.7rem;color:var(--hm-text-3)">GBL · 2024</div>
              </div>
            </div>
          </td>
          <td><span class="stat-mono <?php echo $t['ortg'] >= 110 ? 'c-pos' : 'c-muted'; ?>"><?php echo esc_html( $t['ortg'] ); ?></span></td>
          <td><span class="stat-mono <?php echo $t['drtg'] <= 108 ? 'c-pos' : 'c-muted'; ?>"><?php echo esc_html( $t['drtg'] ); ?></span></td>
          <td><span class="stat-mono <?php echo $t['net'] >= 0 ? 'c-pos' : 'c-neg'; ?>" style="font-weight:800;"><?php echo $t['net'] >= 0 ? '+' : ''; ?><?php echo esc_html( $t['net'] ); ?></span></td>
          <td><span class="stat-mono c-muted"><?php echo esc_html( $t['pace'] ); ?></span></td>
          <td><span class="stat-mono c-muted"><?php echo esc_html( $t['efg'] ); ?></span></td>
          <td><span class="stat-mono c-muted"><?php echo esc_html( $t['ts'] ); ?></span></td>
          <td><span class="delta-cell <?php echo esc_attr( $delta_class ); ?>"><?php echo $t['delta'] >= 0 ? '+' : ''; ?><?php echo esc_html( $t['delta'] ); ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
