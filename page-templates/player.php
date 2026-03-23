<?php
/**
 * Template Name: Profilo Giocatore
 * Slug: giocatore
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$public_id = isset($_GET['id'])     ? sanitize_text_field(wp_unslash($_GET['id']))     : '';
$nation    = isset($_GET['nation']) ? sanitize_text_field(wp_unslash($_GET['nation'])) : 'GRC';
$year      = isset($_GET['year'])   ? sanitize_text_field(wp_unslash($_GET['year']))   : '2024';
$comp      = isset($_GET['comp'])   ? sanitize_text_field(wp_unslash($_GET['comp']))   : 'TOT';

$lb_url  = '';
foreach (['leaderboard'] as $s) { $p = get_page_by_path($s); if ($p) { $lb_url = get_permalink($p->ID); break; } }
if (!$lb_url) $lb_url = home_url('/leaderboard/');
$team_url = '';
foreach (['squadra','team'] as $s) { $p = get_page_by_path($s); if ($p) { $team_url = trailingslashit(get_permalink($p->ID)); break; } }
if (!$team_url) $team_url = home_url('/squadra/');

if (!$public_id):
?>
<div class="hm-wrap" style="padding:6rem 0;text-align:center">
  <h2 style="color:var(--hm-text)">Nessun giocatore specificato</h2>
  <a href="<?php echo esc_url($lb_url); ?>" class="hm-btn hm-btn-primary" style="margin-top:1.5rem;display:inline-block">← Leaderboard</a>
</div>
<?php get_footer(); return; endif; ?>

<style>
/* ═══════════════════════════════════════════════════════
   SEMANTIC COLOR TOKENS
   ═══════════════════════════════════════════════════════ */
:root {
  --s-off:    #f97316;  /* offensivo / accento principale */
  --s-off-d:  rgba(249,115,22,.12);
  --s-off-b:  rgba(249,115,22,.28);
  --s-def:    #60a5fa;  /* difensivo */
  --s-def-d:  rgba(96,165,250,.12);
  --s-def-b:  rgba(96,165,250,.28);
  --s-model:  #a78bfa;  /* modelli compositi RAPTOR/LEBRON/BPM */
  --s-model-d:rgba(167,139,250,.12);
  --s-model-b:rgba(167,139,250,.28);
  --s-eff:    #fde047;  /* efficienza TS%/eFG% */
  --s-eff-d:  rgba(253,224,71,.1);
  --s-eff-b:  rgba(253,224,71,.25);
  --s-green:  #4ade80;
  --s-red:    #f87171;
  --s-gray:   #94a3b8;
}

/* ── Page layout ──────────────────────────────────────── */
.hpg { padding: 1.5rem 0 5rem }
.hpg-bc { display:flex;align-items:center;gap:.35rem;font-size:.78rem;color:var(--hm-text-3);margin-bottom:1.25rem }
.hpg-bc a { color:var(--hm-text-3);text-decoration:none } .hpg-bc a:hover { color:var(--s-off) }
.hpg-bc svg { width:10px;height:10px;opacity:.3 }

/* ── Hero ─────────────────────────────────────────────── */
.hpg-hero { background:var(--hm-bg-card);border:1px solid var(--hm-border);border-radius:16px;overflow:hidden;margin-bottom:1.1rem;position:relative }
.hpg-hero::before { content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 0% 60%,rgba(249,115,22,.13) 0,transparent 55%);pointer-events:none;z-index:0 }
.hpg-hero-top { position:relative;z-index:1;display:grid;grid-template-columns:80px 1fr auto;gap:1.25rem;padding:1.5rem 1.5rem 1.1rem;align-items:start }
.hpg-av { width:80px;height:80px;border-radius:14px;background:linear-gradient(135deg,#f97316,#c2410c);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:900;color:#fff;letter-spacing:-.04em;box-shadow:0 8px 24px rgba(249,115,22,.3);flex-shrink:0 }
.hpg-name { font-size:1.65rem;font-weight:900;letter-spacing:-.05em;line-height:1.05;margin-bottom:.5rem }
.hpg-meta-row { display:flex;flex-wrap:wrap;align-items:center;gap:.15rem .05rem }
.hpg-mi { display:flex;align-items:center;gap:.28rem;font-size:.79rem }
.hpg-ml { color:var(--hm-text-3);font-size:.67rem;text-transform:uppercase;letter-spacing:.07em }
.hpg-mv { font-weight:700;color:var(--hm-text) }
.hpg-sep { width:1px;height:13px;background:var(--hm-border);margin:0 .2rem }
.hpg-roles { display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.55rem }
.hpg-role { display:inline-flex;align-items:center;gap:.3rem;padding:.18rem .6rem;border-radius:6px;font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;border:1px solid }
.hpg-role-off   { background:var(--s-off-d);border-color:var(--s-off-b);color:var(--s-off) }
.hpg-role-def   { background:var(--s-def-d);border-color:var(--s-def-b);color:var(--s-def) }
.hpg-role-combo { background:var(--s-model-d);border-color:var(--s-model-b);color:var(--s-model) }

/* KPI strip with semantic KPI colors */
.hpg-kpis { display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--hm-border);border-top:1px solid var(--hm-border) }
.hpg-kpi  { background:var(--hm-bg-card);padding:.75rem .5rem;text-align:center;transition:background .15s }
.hpg-kpi:hover { background:var(--hm-bg-hover) }
.hpg-kv { font-family:var(--hm-mono);font-weight:800;font-size:1.15rem;letter-spacing:-.04em;line-height:1 }
.hpg-kl { font-size:.59rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.07em;margin-top:3px }

/* ── Filters ──────────────────────────────────────────── */
.hpg-filters { display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap }
.hpg-fl { font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--hm-text-3) }
.hpg-sel { appearance:none;background:var(--hm-bg-card) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right .55rem center;border:1.5px solid var(--hm-border);border-radius:8px;padding:.32rem 1.7rem .32rem .6rem;font-size:.78rem;font-weight:500;color:var(--hm-text);cursor:pointer }
.hpg-sel:focus { outline:none;border-color:rgba(249,115,22,.5) }

/* ── Tabs ─────────────────────────────────────────────── */
.hpg-tabs { display:flex;gap:2px;border-bottom:1px solid var(--hm-border);margin-bottom:1.25rem;overflow-x:auto;scrollbar-width:none }
.hpg-tabs::-webkit-scrollbar { display:none }
.hpg-tab { display:flex;align-items:center;gap:.35rem;padding:.6rem 1rem;font-size:.8rem;font-weight:600;color:var(--hm-text-3);background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;transition:all .15s;white-space:nowrap;font-family:inherit;margin-bottom:-1px }
.hpg-tab:hover { color:var(--hm-text-2) }
.hpg-tab.active { color:var(--s-off);border-bottom-color:var(--s-off) }
.hpg-panel { display:none } .hpg-panel.active { display:block }

/* ── Semantic section cards ───────────────────────────── */
.hpg-card { background:var(--hm-bg-card);border:1px solid var(--hm-border);border-radius:14px;overflow:hidden;margin-bottom:1rem }
/* Colored top border per tema */
.hpg-card-off   { border-top:2px solid var(--s-off) }
.hpg-card-def   { border-top:2px solid var(--s-def) }
.hpg-card-model { border-top:2px solid var(--s-model) }
.hpg-card-eff   { border-top:2px solid var(--s-eff) }
.hpg-card-green { border-top:2px solid var(--s-green) }

.hpg-ch { display:flex;align-items:center;justify-content:space-between;padding:.6rem .9rem;border-bottom:1px solid var(--hm-border);font-size:.77rem;font-weight:700;background:rgba(15,23,42,.35) }
.hpg-ch-title { display:flex;align-items:center;gap:.4rem }
.hpg-cb { padding:.85rem }
.hpg-cb-flush { padding:0;overflow-x:auto }

/* ── Grid helpers ─────────────────────────────────────── */
.hpg-2col { display:grid;grid-template-columns:1fr 300px;gap:1rem }
.hpg-2col-eq { display:grid;grid-template-columns:1fr 1fr;gap:1rem }

/* ── Tables ───────────────────────────────────────────── */
.hpg-t { width:100%;border-collapse:collapse;font-size:.77rem }
.hpg-t th { font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--hm-text-3);padding:.42rem .65rem;text-align:right;border-bottom:1px solid var(--hm-border);white-space:nowrap }
.hpg-t th.l, .hpg-t td.l { text-align:left }
.hpg-t td { padding:.42rem .65rem;text-align:right;border-bottom:1px solid var(--hm-border);font-family:var(--hm-mono);font-size:.76rem;vertical-align:middle }
.hpg-t tr:last-child td { border-bottom:none }
.hpg-t tbody tr:hover td { background:var(--hm-bg-hover) }
.hpg-t .td-name { font-family:inherit;font-weight:600;color:var(--hm-text);font-size:.78rem }
.hpg-t .td-comp { font-family:inherit;font-weight:700;color:var(--hm-text-2) }
.hpg-t tr.row-tot td { background:rgba(249,115,22,.05) }

/* ── Semantic stat cells ──────────────────────────────── */
.hpg-stat-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.4rem }
.hpg-sc { border-radius:9px;padding:.5rem .65rem;border:1px solid }
.hpg-sc-off   { background:rgba(249,115,22,.07);border-color:rgba(249,115,22,.2) }
.hpg-sc-def   { background:rgba(96,165,250,.07);border-color:rgba(96,165,250,.2) }
.hpg-sc-model { background:rgba(167,139,250,.07);border-color:rgba(167,139,250,.2) }
.hpg-sc-eff   { background:rgba(253,224,71,.06);border-color:rgba(253,224,71,.18) }
.hpg-sc-base  { background:var(--hm-bg-2);border-color:var(--hm-border) }
.hpg-sl { font-size:.62rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.2rem }
.hpg-sv { font-family:var(--hm-mono);font-weight:700;font-size:.95rem }
/* sparkline inside stat cell */
.hpg-spark { display:flex;align-items:flex-end;gap:1px;height:20px;margin-top:.3rem }
.hpg-spark-bar { width:3px;border-radius:1px;min-height:2px;transition:height .3s }

