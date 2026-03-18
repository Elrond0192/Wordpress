<?php
/** @var array $hm_player_row */
$p = $hm_player_row ?? [];
$rank = (int) ($p['rank'] ?? 0);
$name = $p['name'] ?? '';
$team = $p['team'] ?? '';
$pos  = $p['pos']  ?? '';
$rap  = $p['rap']  ?? '';
$leb  = $p['leb']  ?? '';
$bpm  = $p['bpm']  ?? '';
$net  = $p['net']  ?? '';
$min  = $p['min']  ?? '';
$pct  = (int) ($p['pct'] ?? 0);

// Avatar initials + color
$initials = strtoupper( preg_replace('/[^A-Z]/', '', substr( $name, 0, 2 ) ) );
$colors   = [
  'linear-gradient(135deg,#f97316,#c2410c)',
  'linear-gradient(135deg,#3b82f6,#1d4ed8)',
  'linear-gradient(135deg,#22c55e,#15803d)',
  'linear-gradient(135deg,#a855f7,#7e22ce)',
  'linear-gradient(135deg,#eab308,#a16207)',
  'linear-gradient(135deg,#14b8a6,#0f766e)',
];
$color_idx = ($rank - 1) % count( $colors );
$bg        = $colors[ $color_idx ];

// Percentile bar class
$pct_fill_class = 'pf-green';
if ( $pct < 75 ) $pct_fill_class = 'pf-yellow';
if ( $pct < 50 ) $pct_fill_class = 'pf-orange';
if ( $pct < 25 ) $pct_fill_class = 'pf-red';

?>
<tr>
  <td class="rank-num"><?php echo $rank; ?></td>
  <td>
    <div class="player-cell">
      <div class="p-avatar" style="background:<?php echo esc_attr( $bg ); ?>">
        <?php echo esc_html( $initials ); ?>
      </div>
      <div>
        <div class="p-name"><?php echo esc_html( $name ); ?></div>
        <div class="p-meta"><?php echo esc_html( $team ); ?> · <?php echo esc_html( $pos ); ?></div>
      </div>
    </div>
  </td>
  <td><span class="stat-mono <?php echo (float)$rap >= 0 ? 'c-pos' : 'c-neg'; ?>"><?php echo esc_html( $rap ); ?></span></td>
  <td><span class="stat-mono c-blue"><?php echo esc_html( $leb ); ?></span></td>
  <td><span class="stat-mono <?php echo (float)$bpm >= 0 ? 'c-pos' : 'c-neg'; ?>"><?php echo esc_html( $bpm ); ?></span></td>
  <td><span class="stat-mono <?php echo (float)$net >= 0 ? 'c-pos' : 'c-neg'; ?>"><?php echo esc_html( $net ); ?></span></td>
  <td><span class="stat-mono c-muted"><?php echo esc_html( $min ); ?></span></td>
  <td>
    <div class="pct-inline">
      <div class="pct-bar"><div class="pct-fill <?php echo esc_attr( $pct_fill_class ); ?>" style="width:<?php echo max(10,min(100,$pct)); ?>%"></div></div>
      <span class="pct-num"><?php echo $pct; ?>°</span>
    </div>
  </td>
</tr>
