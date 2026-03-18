<?php
/**
 * Template Name: Leaderboard
 * v7 – Filtri moderni, cache badge, tutti i dati, fix JS
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$nation    = isset($_GET['nation']) ? sanitize_text_field(wp_unslash($_GET['nation'])) : 'GRC';
$season    = isset($_GET['season']) ? sanitize_text_field(wp_unslash($_GET['season'])) : '2024';
$comp      = isset($_GET['comp'])   ? sanitize_text_field(wp_unslash($_GET['comp']))   : 'RS';
$cache_ttl = (int) get_option('hm_cache_ttl', 1800);

$player_url = '';
foreach (['player','profilo-giocatore','giocatore','player-profile'] as $slug) {
    $p = get_page_by_path($slug);
    if ($p) { $player_url = get_permalink($p->ID); break; }
}
if (!$player_url) $player_url = home_url('/player/');
?>

<style>
/* ── Filter card ─────────────────────────────────────── */
.hm-breadcrumb{display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:#888;margin-bottom:1.5rem}
.hm-breadcrumb a{color:#888;text-decoration:none}.hm-breadcrumb a:hover{color:#3b82f6}
.hm-breadcrumb svg{width:12px;height:12px;opacity:.5}
.hm-lb-header{display:flex;align-items:baseline;gap:.8rem;margin-bottom:1.4rem;flex-wrap:wrap}
.hm-lb-header h2{margin:0;font-size:1.45rem;font-weight:700;letter-spacing:-.02em}
.hm-lb-status{font-size:.8rem;color:#888;font-weight:500}
.hm-cache-badge{font-size:.72rem;padding:.2rem .55rem;border-radius:999px;font-weight:600;letter-spacing:.02em}
.hm-cache-hit{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.hm-cache-miss{background:#eff6ff;color:#2563eb;border:1px solid #bfdbfe}
.hm-filter-card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:1rem 1.2rem;margin-bottom:.9rem;box-shadow:0 1px 4px rgba(0,0,0,.04)}
.hm-filter-card-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-bottom:.65rem}
.hm-sel-group{display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end}
.hm-sel-wrap{display:flex;flex-direction:column;gap:.25rem;min-width:110px}
.hm-sel-wrap label{font-size:.7rem;font-weight:600;color:#6b7280;letter-spacing:.04em}
.hm-sel{appearance:none;background:#f9fafb url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right .65rem center;border:1.5px solid #e5e7eb;border-radius:8px;padding:.38rem 2rem .38rem .7rem;font-size:.82rem;font-weight:500;color:#111827;cursor:pointer;transition:border-color .15s,box-shadow .15s;white-space:nowrap}
.hm-sel:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.hm-sel:hover{border-color:#d1d5db}
.hm-sel-divider{width:1px;background:#e5e7eb;align-self:stretch;margin:0 .2rem}
.hm-metric-bar{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.hm-bar-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9ca3af;margin-right:.2rem;white-space:nowrap}
.hm-pill{display:inline-flex;align-items:center;padding:.3rem .85rem;border-radius:999px;border:1.5px solid #e5e7eb;background:#fff;font-size:.78rem;font-weight:600;color:#374151;cursor:pointer;transition:all .15s ease;white-space:nowrap;user-select:none}
.hm-pill:hover{border-color:#3b82f6;color:#2563eb;background:#eff6ff}
.hm-pill.active{background:#1d4ed8;border-color:#1d4ed8;color:#fff;box-shadow:0 2px 8px rgba(29,78,216,.25)}
.hm-pill.active:hover{background:#1e40af;border-color:#1e40af}
.hm-adv-toggle{display:inline-flex;align-items:center;gap:.35rem;font-size:.78rem;font-weight:600;color:#6b7280;cursor:pointer;padding:.3rem .7rem;border-radius:8px;border:1.5px solid #e5e7eb;background:#fff;transition:all .15s;white-space:nowrap}
.hm-adv-toggle:hover{border-color:#d1d5db;color:#374151;background:#f9fafb}
.hm-adv-toggle.open{border-color:#3b82f6;color:#2563eb;background:#eff6ff}
.hm-adv-toggle .chevron{width:14px;height:14px;transition:transform .2s}
.hm-adv-toggle.open .chevron{transform:rotate(180deg)}
.hm-adv-section{overflow:hidden;max-height:0;transition:max-height .3s ease,opacity .25s ease;opacity:0}
.hm-adv-section.open{max-height:200px;opacity:1}
@media(max-width:640px){.hm-sel-divider{display:none}.hm-sel-wrap{min-width:calc(50% - .3rem)}.hm-filter-card{padding:.8rem .9rem}}
</style>

<div class="hm-wrap" style="margin-top:1rem;">

  <!-- Breadcrumb -->
  <nav class="hm-breadcrumb">
    <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
    <span>Leaderboard</span>
  </nav>

  <!-- Header -->
  <div class="hm-lb-header">
    <h2>🏅 Leaderboard</h2>
    <span id="hm-lb-status" class="hm-lb-status"></span>
    <span id="hm-lb-cache-badge" class="hm-cache-badge"></span>
  </div>

  <!-- Card filtri principali -->
  <div class="hm-filter-card">
    <div class="hm-filter-card-label">Contesto</div>
    <div class="hm-sel-group">

      <div class="hm-sel-wrap">
        <label for="hm-filter-nation">Nazione</label>
        <select id="hm-filter-nation" class="hm-sel">
          <?php $nations=['GRC'=>'🇬🇷 Grecia','ITA'=>'🇮🇹 Italia','DEU'=>'🇩🇪 Germania',
                          'FRA'=>'🇫🇷 Francia','SPA'=>'🇪🇸 Spagna','TUR'=>'🇹🇷 Turchia',
                          'SRB'=>'🇷🇸 Serbia','LTU'=>'🇱🇹 Lituania'];
          foreach($nations as $c=>$l):?>
            <option value="<?php echo esc_attr($c);?>" <?php selected($nation,$c);?>><?php echo esc_html($l);?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div class="hm-sel-wrap">
        <label for="hm-filter-season">Stagione</label>
        <select id="hm-filter-season" class="hm-sel">
          <?php foreach(['2025','2024','2023','2022'] as $y):?>
            <option value="<?php echo $y;?>" <?php selected($season,$y);?>><?php echo $y;?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div class="hm-sel-wrap">
        <label for="hm-filter-comp">Competizione</label>
        <select id="hm-filter-comp" class="hm-sel">
          <?php $comps=['RS'=>'Regular Season','PO'=>'Playoff','Home'=>'Casa','Away'=>'Trasferta'];
          foreach($comps as $v=>$l):?>
            <option value="<?php echo esc_attr($v);?>" <?php selected($comp,$v);?>><?php echo esc_html($l);?></option>
          <?php endforeach;?>
        </select>
      </div>

      <div class="hm-sel-divider"></div>

      <div class="hm-sel-wrap">
        <label for="hm-filter-pos">Posizione</label>
        <select id="hm-filter-pos" class="hm-sel">
          <option value="all">Tutte</option>
          <option value="PG">PG — Playmaker</option>
          <option value="SG">SG — Guardia</option>
          <option value="SF">SF — Ala Piccola</option>
          <option value="PF">PF — Ala Grande</option>
          <option value="C">C — Centro</option>
        </select>
      </div>

      <div class="hm-sel-wrap">
        <label for="hm-filter-minmin">Min. giocati</label>
        <select id="hm-filter-minmin" class="hm-sel">
          <option value="0">Tutti</option>
          <option value="50">≥ 50</option>
          <option value="100">≥ 100</option>
          <option value="150" selected>≥ 150</option>
          <option value="200">≥ 200</option>
          <option value="300">≥ 300</option>
        </select>
      </div>

      <div class="hm-sel-wrap" style="justify-content:flex-end;">
        <label style="opacity:0;pointer-events:none;user-select:none">‎</label>
        <button type="button" id="hm-adv-toggle" class="hm-adv-toggle" aria-expanded="false">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
          Età
          <svg class="chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
      </div>

    </div>

    <!-- Età collassabile -->
    <div id="hm-adv-section" class="hm-adv-section">
      <div class="hm-sel-group" style="margin-top:.8rem;padding-top:.8rem;border-top:1px solid #f3f4f6;">
        <div class="hm-sel-wrap">
          <label for="hm-filter-age-min">Età minima</label>
          <select id="hm-filter-age-min" class="hm-sel">
            <?php for($a=18;$a<=40;$a++):?>
              <option value="<?php echo $a;?>"><?php echo $a;?> anni</option>
            <?php endfor;?>
          </select>
        </div>
        <div class="hm-sel-wrap">
          <label for="hm-filter-age-max">Età massima</label>
          <select id="hm-filter-age-max" class="hm-sel">
            <?php for($a=18;$a<=42;$a++):?>
              <option value="<?php echo $a;?>" <?php echo $a===42?'selected':'';?>>
                <?php echo $a===42?'42+':$a.' anni';?>
              </option>
            <?php endfor;?>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Card metrica pills -->
  <div class="hm-filter-card" style="padding:.7rem 1.2rem;">
    <div class="hm-metric-bar">
      <span class="hm-bar-label">Metrica</span>
      <button class="hm-pill active" data-hm-metric="raptor">RAPTOR</button>
      <button class="hm-pill" data-hm-metric="lebron">LEBRON</button>
      <button class="hm-pill" data-hm-metric="netrtg">Net Rtg</button>
      <button class="hm-pill" data-hm-metric="bpm">BPM</button>
      <button class="hm-pill" data-hm-metric="ws">Win Shares</button>
      <button class="hm-pill" data-hm-metric="vorp">VORP</button>
      <button class="hm-pill" data-hm-metric="pie">PIE</button>
      <button class="hm-pill" data-hm-metric="gmsc">GmSc</button>
    </div>
  </div>

  <!-- Tabella -->
  <div class="hm-table-scroll">
    <table class="hm-table" id="hm-leaderboard-table">
      <thead id="hm-lb-thead"></thead>
      <tbody id="hm-lb-tbody"><tr><td colspan="12" class="hm-loading-cell">Caricamento…</td></tr></tbody>
    </table>
  </div>

</div>

<script>
window.HM_LEADERBOARD = {
  nation:     '<?php echo esc_js($nation); ?>',
  season:     '<?php echo esc_js($season); ?>',
  comp:       '<?php echo esc_js($comp); ?>',
  player_url: '<?php echo esc_js(rtrim($player_url,'/')); ?>',
  cache_ttl:  <?php echo (int)$cache_ttl; ?>
};

// ── Toggle età ────────────────────────────────────────────────────
(function() {
  const btn = document.getElementById('hm-adv-toggle');
  const sec = document.getElementById('hm-adv-section');
  if (btn && sec) {
    btn.addEventListener('click', () => {
      const open = sec.classList.toggle('open');
      btn.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', String(open));
    });
  }
})();

// ── Formatters ────────────────────────────────────────────────────
const fmtTs  = v => v != null ? (v * 100).toFixed(1) + '%' : '—';
const fmtUsg = v => v != null ? v.toFixed(2) + '×' : '—';
const fmtRtg = v => v != null ? v.toFixed(1) : '—';
const fmtN   = v => v != null ? (v > 0 ? '+' : '') + v.toFixed(2) : '—';
const fmtAge = v => {
  const n = Number(v);
  return Number.isFinite(n) ? n : '—';
};
const hue    = p => Math.round((p ?? 50) * 1.2);

const _r     = ()      => ({ t:'rank' });
const _p     = r       => ({ t:'player', name:r.player_name, id:r.public_id, nation:r.nation, year:r.year });
const _pri   = (v,p)   => ({ t:'primary', v, p });
const _bar   = p       => ({ t:'bar', p });

// ── Schemi ────────────────────────────────────────────────────────
const SCHEMAS = {
  raptor: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','RAPTOR Off','RAPTOR Def','RAPTOR','TS%','USG×','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               fmtN(r.raptor_off),fmtN(r.raptor_def),_pri(r.raptor_total,r.pct_rank),
               fmtTs(r.ts_pct),fmtUsg(r.usg_pct),_bar(r.pct_rank)]
  },
  lebron: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','LEBRON Off','LEBRON Def','LEBRON','TS%','USG×','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               fmtN(r.lebron_off),fmtN(r.lebron_def),_pri(r.lebron_total,r.pct_rank),
               fmtTs(r.ts_pct),fmtUsg(r.usg_pct),_bar(r.pct_rank)]
  },
  netrtg: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','O Rtg','D Rtg','Net Rtg','TS%','EFG%','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               fmtRtg(r.o_rtg),fmtRtg(r.d_rtg),_pri(r.net_rtg,r.pct_rank),
               fmtTs(r.ts_pct),fmtTs(r.efg_pct),_bar(r.pct_rank)]
  },
  bpm: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','BPM','VORP','RAPTOR','TS%','USG×','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               _pri(r.metric_val,r.pct_rank),fmtN(r.vorp),fmtN(r.raptor_total),
               fmtTs(r.ts_pct),fmtUsg(r.usg_pct),_bar(r.pct_rank)]
  },
  ws: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','Win Shares','BPM','RAPTOR','TS%','USG×','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               _pri(r.metric_val,r.pct_rank),fmtN(r.bpm),fmtN(r.raptor_total),
               fmtTs(r.ts_pct),fmtUsg(r.usg_pct),_bar(r.pct_rank)]
  },
  vorp: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','VORP','BPM','RAPTOR','TS%','USG×','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               _pri(r.metric_val,r.pct_rank),fmtN(r.bpm),fmtN(r.raptor_total),
               fmtTs(r.ts_pct),fmtUsg(r.usg_pct),_bar(r.pct_rank)]
  },
  pie: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','PIE','RAPTOR','Net Rtg','TS%','EFG%','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               _pri(r.metric_val,r.pct_rank),fmtN(r.raptor_total),fmtRtg(r.net_rtg),
               fmtTs(r.ts_pct),fmtTs(r.efg_pct),_bar(r.pct_rank)]
  },
  gmsc: {
    heads: ['#','Giocatore','Età','Squadra','Pos','Min','GmSc','RAPTOR','Net Rtg','TS%','EFG%','Percentile'],
    row: r => [_r(),_p(r),fmtAge(r.age),r.team_name,r.position,r.minutes,
               _pri(r.metric_val,r.pct_rank),fmtN(r.raptor_total),fmtRtg(r.net_rtg),
               fmtTs(r.ts_pct),fmtTs(r.efg_pct),_bar(r.pct_rank)]
  }
};

