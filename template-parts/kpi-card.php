<?php
/** @var array $hm_kpi from set_query_var */
$val   = $hm_kpi['val']   ?? '';
$lbl   = $hm_kpi['lbl']   ?? '';
$delta = $hm_kpi['delta'] ?? null;
$icon  = $hm_kpi['icon']  ?? '📊';
$color = $hm_kpi['color'] ?? 'accent';
$style = '';
if ( $color === 'accent' ) $style = '--kpi-color: var(--hm-accent);';
if ( $color === 'blue' )   $style = '--kpi-color: var(--hm-blue);';
if ( $color === 'green' )  $style = '--kpi-color: var(--hm-green);';
if ( $color === 'purple' ) $style = '--kpi-color: var(--hm-purple);';
?>
<div class="kpi-card" style="<?php echo esc_attr( $style ); ?>">
  <div class="kpi-icon"><?php echo esc_html( $icon ); ?></div>
  <div class="kpi-val"><?php echo esc_html( $val ); ?></div>
  <div class="kpi-lbl"><?php echo esc_html( $lbl ); ?></div>
  <?php if ( $delta ) : ?>
    <?php $class = (float) $delta >= 0 ? 'delta-up' : 'delta-down'; ?>
    <div class="kpi-delta <?php echo esc_attr( $class ); ?>">
      <?php echo esc_html( $delta ); ?>
    </div>
  <?php endif; ?>
</div>