/* ── On/Off ───────────────────────────────────────────── */
.hpg-onoff { display:grid;grid-template-columns:1fr auto 1fr;gap:.75rem;align-items:start }
.hpg-oo-col { text-align:center }
.hpg-oo-badge { display:inline-block;font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;padding:.18rem .6rem;border-radius:999px;margin-bottom:.65rem }
.hpg-oo-on  { background:rgba(74,222,128,.1);color:#4ade80;border:1px solid rgba(74,222,128,.25) }
.hpg-oo-off { background:rgba(148,163,184,.1);color:#94a3b8;border:1px solid rgba(148,163,184,.2) }
.hpg-oo-stat { margin-bottom:.6rem }
.hpg-oo-val { font-family:var(--hm-mono);font-weight:800;font-size:1.1rem }
.hpg-oo-lbl { font-size:.62rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:2px }
.hpg-oo-div { display:flex;flex-direction:column;align-items:center;gap:.6rem;color:var(--hm-text-3);font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;padding-top:1.8rem }
.hpg-oo-diff { font-family:var(--hm-mono);font-weight:800;font-size:.8rem;padding:.12rem .4rem;border-radius:5px }
.oo-pos { background:rgba(74,222,128,.12);color:#4ade80 }
.oo-neg { background:rgba(248,113,113,.12);color:#f87171 }
.oo-neu { background:rgba(148,163,184,.08);color:#94a3b8 }
.hpg-oo-raw { width:100%;border-collapse:collapse;font-size:.73rem }
.hpg-oo-raw th { font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--hm-text-3);padding:.35rem .5rem;text-align:right;border-bottom:1px solid var(--hm-border) }
.hpg-oo-raw th:first-child { text-align:left }
.hpg-oo-raw td { padding:.35rem .5rem;text-align:right;border-bottom:1px solid var(--hm-border);font-family:var(--hm-mono);font-size:.72rem }
.hpg-oo-raw tr:last-child td { border-bottom:none }

/* ── Percentile bars ──────────────────────────────────── */
.hpg-pct-row { margin-bottom:.6rem }
.hpg-pct-head { display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.25rem }
.hpg-pct-name { font-size:.73rem;font-weight:600;color:var(--hm-text-2) }
.hpg-pct-num  { font-family:var(--hm-mono);font-size:.73rem;font-weight:700 }
.hpg-pct-track { height:5px;background:rgba(255,255,255,.06);border-radius:999px;overflow:hidden }
.hpg-pct-fill  { height:100%;border-radius:999px;transition:width .7s cubic-bezier(.4,0,.2,1) }

/* ── Chart containers ─────────────────────────────────── */
.hpg-chart-wrap    { position:relative;height:220px }
.hpg-chart-wrap-md { position:relative;height:180px }
.hpg-chart-wrap-sm { position:relative;height:155px }
.hpg-chart-wrap canvas, .hpg-chart-wrap-md canvas, .hpg-chart-wrap-sm canvas { width:100%!important }

/* Chart metric switcher pills */
.hpg-metric-pills { display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:.75rem }
.hpg-mpill { padding:.22rem .65rem;border-radius:999px;border:1.5px solid var(--hm-border);background:transparent;font-size:.72rem;font-weight:600;color:var(--hm-text-3);cursor:pointer;transition:all .15s;font-family:inherit }
.hpg-mpill:hover { border-color:rgba(249,115,22,.4);color:var(--hm-text-2) }
.hpg-mpill.active { background:rgba(249,115,22,.12);border-color:rgba(249,115,22,.4);color:var(--s-off) }

/* ── Game badge ───────────────────────────────────────── */
.hpg-badge { display:inline-block;font-size:.6rem;font-weight:700;padding:1px 5px;border-radius:4px;vertical-align:middle;margin-left:3px }
.hpg-bh { background:rgba(96,165,250,.12);color:#60a5fa }
.hpg-ba { background:rgba(148,163,184,.08);color:#94a3b8 }
.hpg-bs { background:rgba(249,115,22,.12);color:#fb923c }

/* ── States ───────────────────────────────────────────── */
.hpg-loading { display:flex;align-items:center;justify-content:center;padding:2.5rem;gap:.65rem;color:var(--hm-text-3);font-size:.8rem }
.hpg-spin { width:18px;height:18px;border:2px solid var(--hm-border);border-top-color:var(--s-off);border-radius:50%;animation:hpg-spin .7s linear infinite;flex-shrink:0 }
@keyframes hpg-spin { to { transform:rotate(360deg) } }
.hpg-error { padding:1.5rem;text-align:center;color:#f87171;font-size:.8rem }
.hpg-empty { padding:1.5rem;text-align:center;color:var(--hm-text-3);font-size:.8rem }

/* ── Value colors ─────────────────────────────────────── */
.vp { color:#4ade80 } .vn { color:#f87171 } .vm { color:var(--hm-text-3) }
/* Intensity-proportional backgrounds for table cells */
.vi1 { background:rgba(74,222,128,.06)!important }
.vi2 { background:rgba(74,222,128,.12)!important }
.vi3 { background:rgba(74,222,128,.2)!important }
.ni1 { background:rgba(248,113,113,.06)!important }
.ni2 { background:rgba(248,113,113,.12)!important }
.ni3 { background:rgba(248,113,113,.2)!important }

/* ── Section title accent ─────────────────────────────── */
.hpg-section-label { font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.55rem;padding:.15rem .5rem;border-radius:4px;display:inline-block }
.hpg-sl-off   { color:var(--s-off);background:var(--s-off-d) }
.hpg-sl-def   { color:var(--s-def);background:var(--s-def-d) }
.hpg-sl-model { color:var(--s-model);background:var(--s-model-d) }
.hpg-sl-eff   { color:var(--s-eff);background:var(--s-eff-d) }

/* ── Lineup expandable cards ─────────────────────────── */
.lq-card { border-bottom:1px solid var(--hm-border);overflow:hidden }
.lq-card:last-child { border-bottom:none }
.lq-header { display:flex;justify-content:space-between;align-items:center;padding:.75rem .9rem;cursor:pointer;user-select:none;transition:background .15s;gap:.75rem }
.lq-header:hover { background:var(--hm-bg-hover) }
.lq-header.open { background:rgba(74,222,128,.04) }
.lq-rank { font-size:.65rem;font-weight:800;color:var(--s-green);text-transform:uppercase;letter-spacing:.08em;flex-shrink:0;min-width:20px }
.lq-names { flex:1;font-size:.79rem;line-height:1.55;min-width:0 }
.lq-meta { display:flex;gap:.5rem;align-items:center;flex-shrink:0 }
.lq-netrtg { font-family:var(--hm-mono);font-weight:800;font-size:.82rem;padding:.15rem .5rem;border-radius:6px }
.lq-chevron { color:var(--hm-text-3);transition:transform .25s;font-size:.75rem;flex-shrink:0 }
.lq-header.open .lq-chevron { transform:rotate(180deg) }
.lq-body { max-height:0;overflow:hidden;transition:max-height .35s cubic-bezier(.4,0,.2,1) }
.lq-body.open { max-height:600px }
.lq-inner { padding:.75rem .9rem .85rem;border-top:1px solid var(--hm-border) }
/* Rating bars */
.lq-rtg-row { display:grid;grid-template-columns:60px 1fr 50px;gap:.4rem;align-items:center;margin-bottom:.4rem;font-size:.73rem }
.lq-rtg-label { color:var(--hm-text-3);font-size:.67rem;text-align:right }
.lq-rtg-track { height:6px;background:rgba(255,255,255,.06);border-radius:999px;overflow:visible;position:relative }
.lq-rtg-fill { height:100%;border-radius:999px;transition:width .5s ease }
.lq-rtg-val { font-family:var(--hm-mono);font-weight:700;font-size:.73rem;text-align:right }
/* Stats grid inside card */
.lq-stats { display:grid;grid-template-columns:repeat(4,1fr);gap:.35rem;margin-bottom:.7rem }
.lq-stat { background:var(--hm-bg-2);border:1px solid var(--hm-border);border-radius:7px;padding:.4rem .5rem;text-align:center }
.lq-stat-v { font-family:var(--hm-mono);font-weight:700;font-size:.88rem }
.lq-stat-l { font-size:.6rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.06em;margin-top:2px }
/* PlayType section */
.lq-pt-title { font-size:.63rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--hm-text-3);margin-bottom:.45rem }
.lq-pt-row { display:flex;align-items:center;gap:.4rem;margin-bottom:.2rem }
.lq-pt-name { font-size:.68rem;color:var(--hm-text-2);width:90px;flex-shrink:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap }
.lq-pt-track { flex:1;height:5px;background:rgba(255,255,255,.06);border-radius:999px;overflow:hidden }
.lq-pt-fill { height:100%;border-radius:999px }
.lq-pt-pct { font-family:var(--hm-mono);font-size:.65rem;color:var(--hm-text-3);min-width:32px;text-align:right }
@media(max-width:960px) { .hpg-2col { grid-template-columns:1fr } .hpg-kpis { grid-template-columns:repeat(4,1fr) } }
@media(max-width:700px) { .hpg-hero-top { grid-template-columns:80px 1fr } #hpg-roles-panel { display:none!important } .hpg-kpis { grid-template-columns:repeat(3,1fr) } }
@media(max-width:640px) { .hpg-hero-top { grid-template-columns:1fr } .hpg-av { display:none } .hpg-2col-eq { grid-template-columns:1fr } }
</style>

<div class="hm-wrap hpg">

  <nav class="hpg-bc">
    <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <a href="<?php echo esc_url($lb_url); ?>">Leaderboard</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span id="hpg-bc">…</span>
  </nav>

  <!-- HERO -->
  <div class="hpg-hero">
    <div class="hpg-hero-top">
      <div class="hpg-av" id="hpg-av">?</div>
      <div style="flex:1;min-width:0">
        <div class="hpg-name" id="hpg-name" style="color:var(--hm-text-3)">Caricamento…</div>
        <div id="hpg-meta"></div>
      </div>
      <!-- Ruoli sulla destra dell'hero -->
      <div id="hpg-roles-panel" style="display:flex;flex-direction:column;gap:.3rem;align-items:flex-end;flex-shrink:0;min-width:160px"></div>
    </div>
    <div class="hpg-kpis" id="hpg-kpis">
      <?php
      $kpi_defs = [
        ['RAPTOR','model'],['LEBRON','model'],['Net Rtg','off'],
        ['BPM','model'],['WS','model'],['USG%','off'],['TS%','eff'],
      ];
      foreach($kpi_defs as [$k,$t]): ?>
        <div class="hpg-kpi" style="border-top:2px solid var(--s-<?php echo $t; ?>)">
          <div class="hpg-kv vm">—</div>
          <div class="hpg-kl"><?php echo $k; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- FILTERS -->
  <div class="hpg-filters">
    <span class="hpg-fl">Competizione</span>
    <select id="hpg-comp" class="hpg-sel">
      <option value="TOT"       <?php selected($comp,'TOT');       ?>>Totale</option>
      <option value="RS"        <?php selected($comp,'RS');        ?>>Regular Season</option>
      <option value="PO"        <?php selected($comp,'PO');        ?>>Playoff</option>
      <option value="CUP"       <?php selected($comp,'CUP');       ?>>Coppa</option>
      <option value="SUPERCUP"  <?php selected($comp,'SUPERCUP');  ?>>Supercoppa</option>
      <option value="Home"      <?php selected($comp,'Home');      ?>>Casa</option>
      <option value="Away"      <?php selected($comp,'Away');      ?>>Trasferta</option>
    </select>
    <span class="hpg-fl" style="margin-left:.5rem">Partite</span>
    <select id="hpg-gn" class="hpg-sel">
      <option value="10" selected>Ultime 10</option>
      <option value="20">Ultime 20</option>
      <option value="40">Ultime 40</option>
    </select>
  </div>

  <!-- TABS -->
  <div class="hpg-tabs">
    <button class="hpg-tab active" data-tab="overview">📊 Panoramica</button>
    <button class="hpg-tab"        data-tab="stats">📈 Statistiche</button>
    <button class="hpg-tab"        data-tab="games">🗓️ Partite</button>
    <button class="hpg-tab"        data-tab="lineups">👥 Quintetti</button>
    <button class="hpg-tab"        data-tab="onoff">⚡ On/Off</button>
    <button class="hpg-tab"        data-tab="pbp">📋 PlayType</button>
    <button class="hpg-tab"        data-tab="similar">🔍 Simili</button>
  </div>

  <!-- ═══════════════════════════════════════════════
       PANORAMICA
  ════════════════════════════════════════════════ -->
  <div class="hpg-panel active" id="panel-overview">
    <div class="hpg-2col">
      <div>

        <!-- Percentili + radar -->
        <div class="hpg-card hpg-card-model">
          <div class="hpg-ch"><span class="hpg-ch-title">🎯 Percentili vs lega</span></div>
          <div class="hpg-cb hpg-2col-eq" style="gap:.9rem;align-items:start">
            <div id="pct-body"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
            <div class="hpg-chart-wrap-sm"><canvas id="chart-radar"></canvas></div>
          </div>
        </div>

        <!-- Shooting profile chart -->
        <div class="hpg-card hpg-card-eff">
          <div class="hpg-ch">
            <span class="hpg-ch-title">🎯 Shooting profile</span>
            <span style="font-size:.67rem;color:var(--hm-text-3)">vs media lega</span>
          </div>
          <div class="hpg-cb">
            <div class="hpg-chart-wrap-md"><canvas id="chart-shooting"></canvas></div>
          </div>
        </div>

      </div>
      <div>

        <!-- Ruolo -->
        <div class="hpg-card hpg-card-off" id="roles-card">
          <div class="hpg-ch"><span class="hpg-ch-title">🏀 Ruolo avanzato</span></div>
          <div class="hpg-cb" id="roles-body"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
        </div>

      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════
       STATISTICHE
  ════════════════════════════════════════════════ -->
  <div class="hpg-panel" id="panel-stats">

    <!-- Splits table -->
    <div class="hpg-card">
      <div class="hpg-ch"><span class="hpg-ch-title">📊 Splits per competizione</span></div>
      <div class="hpg-cb-flush">
        <table class="hpg-t" id="splits-table">
          <thead><tr>
            <th class="l">Comp</th><th>G</th><th>Min</th><th>Pts</th><th>Ast</th><th>Tr</th><th>Stl</th><th>Blk</th>
            <th style="color:var(--s-model)">RAPTOR</th><th style="color:var(--s-model)">LEBRON</th><th style="color:var(--s-model)">BPM</th>
            <th style="color:var(--s-off)">NetRtg</th>
            <th style="color:var(--s-eff)">TS%</th><th style="color:var(--s-eff)">eFG%</th>
            <th style="color:var(--s-off)">USG%</th><th style="color:var(--s-model)">WS</th>
          </tr></thead>
          <tbody id="splits-body"><tr><td colspan="16"><div class="hpg-loading"><div class="hpg-spin"></div></div></td></tr></tbody>
        </table>
      </div>
    </div>

    <!-- Detail sections with sparklines -->
    <div id="stats-detail-wrap"></div>

  </div>

  <!-- ═══════════════════════════════════════════════
       PARTITE
  ════════════════════════════════════════════════ -->
  <div class="hpg-panel" id="panel-games">

    <!-- Trend chart -->
    <div class="hpg-card hpg-card-off">
      <div class="hpg-ch">
        <span class="hpg-ch-title">📉 Andamento prestazioni</span>
        <span id="games-chart-label" style="font-size:.67rem;color:var(--hm-text-3);font-weight:400"></span>
      </div>
      <div class="hpg-cb">
        <div class="hpg-metric-pills">
          <button class="hpg-mpill active" data-cm="gm_sc">GmSc</button>
          <button class="hpg-mpill" data-cm="raptor_total">RAPTOR</button>
          <button class="hpg-mpill" data-cm="net_rtg">NetRtg</button>
          <button class="hpg-mpill" data-cm="points">Punti</button>
          <button class="hpg-mpill" data-cm="plus_minus">+/-</button>
        </div>
        <div class="hpg-chart-wrap"><canvas id="chart-games"></canvas></div>
      </div>
    </div>

    <!-- Game log -->
    <div class="hpg-card">
      <div class="hpg-ch">
        <span class="hpg-ch-title">🗓️ Game log</span>
        <span id="games-count" style="font-size:.67rem;color:var(--hm-text-3);font-weight:400"></span>
      </div>
      <div class="hpg-cb-flush">
        <table class="hpg-t" id="games-table">
          <thead><tr>
            <th class="l">Avversario</th><th style="color:var(--hm-text-3)">#G</th>
            <th>Min</th><th>Pts</th><th>Ast</th><th>Tr</th><th>Stl</th><th>Blk</th>
            <th style="color:var(--s-eff)">2P%</th><th style="color:var(--s-eff)">3P%</th><th style="color:var(--s-eff)">FT%</th>
            <th style="color:var(--s-model)">RAPTOR</th><th style="color:var(--s-off)">NetRtg</th>
            <th>GmSc</th><th>+/-</th><th>Val</th>
          </tr></thead>
          <tbody id="games-body"><tr><td colspan="16"><div class="hpg-loading"><div class="hpg-spin"></div></div></td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════
       QUINTETTI
  ════════════════════════════════════════════════ -->
  <div class="hpg-panel" id="panel-lineups">

    <!-- Scatter in cima — fullwidth -->
    <div class="hpg-card" style="border-top:2px solid var(--s-green);margin-bottom:1rem">
      <div class="hpg-ch">
        <span class="hpg-ch-title">📍 Mappa quintetti</span>
        <span style="font-size:.67rem;color:var(--hm-text-3)">NetRtg vs Possessi — dimensione bolla = utilizzo</span>
      </div>
      <div class="hpg-cb">
        <div class="hpg-chart-wrap"><canvas id="chart-scatter"></canvas></div>
      </div>
    </div>

    <!-- Lista quintetti espandibili -->
    <div class="hpg-card hpg-card-green">
      <div class="hpg-ch">
        <span class="hpg-ch-title">👥 Quintetti principali</span>
        <span id="lineups-count" style="font-size:.67rem;color:var(--hm-text-3);font-weight:400"></span>
      </div>
      <div id="lineups-body"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════
       ON/OFF
  ════════════════════════════════════════════════ -->
  <!-- ═══════════════════════════════════════════════
       ON/OFF — REVAMP
  ════════════════════════════════════════════════ -->
  <div class="hpg-panel" id="panel-onoff">

    <!-- Hero KPI On vs Off -->
    <div class="hpg-card hpg-card-def" style="margin-bottom:1rem">
      <div class="hpg-ch">
        <span class="hpg-ch-title">⚡ Impatto difensivo On/Off</span>
        <span id="onoff-comp" style="font-size:.67rem;color:var(--hm-text-3);font-weight:400"></span>
      </div>
      <div class="hpg-cb" id="onoff-body"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
    </div>

    <div class="hpg-2col">
      <div>
        <!-- Radar chart On vs Off -->
        <div class="hpg-card hpg-card-def">
          <div class="hpg-ch"><span class="hpg-ch-title">📡 Profilo radar difensivo</span></div>
          <div class="hpg-cb">
            <div class="hpg-chart-wrap"><canvas id="chart-onoff-radar"></canvas></div>
          </div>
        </div>
      </div>
      <div>
        <!-- Bar chart -->
        <div class="hpg-card hpg-card-def">
          <div class="hpg-ch"><span class="hpg-ch-title">📊 Confronto On vs Off</span></div>
          <div class="hpg-cb">
            <div class="hpg-chart-wrap-sm"><canvas id="chart-onoff"></canvas></div>
          </div>
        </div>
        <!-- Conteggi raw -->
        <div class="hpg-card">
          <div class="hpg-ch"><span class="hpg-ch-title">📦 Conteggi difensivi raw</span></div>
          <div class="hpg-cb-flush" id="onoff-raw-body"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════
       PLAYTYPE — REVAMP
  ════════════════════════════════════════════════ -->
  <div class="hpg-panel" id="panel-pbp">
    <div class="hpg-card hpg-card-off">
      <div class="hpg-ch">
        <span class="hpg-ch-title">📋 Tendenze di gioco</span>
        <span id="pbp-count" style="font-size:.67rem;color:var(--hm-text-3);font-weight:400"></span>
      </div>
      <div class="hpg-cb" id="pbp-bars"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
    </div>
    <div class="hpg-2col-eq">
      <div class="hpg-card hpg-card-off">
        <div class="hpg-ch"><span class="hpg-ch-title">🍩 Distribuzione</span></div>
        <div class="hpg-cb" style="display:flex;align-items:center;justify-content:center">
          <div style="position:relative;width:200px;height:200px"><canvas id="chart-donut"></canvas></div>
        </div>
      </div>
      <div class="hpg-card hpg-card-eff">
        <div class="hpg-ch"><span class="hpg-ch-title">🎯 Conversion rate per tipo</span></div>
        <div class="hpg-cb" id="pbp-conv"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════
       GIOCATORI SIMILI
  ════════════════════════════════════════════════ -->
  <div class="hpg-panel" id="panel-similar">
    <div class="hpg-card hpg-card-model">
      <div class="hpg-ch">
        <span class="hpg-ch-title">🔍 Giocatori simili</span>
        <span style="font-size:.67rem;color:var(--hm-text-3)">Distanza euclidea su RAPTOR, LEBRON, USG%, TS%, rating difensivo</span>
      </div>
      <div class="hpg-cb" id="similar-body"><div class="hpg-loading"><div class="hpg-spin"></div></div></div>
    </div>
  </div>

</div><!-- /.hm-wrap -->

<?php
// CFG è un oggetto globale stampato subito nel body — non ha bisogno di wp_footer
// perché non usa HM_CONFIG, solo le variabili PHP del template
?>
<script>
window.HM_PLAYER_CFG = {
  id      : '<?php echo esc_js($public_id); ?>',
  nation  : '<?php echo esc_js($nation); ?>',
  year    : '<?php echo esc_js($year); ?>',
  comp    : '<?php echo esc_js($comp); ?>',
  team_url: '<?php echo esc_js($team_url); ?>',
};
</script>

<?php
// Tutta la logica JS va nel footer DOPO hm-main.js (che porta HM_CONFIG/nonce)
// Non servono variabili PHP nella closure — legge da window.HM_PLAYER_CFG
add_action('wp_footer', function() {
?>
<script>
(function(){
'use strict';

const CFG = window.HM_PLAYER_CFG || {};
const pid = encodeURIComponent(CFG.id || '');

// ── API ───────────────────────────────────────────────
const nonce    = () => typeof HM_CONFIG !== 'undefined' ? HM_CONFIG.nonce    : '';
const restBase = () => typeof HM_CONFIG !== 'undefined' ? HM_CONFIG.rest_url : '/wp-json/hoopmetrics/v1';

async function apiFetch(path, timeoutMs = 12000) {
  const ctrl = new AbortController();
  const tid   = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const r = await fetch(restBase() + path, {
      headers: { 'X-WP-Nonce': nonce(), 'Accept': 'application/json' },
      credentials: 'same-origin',
      signal: ctrl.signal,
    });
    const j = await r.json();
    if (!j.success) throw new Error(j.message ?? j.error ?? 'Errore API');
    return j.data;
  } catch(e) {
    if (e.name === 'AbortError') throw new Error('Timeout — riprova o controlla la connessione Azure SQL');
    throw e;
  } finally {
    clearTimeout(tid);
  }
}
const qs = p => '?' + new URLSearchParams(
  Object.fromEntries(Object.entries(p).filter(([,v])=>v!==''&&v!=null))
).toString();

// ── Formatters ────────────────────────────────────────
const fN  = (v,d=2) => v!=null ? (v>0?'+':'')+Number(v).toFixed(d) : '—';
const fF  = (v,d=1) => v!=null ? Number(v).toFixed(d) : '—';
const fP  = v       => v!=null ? Number(v).toFixed(1)+'%' : '—';
const fI  = v       => v!=null ? String(Math.round(Number(v))) : '—';
const esc = s       => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

// ── Semantic color helpers ────────────────────────────
// Returns CSS class for positive/negative/neutral
const vcl = v => v==null ? 'vm' : Number(v)>0 ? 'vp' : Number(v)<0 ? 'vn' : 'vm';
// Returns intensity class for table cell backgrounds
function intensityClass(v, inv=false) {
  if (v==null) return '';
  const n = Math.abs(Number(v));
  const pos = inv ? Number(v)<0 : Number(v)>0;
  const lvl = n>5 ? 3 : n>2 ? 2 : n>0.5 ? 1 : 0;
  if (!lvl) return '';
  return pos ? `vi${lvl}` : `ni${lvl}`;
}

// ── Chart helpers — Chart.js valutato a runtime ──────
// (lo script inline può girare prima del CDN se in footer)
const chartJsAvail = () => typeof Chart !== 'undefined';
const charts = {};
function destroyChart(key) { if (charts[key]) { try{ charts[key].destroy(); }catch(e){} delete charts[key]; } }
function newChart(key, ctx, cfg) {
  if (!chartJsAvail() || !ctx) return null;
  destroyChart(key);
  try { charts[key] = new Chart(ctx, cfg); return charts[key]; } catch(e) { console.warn('Chart error ['+key+']:', e); return null; }
}
function resizeAllCharts() {
  Object.values(charts).forEach(c => { try { c.resize(); } catch(e) {} });
}

const textC = '#64748b';
const gridC = 'rgba(255,255,255,.05)';
if (chartJsAvail) {
  Chart.defaults.color = textC;
  Chart.defaults.font  = { family: 'inherit', size: 11 };
}

// ── Nationality flags ─────────────────────────────────
const FL = {GRC:'🇬🇷',GRE:'🇬🇷',ITA:'🇮🇹',DEU:'🇩🇪',GER:'🇩🇪',FRA:'🇫🇷',SPA:'🇪🇸',ESP:'🇪🇸',TUR:'🇹🇷',SRB:'🇷🇸',LTU:'🇱🇹',USA:'🇺🇸',CYP:'🇨🇾',BIH:'🇧🇦',HRV:'🇭🇷',MNE:'🇲🇪',MKD:'🇲🇰',SVN:'🇸🇮',BGR:'🇧🇬',BUL:'🇧🇬',ROU:'🇷🇴',HUN:'🇭🇺',POL:'🇵🇱',CZE:'🇨🇿',AUT:'🇦🇹',ISR:'🇮🇱',LAT:'🇱🇻',EST:'🇪🇪',FIN:'🇫🇮',NOR:'🇳🇴',SWE:'🇸🇪',DEN:'🇩🇰',AUS:'🇦🇺',BRA:'🇧🇷',ARG:'🇦🇷',CAN:'🇨🇦',NGA:'🇳🇬',SEN:'🇸🇳',CMR:'🇨🇲',GBR:'🇬🇧'};
const flag = c => FL[c?.toUpperCase()] ?? '';

// ── Percentile fill ───────────────────────────────────
function pctFill(p) {
  if (p>=80) return { f:'linear-gradient(90deg,#16a34a,#4ade80)', cls:'vp' };
  if (p>=60) return { f:'linear-gradient(90deg,#1d4ed8,#60a5fa)', cls:'' };
  if (p>=40) return { f:'linear-gradient(90deg,#a16207,#fde047)', cls:'' };
  if (p>=20) return { f:'linear-gradient(90deg,#c2410c,#fb923c)', cls:'' };
  return     { f:'linear-gradient(90deg,#b91c1c,#f87171)', cls:'vn' };
}

// ══════════════════════════════════════════════════════════
// PROFILE
// ══════════════════════════════════════════════════════════
async function loadProfile() {
  const p = await apiFetch(`/player/${pid}${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`);
  const init = (p.player_name||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
  document.getElementById('hpg-av').textContent = init;
  const nameEl = document.getElementById('hpg-name');
  nameEl.textContent = p.player_name; nameEl.style.color = '';
  document.getElementById('hpg-bc').textContent = p.player_name;
  document.title = `${p.player_name} · HoopMetrics`;

  const nats = [p.nationality, p.nationality2].filter(n=>n&&n!=='—'&&n!=='');
  const natHtml = nats.map(n=>`<span class="hpg-mi"><span class="hpg-mv">${flag(n)} ${esc(n)}</span></span>`).join('');
  const items = [
    natHtml,
    p.position ? `<span class="hpg-sep"></span><span class="hpg-mi"><span class="hpg-ml">Pos</span>&nbsp;<span class="hpg-mv">${esc(p.position)}</span></span>` : '',
    p.shirt_number&&p.shirt_number!=='—' ? `<span class="hpg-sep"></span><span class="hpg-mi"><span class="hpg-mv">#${esc(p.shirt_number)}</span></span>` : '',
    p.age  ? `<span class="hpg-sep"></span><span class="hpg-mi"><span class="hpg-ml">Età</span>&nbsp;<span class="hpg-mv">${p.age} a.</span></span>` : '',
    p.height_cm ? `<span class="hpg-sep"></span><span class="hpg-mi"><span class="hpg-ml">Alt.</span>&nbsp;<span class="hpg-mv">${p.height_cm} cm</span></span>` : '',
    p.weight&&p.weight!=='' ? `<span class="hpg-sep"></span><span class="hpg-mi"><span class="hpg-ml">Kg</span>&nbsp;<span class="hpg-mv">${p.weight}</span></span>` : '',
    p.team_name&&p.team_name!=='—' ? `<span class="hpg-sep"></span><span class="hpg-mi"><span class="hpg-ml">Team</span>&nbsp;<span class="hpg-mv"><a href="${esc(CFG.team_url)}?team=${encodeURIComponent(p.public_id)}&nation=${CFG.nation}&year=${CFG.year}" style="color:#f97316;text-decoration:none">${esc(p.team_name)}</a></span></span>` : '',
  ].filter(Boolean);
  document.getElementById('hpg-meta').innerHTML = `<div class="hpg-meta-row">${items.join('')}</div>`;

  // KPI strip
  const KPIS = [
    {v:p.raptor_total,d:2,sign:true},{v:p.lebron_total,d:2,sign:true},
    {v:p.net_rtg,d:1,sign:true},{v:p.bpm,d:2,sign:true},{v:p.ws,d:2,sign:true},
    {v:p.usg_pct,d:1,pct:true},{v:p.ts_pct,d:1,pct:true},
  ];
  document.getElementById('hpg-kpis').querySelectorAll('.hpg-kpi').forEach((el,i)=>{
    const k = KPIS[i]; if(!k) return;
    const v   = k.v;
    const cls = k.pct ? '' : vcl(v);
    const txt = v!=null ? (k.pct ? Number(v).toFixed(k.d)+'%' : (v>0?'+':'')+Number(v).toFixed(k.d)) : '—';
    el.querySelector('.hpg-kv').className = `hpg-kv ${cls}`;
    el.querySelector('.hpg-kv').textContent = txt;
  });

  return p;
}

// ══════════════════════════════════════════════════════════
// ROLE
// ══════════════════════════════════════════════════════════
async function loadRole() {
  const el      = document.getElementById('roles-body');
  const panel   = document.getElementById('hpg-roles-panel');

  try {
    const r = await apiFetch(`/player/${pid}/role${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`);

    // Pannello destra nell'hero — label + valore senza icone
    if (panel) {
      if (!r || (!r.role_off && !r.role_def && !r.role_combo)) {
        panel.innerHTML = `<span style="font-size:.7rem;color:var(--hm-text-3)">Ruolo N/D</span>`;
      } else {
        const roleRows = [
          r.role_off   ? ['Offensivo',  r.role_off,   'var(--s-off)',   'var(--s-off-d)',   'var(--s-off-b)']   : null,
          r.role_def   ? ['Difensivo',  r.role_def,   'var(--s-def)',   'var(--s-def-d)',   'var(--s-def-b)']   : null,
          r.role_combo ? ['Combinato',  r.role_combo, 'var(--s-model)', 'var(--s-model-d)', 'var(--s-model-b)'] : null,
        ].filter(Boolean);
        panel.innerHTML = roleRows.map(([label, val, clr, bg, bdr]) =>
          `<div style="text-align:right">
            <span style="font-size:.6rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.08em;display:block;margin-bottom:1px">${label}</span>
            <span style="display:inline-block;background:${bg};border:1px solid ${bdr};color:${clr};font-size:.69rem;font-weight:700;padding:.15rem .55rem;border-radius:5px;text-transform:uppercase;letter-spacing:.06em">${esc(val)}</span>
          </div>`
        ).join('');
      }
    }

    // Card ruolo avanzato
    if (!r) {
      el.innerHTML = `<div class="hpg-empty">Nessun dato ruolo per la competizione selezionata (${esc(CFG.comp||'TOT')})</div>`;
      return;
    }

    el.innerHTML = `
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:.7rem">
        ${r.role_off   ? `<div style="text-align:center;padding:.6rem .4rem;background:var(--s-off-d);border:1px solid var(--s-off-b);border-radius:8px"><div style="font-size:.62rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.3rem">Offensivo</div><div style="font-size:.77rem;font-weight:700;color:var(--s-off)">${esc(r.role_off)}</div></div>` : '<div></div>'}
        ${r.role_def   ? `<div style="text-align:center;padding:.6rem .4rem;background:var(--s-def-d);border:1px solid var(--s-def-b);border-radius:8px"><div style="font-size:.62rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.3rem">Difensivo</div><div style="font-size:.77rem;font-weight:700;color:var(--s-def)">${esc(r.role_def)}</div></div>` : '<div></div>'}
        ${r.role_combo ? `<div style="text-align:center;padding:.6rem .4rem;background:var(--s-model-d);border:1px solid var(--s-model-b);border-radius:8px"><div style="font-size:.62rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.07em;margin-bottom:.3rem">Combinato</div><div style="font-size:.77rem;font-weight:700;color:var(--s-model)">${esc(r.role_combo)}</div></div>` : '<div></div>'}
      </div>
      <div style="display:flex;gap:1rem;font-size:.76rem;color:var(--hm-text-3)">
        <span>Comp: <strong style="color:var(--hm-text)">${esc(r.competition??'—')}</strong></span>
        <span>Partite: <strong style="color:var(--hm-text)">${r.games_played??'—'}</strong></span>
        <span>Min tot: <strong style="color:var(--hm-text)">${r.min??'—'}</strong></span>
        ${r.avg_min!=null?`<span>Min/G: <strong style="color:var(--s-off)">${r.avg_min.toFixed(1)}</strong></span>`:''}
      </div>`;
  } catch(e) {
    if (panel) panel.innerHTML = '';
    el.innerHTML = '<div class="hpg-empty">Ruolo non disponibile</div>';
  }
}

// ══════════════════════════════════════════════════════════
// PERCENTILES + RADAR + SHOOTING CHART
// ══════════════════════════════════════════════════════════
let leagueAvgs = {};

async function loadPercentiles() {
  const el = document.getElementById('pct-body');
  try {
    const pcts = await apiFetch(`/player/${pid}/percentiles${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`);
    const LABELS = {RaptorTotal:'RAPTOR',LebronTotal:'LEBRON',NetRtg:'Net Rtg',ORtg:'Off Rtg',DRtg:'Def Rtg',UsgPct:'Usage%',TsPct:'TS%',Bpm:'BPM',Ws:'WS'};
    const entries = Object.entries(pcts).filter(([k])=>LABELS[k]);
    if (!entries.length) { el.innerHTML='<div class="hpg-empty">Nessun dato</div>'; return; }
    el.innerHTML = entries.map(([k,p])=>{
      const c = pctFill(p);
      return `<div class="hpg-pct-row">
        <div class="hpg-pct-head"><span class="hpg-pct-name">${LABELS[k]}</span><span class="hpg-pct-num ${c.cls}">${p.toFixed(0)}°</span></div>
        <div class="hpg-pct-track"><div class="hpg-pct-fill" style="width:${p}%;background:${c.f}"></div></div>
      </div>`;
    }).join('');

    // Radar chart (6 percentili)
    destroyChart('radar');
    const rKeys   = ['RaptorTotal','LebronTotal','UsgPct','TsPct','Bpm','Ws'].filter(k=>pcts[k]!=null);
    const rLabels = {RaptorTotal:'RAPTOR',LebronTotal:'LEBRON',UsgPct:'Usage',TsPct:'TS%',Bpm:'BPM',Ws:'WS'};
    if (rKeys.length >= 3) {
      const ctx = document.getElementById('chart-radar')?.getContext('2d');
      if (ctx) newChart('radar', ctx, {
        type:'radar',
        data:{ labels:rKeys.map(k=>rLabels[k]), datasets:[{
          data:rKeys.map(k=>pcts[k]), backgroundColor:'rgba(167,139,250,.15)',
          borderColor:'#a78bfa', borderWidth:2, pointBackgroundColor:'#a78bfa', pointRadius:3,
        }]},
        options:{ responsive:true, maintainAspectRatio:false,
          scales:{ r:{ min:0, max:100, ticks:{ stepSize:25, font:{size:9} }, grid:{color:gridC}, pointLabels:{font:{size:10}} }},
          plugins:{ legend:{ display:false } },
        }
      });
    }
  } catch(e) { el.innerHTML='<div class="hpg-empty">Percentili non disponibili</div>'; }
}

// Shooting profile chart — richiede i dati dello split per confronto lega
function buildShootingChart(playerData, allPlayers) {
  destroyChart('shooting');
  const ctx = document.getElementById('chart-shooting')?.getContext('2d');
  if (!ctx || !playerData) return;

  const toN = (v) => v!=null ? Math.round(Number(v)*10)/10 : null;

  // Valori giocatore — ts_pct e efg_pct da splits sono già in % (moltiplicati ×100 dal PHP)
  const playerVals = {
    'TS%':  toN(playerData.ts_pct),
    'eFG%': toN(playerData.efg_pct),
    'USG%': toN(playerData.usg_pct),
  };

  // Media lega — la leaderboard restituisce ts_pct/efg_pct già in % (×100), usg_pct come float
  const avg = key => {
    const vals = (allPlayers||[]).map(p=>p[key]).filter(v=>v!=null&&!isNaN(v));
    return vals.length ? Math.round(vals.reduce((a,b)=>a+Number(b),0)/vals.length*10)/10 : null;
  };
  const leagueVals = {
    'TS%':  avg('ts_pct'),
    'eFG%': avg('efg_pct'),
    'USG%': avg('usg_pct'),
  };

  const labels = Object.keys(playerVals).filter(k=>playerVals[k]!=null);
  const pVals  = labels.map(k=>playerVals[k]);
  const lVals  = labels.map(k=>leagueVals[k]);

  if (!labels.length) return;

  newChart('shooting', ctx, {
    type:'bar',
    data:{
      labels,
      datasets:[
        { label:'Giocatore',   data:pVals, backgroundColor:'rgba(253,224,71,.65)', borderColor:'#fde047', borderWidth:1.5, borderRadius:4 },
        { label:'Media lega',  data:lVals, backgroundColor:'rgba(96,165,250,.25)',  borderColor:'#60a5fa', borderWidth:1.5, borderRadius:4 },
      ]
    },
    options:{
      responsive:true, maintainAspectRatio:false, indexAxis:'y',
      plugins:{ legend:{ labels:{ color:textC, boxWidth:10, font:{size:11} } }, tooltip:{ callbacks:{ label: t=>`${t.dataset.label}: ${t.parsed.x}%` } } },
      scales:{ x:{ grid:{color:gridC}, ticks:{ callback: v=>v+'%' } }, y:{ grid:{color:gridC} } }
    }
  });
}

// ══════════════════════════════════════════════════════════
// LINEUPS + SCATTER
// ══════════════════════════════════════════════════════════
let cachedLineups = [];

async function loadLineups() {
  const el = document.getElementById('lineups-body');
  try {
    const rows = await apiFetch(`/player/${pid}/lineups${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`);
    cachedLineups = rows ?? [];

    const countEl = document.getElementById('lineups-count');
    if (countEl) countEl.textContent = cachedLineups.length ? `${cachedLineups.length} quintetti` : '';

    if (!cachedLineups.length) {
      el.innerHTML='<div class="hpg-empty">Dati quintetti non disponibili dalla tabella PBP</div>';
      return;
    }

    const PT_COLORS = ['#f97316','#60a5fa','#4ade80','#a78bfa','#fde047','#f472b6','#34d399','#fb923c'];

    el.innerHTML = cachedLineups.map((l, idx) => {
      const id = `lq-${idx}`;

      const playerNames = (l.players||[]).map(p =>
        p.is_self
          ? `<strong style="color:var(--s-off)">${esc(p.name)}</strong>`
          : `<span style="color:var(--hm-text-2)">${esc(p.name)}</span>`
      ).join('<span style="color:var(--hm-text-3);margin:0 .2rem">·</span>');

      const netBg  = l.net_rtg==null ? 'rgba(148,163,184,.1)' : l.net_rtg>=0 ? 'rgba(74,222,128,.12)' : 'rgba(248,113,113,.12)';
      const netClr = l.net_rtg==null ? '#94a3b8' : l.net_rtg>=0 ? '#4ade80' : '#f87171';
      const netTxt = l.net_rtg!=null ? (l.net_rtg>=0?'+':'')+l.net_rtg.toFixed(1) : '—';

      const rtgBar = (val, color, inverted=false) => {
        if (val==null) return '<div style="height:6px;background:rgba(255,255,255,.06);border-radius:999px"></div>';
        const pct = Math.min(Math.max(((val - 80) / 50) * 100, 2), 100);
        const c = inverted ? (val < 100 ? '#4ade80' : '#f87171') : color;
        return `<div class="lq-rtg-track"><div class="lq-rtg-fill" style="width:${pct.toFixed(0)}%;background:${c}"></div></div>`;
      };

      const ptRows = (l.play_types||[]).map((pt, i) => `
        <div class="lq-pt-row">
          <span class="lq-pt-name">${esc(pt.play_type)}</span>
          <div class="lq-pt-track"><div class="lq-pt-fill" style="width:${Math.round(pt.pct)}%;background:${PT_COLORS[i%PT_COLORS.length]}"></div></div>
          <span class="lq-pt-pct">${pt.pct}%</span>
        </div>`).join('');

      const pm = (l.tot_for != null && l.tot_against != null) ? (l.tot_for - l.tot_against) : null;
      const pmTxt = pm != null ? (pm>=0?'+':'')+pm : '—';
      const pmClr = pm == null ? 'vm' : pm > 0 ? 'vp' : pm < 0 ? 'vn' : 'vm';

      return `
      <div class="lq-card">
        <div class="lq-header" id="${id}-h">
          <span class="lq-rank">#${idx+1}</span>
          <div class="lq-names">${playerNames}</div>
          <div class="lq-meta">
            <span style="font-size:.65rem;color:var(--hm-text-3)">${l.poss} azioni · ${l.games}G</span>
            <span class="lq-netrtg" style="background:${netBg};color:${netClr}">NetRtg ${netTxt}</span>
            <span class="lq-chevron">▼</span>
          </div>
        </div>
        <div class="lq-body" id="${id}-b">
          <div class="lq-inner">
            <div class="lq-stats" style="margin-bottom:.75rem">
              <div class="lq-stat">
                <div class="lq-stat-v ${l.o_rtg!=null?(l.o_rtg>=100?'vp':'vn'):'vm'}">${l.o_rtg!=null?l.o_rtg.toFixed(1):'—'}</div>
                <div class="lq-stat-l">ORtg</div>
              </div>
              <div class="lq-stat">
                <div class="lq-stat-v ${l.d_rtg!=null?(l.d_rtg<100?'vp':'vn'):'vm'}">${l.d_rtg!=null?l.d_rtg.toFixed(1):'—'}</div>
                <div class="lq-stat-l">DRtg</div>
              </div>
              <div class="lq-stat">
                <div class="lq-stat-v ${pmClr}">${pmTxt}</div>
                <div class="lq-stat-l">+/− tot</div>
              </div>
              <div class="lq-stat">
                <div class="lq-stat-v vm">${l.games??'—'}</div>
                <div class="lq-stat-l">Partite</div>
              </div>
              ${l.tot_for!=null?`<div class="lq-stat"><div class="lq-stat-v vp">${l.tot_for}</div><div class="lq-stat-l">Pt segnati</div></div>`:''}
              ${l.tot_against!=null?`<div class="lq-stat"><div class="lq-stat-v vn">${l.tot_against}</div><div class="lq-stat-l">Pt subiti</div></div>`:''}
              <div class="lq-stat">
                <div class="lq-stat-v vm">${l.poss}</div>
                <div class="lq-stat-l">Azioni</div>
              </div>
              <div class="lq-stat">
                <div class="lq-stat-v vm">${l.poss&&l.games?(l.poss/l.games).toFixed(1):'—'}</div>
                <div class="lq-stat-l">Az/Partita</div>
              </div>
            </div>
            ${l.o_rtg!=null||l.d_rtg!=null ? `
            <div style="margin-bottom:.75rem">
              <div class="lq-rtg-row">
                <span class="lq-rtg-label">ORtg</span>
                ${rtgBar(l.o_rtg, '#f97316', false)}
                <span class="lq-rtg-val ${l.o_rtg!=null?(l.o_rtg>=100?'vp':'vn'):'vm'}">${l.o_rtg!=null?l.o_rtg.toFixed(1):'—'}</span>
              </div>
              <div class="lq-rtg-row">
                <span class="lq-rtg-label">DRtg</span>
                ${rtgBar(l.d_rtg, '#60a5fa', true)}
                <span class="lq-rtg-val ${l.d_rtg!=null?(l.d_rtg<100?'vp':'vn'):'vm'}">${l.d_rtg!=null?l.d_rtg.toFixed(1):'—'}</span>
              </div>
            </div>` : ''}
            ${ptRows ? `<div class="lq-pt-title">Distribuzione azioni offensive</div>${ptRows}` : ''}

            ${Object.keys(l.quarters||{}).length ? (() => {
              const qData = l.quarters;
              const qTotal = Object.values(qData).reduce((a,b)=>a+b, 0);
              const qLabels = {1:'Q1',2:'Q2',3:'Q3',4:'Q4',5:'OT'};
              const qColors = {'1':'#60a5fa','2':'#4ade80','3':'#fde047','4':'#f97316','5':'#a78bfa'};
              const qBars = Object.entries(qData).sort(([a],[b])=>+a-+b).map(([q,cnt]) => {
                const pct = qTotal > 0 ? Math.round(cnt/qTotal*100) : 0;
                const color = qColors[q] ?? '#94a3b8';
                return `<div style="display:flex;align-items:center;gap:.4rem;margin-bottom:.18rem">
                  <span style="font-size:.65rem;font-weight:700;color:${color};width:24px;flex-shrink:0">${qLabels[q]??'Q'+q}</span>
                  <div class="lq-pt-track"><div class="lq-pt-fill" style="width:${pct}%;background:${color}"></div></div>
                  <span style="font-family:var(--hm-mono);font-size:.62rem;color:var(--hm-text-3);min-width:28px;text-align:right">${cnt}</span>
                </div>`;
              }).join('');
              return `<div class="lq-pt-title" style="margin-top:.55rem">Utilizzo per quarto</div>${qBars}`;
            })() : ''}
          </div>
        </div>
      </div>`;
    }).join('');

    buildScatterChart(cachedLineups);

    // Attach click events (toggleLineup è dentro IIFE, non globale)
    el.querySelectorAll('.lq-header').forEach(header => {
      header.addEventListener('click', () => {
        const id  = header.id.replace('-h', '');
        const body = document.getElementById(`${id}-b`);
        if (!body) return;
        const isOpen = body.classList.contains('open');
        el.querySelectorAll('.lq-body.open').forEach(b => b.classList.remove('open'));
        el.querySelectorAll('.lq-header.open').forEach(h => h.classList.remove('open'));
        if (!isOpen) { body.classList.add('open'); header.classList.add('open'); }
      });
    });
  } catch(e) {
    el.innerHTML=`<div class="hpg-empty">Quintetti non disponibili: ${esc(e.message)}</div>`;
  }
}

function buildScatterChart(rows) {
  // Defer to next tick — il canvas deve essere visibile per avere dimensioni
  setTimeout(() => {
    destroyChart('scatter');
    const ctx = document.getElementById('chart-scatter')?.getContext('2d');
    if (!ctx || !rows?.length) return;
    const valid = rows.filter(r=>r.net_rtg!=null&&r.poss!=null);
    if (!valid.length) return;

    const COLORS = ['#f97316','#60a5fa','#4ade80','#a78bfa','#fde047','#f472b6','#34d399','#fb923c'];
    newChart('scatter', ctx, {
      type:'bubble',
      data:{ datasets: valid.map((r,i)=>({
        label: `#${i+1}`,
        data:[{ x:r.poss, y:r.net_rtg, r:Math.min(4+r.poss/20, 14) }],
        backgroundColor: COLORS[i%COLORS.length]+'99',
        borderColor: COLORS[i%COLORS.length],
        borderWidth:1.5,
      }))},
      options:{
        responsive:true, maintainAspectRatio:false,
        plugins:{ legend:{ display:false },
          tooltip:{ callbacks:{ label: t=>{
            const r = valid[t.datasetIndex];
            return [`#${t.datasetIndex+1}: ${r.poss} azioni`, `NetRtg: ${r.net_rtg>=0?'+':''}${r.net_rtg.toFixed(1)}`];
          }}}},
        scales:{
          x:{ title:{ display:true, text:'Possessi', color:textC, font:{size:10} }, grid:{color:gridC} },
          y:{ title:{ display:true, text:'NetRtg', color:textC, font:{size:10} }, grid:{color:gridC},
              ticks:{ callback:v=>(v>=0?'+':'')+v }},
        }
      }
    });
  }, 60);
}

// ══════════════════════════════════════════════════════════
// SPLITS + STATS DETAIL WITH SPARKLINES
// ══════════════════════════════════════════════════════════
let cachedSplits = null, cachedGames = [];

async function loadSplits() {
  const tbody   = document.getElementById('splits-body');
  const detWrap = document.getElementById('stats-detail-wrap');
  try {
    const data = await apiFetch(`/player/${pid}/splits${qs({nation:CFG.nation,year:CFG.year})}`);
    cachedSplits = data.splits ?? {};
    const ORDER = ['TOT','RS','PO','CUP','SUPERCUP','Home','Away'];
    const COMP_L= {TOT:'TOT',RS:'RS',PO:'PO',CUP:'CUP',SUPERCUP:'SCUP',Home:'Casa',Away:'Away'};
    const entries = ORDER.filter(k=>cachedSplits[k]).map(k=>[k,cachedSplits[k]]);

    tbody.innerHTML = entries.length ? entries.map(([comp,s])=>`
      <tr class="${comp==='TOT'?'row-tot':''}">
        <td class="l td-comp">${esc(COMP_L[comp]??comp)}</td>
        <td>${s.games??'—'}</td><td>${s.minutes??'—'}</td>
        <td>${fF(s.pts)}</td><td>${fF(s.ast)}</td><td>${fF(s.tr)}</td><td>${fF(s.stl)}</td><td>${fF(s.blk)}</td>
        <td class="${vcl(s.raptor_total)} ${intensityClass(s.raptor_total)}">${fN(s.raptor_total)}</td>
        <td class="${vcl(s.lebron_total)} ${intensityClass(s.lebron_total)}">${fN(s.lebron_total)}</td>
        <td class="${vcl(s.bpm)} ${intensityClass(s.bpm)}">${fN(s.bpm)}</td>
        <td class="${vcl(s.net_rtg)} ${intensityClass(s.net_rtg)}">${fN(s.net_rtg,1)}</td>
        <td>${fP(s.ts_pct)}</td><td>${fP(s.efg_pct)}</td><td>${fP(s.usg_pct)}</td>
        <td class="${vcl(s.ws)} ${intensityClass(s.ws)}">${fN(s.ws)}</td>
      </tr>`).join('')
      : '<tr><td colspan="16" class="hpg-empty">Nessun dato</td></tr>';

    const main = cachedSplits['TOT'] ?? cachedSplits['RS'] ?? Object.values(cachedSplits)[0];
    if (main) {
      renderStatsDetail(main);
      // Fetch leaderboard per media lega (limit alto, min_min basso per includere tutti)
      setTimeout(async () => {
        try {
          const lb = await apiFetch(`/leaderboard/players${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp,metric:'TsPct',limit:200,min_min:0})}`);
          buildShootingChart(main, lb ?? []);
        } catch(e) {
          buildShootingChart(main, []);
        }
      }, 60);
    }
  } catch(e) {
    tbody.innerHTML = `<tr><td colspan="16" class="hpg-error">⚠️ ${esc(e.message)}</td></tr>`;
  }
}

function sparklineHtml(values, color='#f97316') {
  if (!values?.length) return '';
  const valid = values.filter(v=>v!=null);
  if (!valid.length) return '';
  const mn = Math.min(...valid), mx = Math.max(...valid);
  const range = mx - mn || 1;
  const bars  = values.slice(-10).map(v => {
    const h = v!=null ? Math.max(2, Math.round((v-mn)/range*16)) : 2;
    const c = v==null ? '#334155' : v>=0 ? '#4ade80' : '#f87171';
    return `<div class="hpg-spark-bar" style="height:${h}px;background:${c}"></div>`;
  }).join('');
  return `<div class="hpg-spark">${bars}</div>`;
}

function renderStatsDetail(s) {
  const wrap = document.getElementById('stats-detail-wrap');
  if (!s || !wrap) return;

  // Sparkline values per metrica (dalle game più recenti)
  const gm = cachedGames.slice().reverse();
  const spark = key => sparklineHtml(gm.map(g=>g[key]));

  const cell = (label, value, cls='base', valCls='', spk='') =>
    `<div class="hpg-sc hpg-sc-${cls}">
      <div class="hpg-sl">${label}</div>
      <div class="hpg-sv ${valCls}">${value}</div>
      ${spk}
    </div>`;

  const sections = [
    { title:'🎯 Shooting', color:'eff', cells:[
      cell('TS%',   fP(s.ts_pct),  'eff','',spark('ts_pct')),
      cell('eFG%',  fP(s.efg_pct), 'eff'),
      cell('FT Rate',s.ftr!=null?s.ftr.toFixed(3):'—','eff'),
      cell('3PAr',  fP(s.three_par),'eff'),
      cell('Pts/40',fF(s.pts_p40), 'eff'),
      cell('Pts',   fF(s.pts,1),   'off'),
    ]},
    { title:'📊 Volume & Rates', color:'off', cells:[
      cell('USG%',    fP(s.usg_pct),   'off','',spark('usg_pct')),
      cell('AST%',    fP(s.ast_pct),   'off'),
      cell('TOV%',    fP(s.tov_pct),   'base'),
      cell('STL%',    fP(s.stl_pct),   'base'),
      cell('BLK%',    fP(s.blk_pct),   'def'),
      cell('REB%',    fP(s.reb_pct),   'def'),
      cell('OREB%',   fP(s.oreb_pct),  'def'),
      cell('DREB%',   fP(s.dreb_pct),  'def'),
      cell('ASTRatio',fF(s.ast_ratio), 'off'),
      cell('Hustle',  s.hustle!=null?fI(s.hustle):'—','base'),
      cell('FIC',     s.fic!=null?fI(s.fic):'—','base'),
    ]},
    { title:'⚡ Modelli di impatto', color:'model', cells:[
      cell('RAPTOR',  fN(s.raptor_total), 'model', vcl(s.raptor_total), spark('raptor_total')),
      cell('RAP Off', fN(s.raptor_off),   'model', vcl(s.raptor_off)),
      cell('RAP Def', fN(s.raptor_def),   'model', vcl(s.raptor_def)),
      cell('LEBRON',  fN(s.lebron_total), 'model', vcl(s.lebron_total), spark('lebron_total')),
      cell('BPM',     fN(s.bpm),          'model', vcl(s.bpm),          spark('bpm')),
      cell('VORP',    fN(s.vorp),         'model', vcl(s.vorp)),
      cell('WS',      fN(s.ws),           'model', vcl(s.ws)),
      cell('Net Rtg', fN(s.net_rtg,1),    'off',   vcl(s.net_rtg),      spark('net_rtg')),
      cell('O Rtg',   fF(s.o_rtg),        'off'),
      cell('D Rtg',   fF(s.d_rtg),        'def'),
      cell('PIE',     s.pie!=null?s.pie.toFixed(3):'—','base'),
      cell('SPM',     fN(s.spm),          'model', vcl(s.spm)),
    ]},
    { title:'📈 Per 40 minuti', color:'off', cells:[
      cell('Pts/40', fF(s.pts_p40), 'off'),
      cell('Ast/40', fF(s.ast_p40), 'off'),
      cell('Tr/40',  fF(s.tr_p40),  'base'),
      cell('Stl/40', fF(s.stl_p40), 'base'),
      cell('Blk/40', fF(s.blk_p40), 'def'),
      cell('To/40',  fF(s.to_p40),  'base'),
    ]},
  ];

  wrap.innerHTML = sections.map(sec=>`
    <div class="hpg-card hpg-card-${sec.color}">
      <div class="hpg-ch"><span class="hpg-ch-title">${sec.title}</span></div>
      <div class="hpg-cb">
        <div class="hpg-stat-grid">${sec.cells.join('')}</div>
      </div>
    </div>`).join('');
}

// ══════════════════════════════════════════════════════════
// GAMES
// ══════════════════════════════════════════════════════════
let activeGameMetric = 'gm_sc';

async function loadGames() {
  const tbody = document.getElementById('games-body');
  const n     = document.getElementById('hpg-gn')?.value ?? 10;
  try {
    const rows = await apiFetch(`/player/${pid}/games${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp,limit:n})}`);
    cachedGames = rows ?? [];
    document.getElementById('games-count').textContent = `${cachedGames.length} partite`;

    if (!cachedGames.length) { tbody.innerHTML='<tr><td colspan="16" class="hpg-empty">Nessuna partita</td></tr>'; return; }

    tbody.innerHTML = cachedGames.map(g=>{
      const opp  = g.opponent ? `VS ${esc(g.opponent)}` : (g.game ? esc(g.game) : '—');
      const loc  = g.is_home ? '<span class="hpg-badge hpg-bh">H</span>' : '<span class="hpg-badge hpg-ba">A</span>';
      const strt = g.starter ? '<span class="hpg-badge hpg-bs">S</span>' : '';
      const dt   = g.timestamp ? new Date(g.timestamp).toLocaleDateString('it-IT',{day:'2-digit',month:'2-digit'}) : '—';
      return `<tr>
        <td class="l td-name">${opp}${loc}${strt}</td>
        <td style="font-family:var(--hm-mono);font-size:.7rem;color:var(--hm-text-3);text-align:center">${g.game_num != null ? '#'+g.game_num : '—'}</td>
        <td>${g.minutes}</td><td>${g.points}</td><td>${g.ast}</td><td>${g.tr}</td><td>${g.stl}</td><td>${g.blk}</td>
        <td>${fP(g.fg2_pct)}</td><td>${fP(g.fg3_pct)}</td><td>${fP(g.ft_pct)}</td>
        <td class="${vcl(g.raptor_total)} ${intensityClass(g.raptor_total)}">${fN(g.raptor_total)}</td>
        <td class="${vcl(g.net_rtg)} ${intensityClass(g.net_rtg)}">${fN(g.net_rtg,1)}</td>
        <td class="${vcl(g.gm_sc)}">${fN(g.gm_sc)}</td>
        <td class="${vcl(g.plus_minus)}">${fN(g.plus_minus,1)}</td>
        <td class="vm">${g.val_lega!=null?fI(g.val_lega):'—'}</td>
      </tr>`;
    }).join('');

    renderGamesChart(activeGameMetric);

    // Se la schermata statistiche è già stata caricata, aggiorna le sparklines
    if (document.getElementById('stats-detail-wrap')?.children.length) {
      const main = cachedSplits?.['TOT'] ?? cachedSplits?.['RS'] ?? (cachedSplits ? Object.values(cachedSplits)[0] : null);
      if (main) renderStatsDetail(main);
    }
  } catch(e) { tbody.innerHTML=`<tr><td colspan="16" class="hpg-error">⚠️ ${esc(e.message)}</td></tr>`; }
}

function renderGamesChart(metric) {
  activeGameMetric = metric;
  const LABEL = {gm_sc:'GmSc',raptor_total:'RAPTOR',net_rtg:'NetRtg',points:'Punti',plus_minus:'+/-'};
  const rows   = [...cachedGames].reverse();
  const labels = rows.map(g => (g.game_num ? '#'+g.game_num+' ' : '') + (g.opponent ?? g.game ?? '—'));
  const vals   = rows.map(g => g[metric] ?? null);
  document.getElementById('games-chart-label').textContent = LABEL[metric]??metric;
  destroyChart('games');
  const ctx = document.getElementById('chart-games')?.getContext('2d');
  if (!ctx || !vals.length) return;
  // Color each point by value
  const ptColors = vals.map(v=>v==null?'#475569':v>=0?'#4ade80':'#f87171');
  newChart('games', ctx, {
    type:'line',
    data:{ labels, datasets:[{
      data:vals,
      borderColor:'#f97316', backgroundColor:'rgba(249,115,22,.07)',
      borderWidth:2, tension:.3, pointRadius:4, spanGaps:true,
      pointBackgroundColor: ptColors, pointBorderColor: ptColors,
    }]},
    options:{
      responsive:true, maintainAspectRatio:false,
      plugins:{ legend:{display:false}, tooltip:{ callbacks:{ title:t=>`vs ${t[0].label}` } } },
      scales:{
        x:{ ticks:{ maxRotation:35, font:{size:10} }, grid:{color:gridC} },
        y:{ grid:{color:gridC}, ticks:{ callback:v=>(v>=0?'+':'')+v } },
      }
    }
  });
}

// ══════════════════════════════════════════════════════════
// ON/OFF
// ══════════════════════════════════════════════════════════
async function loadOnOff() {
  const el    = document.getElementById('onoff-body');
  const rawEl = document.getElementById('onoff-raw-body');
  try {
    const rows = await apiFetch(`/player/${pid}/onoff${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`);
    if (!rows?.length) { el.innerHTML=rawEl.innerHTML='<div class="hpg-empty">Dati On/Off non disponibili</div>'; return; }
    const row = rows[0];
    document.getElementById('onoff-comp').textContent = row.Competition ?? '';

    const METRICS = [
      ['Def Rating',    'DefRating_On',        'DefRating_Off',        'DefRating_Diff',        true],
      ['FG2% concesso', 'Fg2PctAllowed_On',    'Fg2PctAllowed_Off',    'Fg2PctAllowed_Diff',    true],
      ['FG3% concesso', 'Fg3PctAllowed_On',    'Fg3PctAllowed_Off',    'Fg3PctAllowed_Diff',    true],
      ['TOV forzati/p', 'ForcedTovPerPoss_On', 'ForcedTovPerPoss_Off', 'ForcedTovPerPoss_Diff', false],
      ['OR concessi/p', 'OrebPerPoss_On',      'OrebPerPoss_Off',      'OrebPerPoss_Diff',      true],
    ];
    const vclOO = (v,inv) => v==null?'vm':(inv?(Number(v)<0?'vp':Number(v)>0?'vn':'vm'):vcl(v));

    // ── Hero: tre colonne On · Diff · Off ──────────────
    const statsHtml = side => METRICS.map(([lbl,on,off,,inv])=>{
      const key = side==='on'?on:off; const v = row[key];
      return `<div class="hpg-oo-stat"><div class="hpg-oo-val ${vclOO(v,inv)}">${fF(v)}</div><div class="hpg-oo-lbl">${lbl}</div></div>`;
    }).join('');
    const diffHtml = () => METRICS.map(([lbl,,,diff,inv])=>{
      const v = row[diff]; if(v==null) return '<div class="hpg-oo-diff oo-neu">—</div>';
      const cls = inv?(Number(v)<0?'oo-pos':'oo-neg'):(Number(v)>0?'oo-pos':'oo-neg');
      const txt = (Number(v)>0?'+':'')+Number(v).toFixed(2);
      // Intensità colore proporzionale all'entità del diff
      return `<div class="hpg-oo-diff ${cls}" title="${lbl}: ${txt}">${txt}</div>`;
    }).join('');

    el.innerHTML = `
    <div class="hpg-onoff">
      <div class="hpg-oo-col">
        <span class="hpg-oo-badge hpg-oo-on">Con in campo</span>
        ${statsHtml('on')}
        <div class="hpg-oo-stat"><div class="hpg-oo-val vm">${row.Poss_On!=null?Math.round(row.Poss_On):'—'}</div><div class="hpg-oo-lbl">Possessi</div></div>
      </div>
      <div class="hpg-oo-div"><span>Δ</span>${diffHtml()}</div>
      <div class="hpg-oo-col">
        <span class="hpg-oo-badge hpg-oo-off">Senza</span>
        ${statsHtml('off')}
        <div class="hpg-oo-stat"><div class="hpg-oo-val vm">${row.Poss_Off!=null?Math.round(row.Poss_Off):'—'}</div><div class="hpg-oo-lbl">Possessi</div></div>
      </div>
    </div>
    <p style="margin-top:.7rem;font-size:.69rem;color:var(--hm-text-3);line-height:1.5">
      I possessi "Senza" includono tutti i possessi della squadra quando il giocatore non è in campo — è normale che siano molto più alti.
    </p>`;

    // ── Raw table ──────────────────────────────────────
    rawEl.innerHTML = `<table class="hpg-oo-raw">
      <thead><tr><th class="l" style="text-align:left">Metrica</th><th>Con</th><th>Senza</th><th>Δ</th></tr></thead>
      <tbody>${[
        ['Pt ammessi',   'PointsAllowed_On', 'PointsAllowed_Off'],
        ['FGA2 concessi','Fga2Allowed_On',   'Fga2Allowed_Off'],
        ['FGM2 concessi','Fgm2Allowed_On',   'Fgm2Allowed_Off'],
        ['FGA3 concessi','Fga3Allowed_On',   'Fga3Allowed_Off'],
        ['FGM3 concessi','Fgm3Allowed_On',   'Fgm3Allowed_Off'],
        ['FTA concessi', 'FtaAllowed_On',    'FtaAllowed_Off'],
        ['TOV forzati',  'TovForced_On',     'TovForced_Off'],
        ['OR avvers.',   'OrebAllowed_On',   'OrebAllowed_Off'],
      ].map(([l,on,off])=>{
        const vo=row[on]??null, vf=row[off]??null;
        const d = vo!=null&&vf!=null ? (vo-vf).toFixed(1) : '—';
        const dClr = d!=='—' ? (parseFloat(d)<0?'color:#4ade80':'color:#f87171') : '';
        return `<tr><td class="l" style="font-family:inherit;color:var(--hm-text-2)">${l}</td><td>${vo!=null?fI(vo):'—'}</td><td>${vf!=null?fI(vf):'—'}</td><td style="${dClr}">${d}</td></tr>`;
      }).join('')}</tbody></table>`;

    // ── Bar chart ──────────────────────────────────────
    setTimeout(() => {
      destroyChart('onoff');
      const ctxB = document.getElementById('chart-onoff')?.getContext('2d');
      if (ctxB) newChart('onoff', ctxB, {
        type:'bar',
        data:{ labels: METRICS.map(m=>m[0]),
          datasets:[
            {label:'Con',   data:METRICS.map(m=>row[m[1]]??null), backgroundColor:'rgba(74,222,128,.45)', borderColor:'#4ade80', borderWidth:1.5, borderRadius:3},
            {label:'Senza', data:METRICS.map(m=>row[m[2]]??null), backgroundColor:'rgba(148,163,184,.25)', borderColor:'#94a3b8', borderWidth:1.5, borderRadius:3},
          ]},
        options:{ responsive:true, maintainAspectRatio:false,
          plugins:{ legend:{ labels:{ color:textC, boxWidth:10, font:{size:11} } } },
          scales:{ x:{ ticks:{font:{size:9}}, grid:{color:gridC} }, y:{ grid:{color:gridC} } },
        }
      });

      // ── Radar On vs Off ─────────────────────────────
      destroyChart('onoff-radar');
      const ctxR = document.getElementById('chart-onoff-radar')?.getContext('2d');
      if (ctxR) {
        const onVals  = METRICS.map(m=>row[m[1]]??null);
        const offVals = METRICS.map(m=>row[m[2]]??null);
        // Normalizza 70-120 → 0-100 per radar
        const norm = v => v!=null ? Math.min(100, Math.max(0, ((v - 70) / 50) * 100)) : null;
        newChart('onoff-radar', ctxR, {
          type:'radar',
          data:{
            labels: METRICS.map(m=>m[0]),
            datasets:[
              { label:'Con',   data: onVals.map(norm),  backgroundColor:'rgba(74,222,128,.15)', borderColor:'#4ade80', borderWidth:2, pointRadius:3, pointBackgroundColor:'#4ade80' },
              { label:'Senza', data: offVals.map(norm), backgroundColor:'rgba(148,163,184,.1)', borderColor:'#94a3b8', borderWidth:2, pointRadius:3, pointBackgroundColor:'#94a3b8' },
            ]
          },
          options:{ responsive:true, maintainAspectRatio:false,
            scales:{ r:{ min:0, max:100, ticks:{ display:false }, grid:{color:gridC}, pointLabels:{font:{size:10}} }},
            plugins:{ legend:{ labels:{ color:textC, boxWidth:10, font:{size:11} } } },
          }
        });
      }
    }, 60);

  } catch(e) { el.innerHTML=rawEl.innerHTML='<div class="hpg-empty">On/Off non disponibile</div>'; }
}

// ══════════════════════════════════════════════════════════
// PBP — Revamp: tendenze + conversion rate + donut
// ══════════════════════════════════════════════════════════
async function loadPbp() {
  const barsEl = document.getElementById('pbp-bars');
  const convEl = document.getElementById('pbp-conv');
  try {
    // Due fetch in parallelo: distribuzione PBP + conversion rate
    const [data, convData] = await Promise.all([
      apiFetch(`/player/${pid}/shots${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`),
      apiFetch(`/player/${pid}/playtypes${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`).catch(()=>null),
    ]);

    const pts = data.play_types ?? [];
    document.getElementById('pbp-count').textContent = data.total ? `${data.total} azioni` : '';

    if (!pts.length) { barsEl.innerHTML='<div class="hpg-empty">Dati PBP non disponibili</div>'; return; }

    const COLORS = ['#f97316','#60a5fa','#4ade80','#a78bfa','#fde047','#f472b6','#34d399','#fb923c','#818cf8','#86efac'];

    // ── Barre principali con share% ───────────────────
    barsEl.innerHTML = pts.map((p,i)=>`
      <div style="margin-bottom:.45rem">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:.22rem">
          <span style="font-size:.78rem;font-weight:600;color:var(--hm-text)">${esc(p.play_type)}</span>
          <span style="font-family:var(--hm-mono);font-size:.73rem;color:var(--hm-text-2)">${p.count} <span style="color:var(--hm-text-3);font-size:.67rem">(${p.pct}%)</span></span>
        </div>
        <div style="height:5px;background:rgba(255,255,255,.06);border-radius:999px;overflow:hidden">
          <div style="height:100%;width:${Math.round(p.pct)}%;background:${COLORS[i%COLORS.length]};border-radius:999px"></div>
        </div>
      </div>`).join('');

    // ── Donut ─────────────────────────────────────────
    setTimeout(() => {
      destroyChart('donut');
      const ctx = document.getElementById('chart-donut')?.getContext('2d');
      if (ctx) newChart('donut', ctx, {
        type:'doughnut',
        data:{ labels:pts.map(p=>p.play_type), datasets:[{
          data:pts.map(p=>p.count),
          backgroundColor: pts.map((_,i)=>COLORS[i%COLORS.length]+'cc'),
          borderColor:     pts.map((_,i)=>COLORS[i%COLORS.length]),
          borderWidth:1.5, hoverOffset:6,
        }]},
        options:{ responsive:true, maintainAspectRatio:false,
          plugins:{
            legend:{ position:'right', labels:{ color:textC, boxWidth:10, font:{size:10}, padding:8 } },
            tooltip:{ callbacks:{ label: t=>`${t.label}: ${t.parsed} (${pts[t.dataIndex]?.pct}%)` } }
          },
          cutout:'62%',
        }
      });
    }, 60);

    // ── Conversion rate ───────────────────────────────
    if (convEl && convData?.length) {
      convEl.innerHTML = convData.map((c,i) => {
        const conv = c.conv_pct;
        const convClr = conv==null ? '#94a3b8' : conv >= 50 ? '#4ade80' : conv >= 35 ? '#fde047' : '#f87171';
        return `<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;font-size:.74rem">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${COLORS[i%COLORS.length]};flex-shrink:0"></span>
          <span style="flex:1;color:var(--hm-text-2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(c.play_type)}</span>
          <span style="font-family:var(--hm-mono);font-size:.7rem;color:var(--hm-text-3)">${c.total} az.</span>
          <span style="font-family:var(--hm-mono);font-weight:700;font-size:.74rem;color:${convClr};min-width:38px;text-align:right">
            ${conv!=null?conv.toFixed(1)+'%':'—'}
          </span>
        </div>`;
      }).join('') +
      `<p style="margin-top:.6rem;font-size:.67rem;color:var(--hm-text-3)">Conv% = azioni con And1=1 / totale azioni per tipo</p>`;
    } else if (convEl) {
      convEl.innerHTML = '<div class="hpg-empty" style="padding:1rem">Conversion rate non disponibile</div>';
    }

  } catch(e) { barsEl.innerHTML=`<div class="hpg-error">⚠️ ${esc(e.message)}</div>`; }
}

// ══════════════════════════════════════════════════════════
// GIOCATORI SIMILI
// ══════════════════════════════════════════════════════════
async function loadSimilar() {
  const el = document.getElementById('similar-body');
  try {
    const rows = await apiFetch(`/player/${pid}/similar${qs({nation:CFG.nation,year:CFG.year,comp:CFG.comp})}`);
    if (!rows?.length) { el.innerHTML='<div class="hpg-empty">Nessun giocatore simile trovato</div>'; return; }

    const playerUrl = typeof HM_CONFIG!=='undefined' ? window.location.pathname : '/wordpress/giocatore/';

    el.innerHTML = `
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem">
      ${rows.map(p => {
        const init = (p.player_name||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
        const simPct = p.similarity ?? 0;
        const simClr = simPct >= 80 ? '#4ade80' : simPct >= 60 ? '#fde047' : '#94a3b8';
        const url = `${playerUrl}?id=${encodeURIComponent(p.public_id)}&nation=${CFG.nation}&year=${CFG.year}`;
        return `
        <a href="${esc(url)}" style="text-decoration:none">
          <div style="background:var(--hm-bg-2);border:1px solid var(--hm-border);border-radius:12px;padding:.85rem;transition:border-color .15s;cursor:pointer" onmouseenter="this.style.borderColor='var(--s-model)'" onmouseleave="this.style.borderColor='var(--hm-border)'">
            <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.6rem">
              <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,var(--s-model),#7c3aed);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:900;color:#fff;flex-shrink:0">${init}</div>
              <div style="min-width:0">
                <div style="font-size:.79rem;font-weight:700;color:var(--hm-text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.player_name)}</div>
                <div style="font-size:.68rem;color:var(--hm-text-3)">${esc(p.position||'—')} · ${esc(p.team_name||'—')}</div>
              </div>
            </div>
            <!-- Similarità badge -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
              <span style="font-size:.63rem;color:var(--hm-text-3);text-transform:uppercase;letter-spacing:.07em">Similarità</span>
              <span style="font-family:var(--hm-mono);font-weight:800;font-size:.82rem;color:${simClr}">${simPct}%</span>
            </div>
            <div style="height:4px;background:rgba(255,255,255,.06);border-radius:999px;overflow:hidden;margin-bottom:.6rem">
              <div style="height:100%;width:${simPct}%;background:${simClr};border-radius:999px"></div>
            </div>
            <!-- Mini stats -->
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.3rem;text-align:center">
              <div style="background:var(--hm-bg-card);border-radius:5px;padding:.3rem .2rem">
                <div style="font-family:var(--hm-mono);font-size:.77rem;font-weight:700;color:${(p.raptor_total??0)>=0?'#4ade80':'#f87171'}">${p.raptor_total!=null?(p.raptor_total>=0?'+':'')+p.raptor_total.toFixed(1):'—'}</div>
                <div style="font-size:.58rem;color:var(--hm-text-3)">RAPTOR</div>
              </div>
              <div style="background:var(--hm-bg-card);border-radius:5px;padding:.3rem .2rem">
                <div style="font-family:var(--hm-mono);font-size:.77rem;font-weight:700;color:var(--hm-text)">${p.ts_pct!=null?p.ts_pct.toFixed(1)+'%':'—'}</div>
                <div style="font-size:.58rem;color:var(--hm-text-3)">TS%</div>
              </div>
              <div style="background:var(--hm-bg-card);border-radius:5px;padding:.3rem .2rem">
                <div style="font-family:var(--hm-mono);font-size:.77rem;font-weight:700;color:var(--hm-text)">${p.usg_pct!=null?p.usg_pct.toFixed(1)+'%':'—'}</div>
                <div style="font-size:.58rem;color:var(--hm-text-3)">USG%</div>
              </div>
            </div>
          </div>
        </a>`;
      }).join('')}
    </div>
    <p style="margin-top:.75rem;font-size:.69rem;color:var(--hm-text-3)">
      Similarità calcolata su: RAPTOR (×3), LEBRON (×3), USG% (×2), TS% (×2), REB%, AST%, BLK%, STL%, ORtg, DRtg — solo giocatori con ≥100 minuti.
    </p>`;
  } catch(e) {
    el.innerHTML=`<div class="hpg-error">⚠️ ${esc(e.message)}</div>`;
  }
}

// ══════════════════════════════════════════════════════════
// TAB SYSTEM
// ══════════════════════════════════════════════════════════
const loaded = {};

function activateTab(name) {
  document.querySelectorAll('.hpg-tab[data-tab]').forEach(t=>t.classList.toggle('active', t.dataset.tab===name));
  document.querySelectorAll('.hpg-panel').forEach(p=>p.classList.toggle('active', p.id===`panel-${name}`));
  if (!loaded[name]) {
    loaded[name] = true;
    if (name==='stats')   loadSplits();
    if (name==='games')   loadGames();
    if (name==='lineups') loadLineups();
    if (name==='onoff')   loadOnOff();
    if (name==='pbp')     loadPbp();
    if (name==='similar') loadSimilar();
  }
  setTimeout(resizeAllCharts, 50);
}

function refreshAll() {
  ['stats','games','lineups','onoff','pbp','similar'].forEach(k=>{ delete loaded[k]; });
  Promise.allSettled([loadProfile(), loadRole()].map(p=>p.catch(console.error)));
  setTimeout(() => loadPercentiles().catch(console.error), 300);
  const active = document.querySelector('.hpg-tab[data-tab].active')?.dataset?.tab;
  if (active && active!=='overview') { loaded[active]=false; activateTab(active); }
}

// ══════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
  // Applica defaults Chart.js — ora è certamente caricato (head)
  if (chartJsAvail()) {
    Chart.defaults.color = textC;
    Chart.defaults.font  = { family: 'inherit', size: 11 };
  }

  document.querySelectorAll('.hpg-tab[data-tab]').forEach(btn=>{
    btn.addEventListener('click', ()=>activateTab(btn.dataset.tab));
  });

  document.querySelectorAll('[data-cm]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      document.querySelectorAll('[data-cm]').forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      renderGamesChart(btn.dataset.cm);
    });
  });

  document.getElementById('hpg-comp').addEventListener('change', e=>{ CFG.comp=e.target.value; refreshAll(); });
  document.getElementById('hpg-gn').addEventListener('change', ()=>{
    if (loaded.games) { delete loaded.games; activateTab('games'); }
  });

  loaded.overview = true;

  // Caricamento iniziale
  Promise.allSettled([
    loadProfile().catch(e => { document.getElementById('hpg-name').textContent='Errore caricamento'; console.error('[HM] loadProfile:', e.message); }),
    loadRole().catch(e => console.error('[HM] loadRole:', e.message)),
  ]);

  // Percentili dopo 300ms (non bloccante)
  setTimeout(() => loadPercentiles().catch(console.error), 300);

  // Precarica games per sparklines dopo 1.5s
  setTimeout(() => { if (!loaded.games) { loaded.games=true; loadGames().catch(()=>{}); } }, 1500);
});

})();
</script>
<?php
}, 20);
?>

<?php get_footer(); ?>