// ── Cell renderer ─────────────────────────────────────────────────
function cellHtml(cell, ci, rank) {
  if (ci === 0 || cell?.t === 'rank') return `<td class="hm-rank">${rank}</td>`;
  if (cell === null || cell === undefined) return '<td class="hm-null">—</td>';
  if (cell?.t === 'player') {
    const url = `${HM_LEADERBOARD.player_url}?id=${encodeURIComponent(cell.id)}&nation=${encodeURIComponent(cell.nation||HM_LEADERBOARD.nation)}&year=${encodeURIComponent(cell.year||HM_LEADERBOARD.season)}`;
    return `<td class="hm-player-cell"><a href="${url}" class="hm-player-link">${cell.name}</a></td>`;
  }
  if (cell?.t === 'primary') {
    const p = cell.p ?? 50;
    const cls = p >= 75 ? 'hm-val-hi' : p <= 25 ? 'hm-val-lo' : '';
    const txt = cell.v != null ? (cell.v > 0 ? '+' : '') + Number(cell.v).toFixed(2) : '—';
    return `<td class="hm-metric-primary ${cls}">${txt}</td>`;
  }
  if (cell?.t === 'bar') {
    const p = cell.p ?? 0, h = hue(p);
    return `<td><span class="hm-pct-bar-wrap"><span class="hm-pct-bar" style="width:${p}%;background:hsl(${h},85%,42%)"></span><span class="hm-pct-val">${p.toFixed(0)}°</span></span></td>`;
  }
  if (typeof cell === 'number') return `<td>${isNaN(cell) ? '—' : cell}</td>`;
  if (typeof cell === 'string') return `<td>${cell || '—'}</td>`;
  return '<td>—</td>';
}

