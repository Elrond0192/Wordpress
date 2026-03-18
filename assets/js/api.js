/**
 * Court Analytics – api.js v1.1
 * Endpoint pubblici → nessun nonce richiesto.
 */
(function () {
  'use strict';

  const state = {
    nation : (typeof HM_CONFIG !== 'undefined' ? HM_CONFIG.default_nation : 'GRC'),
    year   : (typeof HM_CONFIG !== 'undefined' ? HM_CONFIG.default_year   : '2024'),
    comp   : 'RS',
    metric : 'RaptorTotal',
  };

  // ── Fetch senza nonce (endpoint pubblici read-only) ───────────────────
  const BASE = (typeof HM_CONFIG !== 'undefined' ? HM_CONFIG.rest_url : '/wp-json/hoopmetrics/v1');

  async function apiFetch(endpoint, params = {}) {
    const url = new URL(BASE + endpoint, window.location.origin);
    Object.entries(params).forEach(([k, v]) => {
      if (v !== '' && v !== null && v !== undefined) url.searchParams.set(k, v);
    });

    const res = await fetch(url.toString(), {
      headers    : { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    if (!res.ok) {
      const txt = await res.text().catch(() => '');
      throw new Error(`HTTP ${res.status}${txt ? ': ' + txt.slice(0,120) : ''}`);
    }

    const json = await res.json();
    if (!json.success) throw new Error(json.error ?? 'Errore API');
    return json.data;
  }

  // ── Percentile bar ────────────────────────────────────────────────────
  function pctBar(pct) {
    if (pct === null || pct === undefined) return '<span class="c-muted">—</span>';
    const n   = parseFloat(pct);
    const cls = n >= 75 ? 'hp-e' : n >= 50 ? 'hp-g' : n >= 25 ? 'hp-m' : 'hp-b';
    return `<div class="pct-bar-wrap" title="${n.toFixed(1)}°">
              <div class="pct-bar ${cls}" style="width:${Math.min(n,100)}%"></div>
              <span class="pct-label">${n.toFixed(0)}</span>
            </div>`;
  }

  function statCell(v, d = 2) {
    if (v === null || v === undefined) return '<td class="c-muted">—</td>';
    const n = parseFloat(v);
    return `<td class="${n > 0 ? 'c-pos' : n < 0 ? 'c-neg' : ''}">${n > 0 ? '+' : ''}${n.toFixed(d)}</td>`;
  }

  // ══════════════════════════════════════════════════════════════════
  //  DASHBOARD
  // ══════════════════════════════════════════════════════════════════
  async function loadDashboardKPI() {
    const el = document.getElementById('hm-kpi-row');
    if (!el) return;
    try {
      const d = await apiFetch('/dashboard/summary', {
        nation: state.nation, year: state.year, comp: state.comp,
      });
      const kpis = [
        { icon:'📊', label:'RAPTOR MEDIO TOP-50',    val: d.raptor_avg_top50  },
        { icon:'⚡', label:'LEBRON MEDIO TOP-50',    val: d.lebron_avg_top50  },
        { icon:'🏠', label:'DIFF. CASA VS TRASFERTA',val: d.home_away_netrtg  },
        { icon:'🛡️', label:'DEF RTG MEDIO ONCOURT',  val: d.def_rtg_avg_top50 },
      ];
      el.innerHTML = kpis.map(k => {
        const n    = parseFloat(k.val);
        const sign = n > 0 ? '+' : '';
        const cls  = n > 0 ? 'c-pos' : n < 0 ? 'c-neg' : '';
        return `<div class="kpi-card">
          <div class="kpi-icon">${k.icon}</div>
          <div class="kpi-val ${cls}">${isNaN(n) ? '—' : sign + n.toFixed(1)}</div>
          <div class="kpi-label">${k.label}</div>
        </div>`;
      }).join('');
    } catch(e) {
      console.error('[Court Analytics] KPI error:', e);
      el.innerHTML = `<div class="hm-error">⚠️ ${e.message}</div>`;
    }
  }

  async function loadNationsOverview() {
    const el = document.getElementById('hm-nations-grid');
    if (!el) return;
    el.innerHTML = HmUtil.spinner();
    try {
      const rows = await apiFetch('/dashboard/nations', { year: state.year, comp: state.comp });
      el.innerHTML = rows.map(r => `
        <a href="?page=nation&n=${r.nation}&year=${r.year}" class="nation-card">
          <div class="nation-flag">${r.flag ?? '🏀'}</div>
          <div class="nation-name">${HmUtil.escHtml(r.league_name)}</div>
          <div class="nation-stats">
            <span>RAPTOR <strong class="${parseFloat(r.raptor_avg)>=0?'c-pos':'c-neg'}">${r.raptor_avg !== null ? (r.raptor_avg > 0 ? '+' : '') + r.raptor_avg : '—'}</strong></span>
            <span>NetRtg <strong class="${parseFloat(r.net_rtg_avg)>=0?'c-pos':'c-neg'}">${r.net_rtg_avg !== null ? (r.net_rtg_avg > 0 ? '+' : '') + r.net_rtg_avg : '—'}</strong></span>
          </div>
          <div class="nation-players">${r.total_players} giocatori</div>
        </a>`
      ).join('');
    } catch(e) {
      console.error('[Court Analytics] Nations error:', e);
      el.innerHTML = `<div class="hm-error">⚠️ ${e.message}</div>`;
    }
  }

  // ══════════════════════════════════════════════════════════════════
  //  LEADERBOARD
  // ══════════════════════════════════════════════════════════════════
  async function loadPlayerLeaderboard() {
    const tbody = document.querySelector('#hm-leaderboard-table tbody');
    const info  = document.getElementById('hm-leaderboard-info');
    if (!tbody) return;
    tbody.innerHTML = HmUtil.skeleton(10, 10);
    try {
      const res = await apiFetch('/leaderboard/players', {
        nation: state.nation, year: state.year,
        metric: state.metric, comp: state.comp, limit: 50,
      });
      if (info) info.textContent = `${res.meta.total} giocatori · ${state.comp} ${state.year}`;
      if (!res.data.length) {
        tbody.innerHTML = '<tr><td colspan="10" class="c-muted text-center">Nessun dato</td></tr>';
        return;
      }
      tbody.innerHTML = res.data.map((p, i) => {
        const url = `?page=player&id=${encodeURIComponent(p.public_id)}&nation=${p.nation}&year=${p.year}`;
        return `<tr>
          <td class="col-rank">${i + 1}</td>
          <td class="col-player"><a href="${url}" class="player-link">${HmUtil.escHtml(p.player_name)}</a></td>
          <td>${HmUtil.escHtml(p.team_name)}</td>
          <td>${HmUtil.escHtml(p.position)}</td>
          <td>${p.minutes}</td>
          ${statCell(p.raptor_total)}
          ${statCell(p.lebron_total)}
          ${statCell(p.net_rtg)}
          <td>${p.ts_pct !== null ? p.ts_pct.toFixed(1) + '%' : '—'}</td>
          <td>${pctBar(p.pct_rank)}</td>
        </tr>`;
      }).join('');
    } catch(e) {
      console.error('[Court Analytics] Leaderboard error:', e);
      tbody.innerHTML = `<tr><td colspan="10" class="hm-error">⚠️ ${HmUtil.escHtml(e.message)}</td></tr>`;
    }
  }

  async function loadTeamLeaderboard() {
    const tbody = document.querySelector('#hm-team-leaderboard-table tbody');
    if (!tbody) return;
    tbody.innerHTML = HmUtil.skeleton(8, 7);
    try {
      const res = await apiFetch('/leaderboard/teams', {
        nation: state.nation, year: state.year,
        metric: state.teamMetric ?? 'NetRtg', comp: state.comp,
      });
      if (!res.data.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="c-muted text-center">Nessun dato</td></tr>';
        return;
      }
      tbody.innerHTML = res.data.map((t, i) => {
        const url = `?page=team&id=${encodeURIComponent(t.public_id)}&nation=${t.nation}&year=${t.year}`;
        return `<tr>
          <td>${i + 1}</td>
          <td><a href="${url}" class="player-link">${HmUtil.escHtml(t.team_name)}</a></td>
          ${statCell(t.net_rtg)} ${statCell(t.o_rtg)} ${statCell(t.d_rtg)}
          <td>${t.pace !== null ? t.pace.toFixed(1) : '—'}</td>
          <td>${t.efg_pct !== null ? t.efg_pct.toFixed(1) + '%' : '—'}</td>
        </tr>`;
      }).join('');
    } catch(e) {
      console.error('[Court Analytics] Team LB error:', e);
      tbody.innerHTML = `<tr><td colspan="7" class="hm-error">⚠️ ${HmUtil.escHtml(e.message)}</td></tr>`;
    }
  }

  // ══════════════════════════════════════════════════════════════════
  //  SEARCH
  // ══════════════════════════════════════════════════════════════════
  document.addEventListener('hm:search-query', async function(e) {
    const $r = document.getElementById('hm-search-results');
    if (!$r) return;
    try {
      const data = await apiFetch('/search', { q: e.detail.query, nation: state.nation, year: state.year });
      HmSearch.render($r, data);
    } catch(err) {
      $r.innerHTML = `<div class="search-empty">⚠️ ${HmUtil.escHtml(err.message)}</div>`;
      $r.hidden = false;
    }
  });

  // ══════════════════════════════════════════════════════════════════
  //  FILTRI
  // ══════════════════════════════════════════════════════════════════
  document.addEventListener('hm:filter-change', function(e) {
    const { attr, value } = e.detail;
    if      (attr === 'nation')      state.nation     = value;
    else if (attr === 'comp')        state.comp       = value;
    else if (attr === 'metric')      state.metric     = value;
    else if (attr === 'season')      state.year       = value;
    else if (attr === 'team-metric') state.teamMetric = value;

    console.log('[Court Analytics] Stato →', JSON.stringify(state));
    refreshPage();
  });

  // ══════════════════════════════════════════════════════════════════
  //  REFRESH & INIT
  // ══════════════════════════════════════════════════════════════════
  function refreshPage() {
    if (document.getElementById('hm-kpi-row'))              loadDashboardKPI();
    if (document.getElementById('hm-nations-grid'))          loadNationsOverview();
    if (document.querySelector('#hm-leaderboard-table'))      loadPlayerLeaderboard();
    if (document.querySelector('#hm-team-leaderboard-table')) loadTeamLeaderboard();
  }

  document.addEventListener('DOMContentLoaded', function() {
    console.log('[Court Analytics] api.js pronto ✅ — stato:', state);
    if (typeof HM_CONFIG === 'undefined')
      console.warn('[Court Analytics] ⚠️ HM_CONFIG non trovato — plugin hoopmetrics-api attivo?');
    refreshPage();
  });

})();