function renderHeaders(metric) {
  const s = SCHEMAS[metric] || SCHEMAS.raptor;
  document.getElementById('hm-lb-thead').innerHTML =
    '<tr>' + s.heads.map((h,i) =>
      `<th${i===1?' style="text-align:left;min-width:160px"':''}>${h}</th>`
    ).join('') + '</tr>';
}

function renderRows(data, metric) {
  const s = SCHEMAS[metric] || SCHEMAS.raptor;
  const tbody = document.getElementById('hm-lb-tbody');
  if (!data?.length) {
    tbody.innerHTML = `<tr><td colspan="${s.heads.length}" class="hm-empty-cell">Nessun dato per i filtri selezionati.</td></tr>`;
    return;
  }
  tbody.innerHTML = data.map((r,i) =>
    '<tr>' + s.row(r).map((c,ci) => cellHtml(c,ci,i+1)).join('') + '</tr>'
  ).join('');
}

function updateCacheBadge(fromCache, cachedAt) {
  const badge = document.getElementById('hm-lb-cache-badge');
  if (!badge) return;
  if (fromCache && cachedAt) {
    const sec  = Math.round(Date.now()/1000 - cachedAt);
    const mins = Math.floor(sec/60);
    const age  = mins > 0 ? `${mins} min fa` : 'pochi secondi fa';
    badge.textContent = `📦 Da cache · ${age}`;
    badge.className   = 'hm-cache-badge hm-cache-hit';
    badge.title       = `TTL: ${HM_LEADERBOARD.cache_ttl}s`;
  } else {
    badge.textContent = '🔄 Dati freschi';
    badge.className   = 'hm-cache-badge hm-cache-miss';
  }
}

// ── Fetch ─────────────────────────────────────────────────────────
let activeMetric = 'raptor';

async function fetchLeaderboard() {
  const s = SCHEMAS[activeMetric] || SCHEMAS.raptor;
  document.getElementById('hm-lb-tbody').innerHTML =
    `<tr><td colspan="${s.heads.length}" class="hm-loading-cell">Caricamento…</td></tr>`;
  const params = new URLSearchParams({
    nation:  HM_LEADERBOARD.nation,
    year:    HM_LEADERBOARD.season,
    comp:    HM_LEADERBOARD.comp,
    metric:  activeMetric,
    limit:   200,
    min_min: document.getElementById('hm-filter-minmin')?.value  ?? 150,
    pos:     document.getElementById('hm-filter-pos')?.value     ?? 'all',
    age_min: document.getElementById('hm-filter-age-min')?.value ?? 0,
    age_max: document.getElementById('hm-filter-age-max')?.value ?? 42,
  });
  try {
    const res  = await fetch(`/wp-json/hoopmetrics/v1/leaderboard/players?${params}`);
    const json = await res.json();
    if (json.success) {
      renderRows(json.data.data, activeMetric);
      const tot = json.data.meta?.total ?? '';
      document.getElementById('hm-lb-status').textContent = tot ? `${tot} giocatori` : '';
      updateCacheBadge(json.data.meta?.from_cache, json.data.meta?.cached_at);
    } else {
      document.getElementById('hm-lb-tbody').innerHTML =
        `<tr><td colspan="${s.heads.length}" class="hm-error-cell">${json.error ?? 'Errore sconosciuto'}</td></tr>`;
    }
  } catch(e) {
    document.getElementById('hm-lb-tbody').innerHTML =
      `<tr><td colspan="${s.heads.length}" class="hm-error-cell">Errore di rete. Riprova.</td></tr>`;
  }
}

// ── Init ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  renderHeaders(activeMetric);
  fetchLeaderboard();

  // Metric pills  ← usa .hm-pill sia per deselezionare che per selezionare
  document.querySelectorAll('.hm-pill').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.hm-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeMetric = btn.dataset.hmMetric;
      renderHeaders(activeMetric);
      fetchLeaderboard();
    });
  });

  // Contesto
  document.getElementById('hm-filter-nation').addEventListener('change', e => { HM_LEADERBOARD.nation=e.target.value; fetchLeaderboard(); });
  document.getElementById('hm-filter-season').addEventListener('change', e => { HM_LEADERBOARD.season=e.target.value; fetchLeaderboard(); });
  document.getElementById('hm-filter-comp').addEventListener('change',   e => { HM_LEADERBOARD.comp  =e.target.value; fetchLeaderboard(); });

  // Filtri avanzati con debounce
  let debounceTimer;
  ['hm-filter-pos','hm-filter-age-min','hm-filter-age-max','hm-filter-minmin'].forEach(id => {
    document.getElementById(id)?.addEventListener('change', () => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(fetchLeaderboard, 300);
    });
  });
});
</script>

<?php get_footer(); ?>
