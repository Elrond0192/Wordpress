/**
 * HoopMetrics – main.js v1.0.4
 * Fix: DOMContentLoaded garantito, theme toggle sicuro, filtri nazione.
 */
(function () {
  'use strict';

  /* ─── THEME ─────────────────────────────────────────────────────── */
  const THEME_KEY = 'hm_theme';
  const $html     = document.documentElement;

  function getTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'dark'; } catch(e) { return 'dark'; }
  }
  function applyTheme(theme) {
    $html.setAttribute('data-theme', theme);
    try { localStorage.setItem(THEME_KEY, theme); } catch(e) {}
    const icon = document.getElementById('hm-theme-icon');
    if (icon) icon.textContent = theme === 'dark' ? '🌙' : '☀️';
  }

  // Applica subito (prima del render per evitare flash)
  applyTheme(getTheme());

  // Aspetta il DOM per attaccare gli event listener
  document.addEventListener('DOMContentLoaded', function () {

    console.log('[HoopMetrics] main.js caricato ✅');

    /* ── Toggle dark/light ──────────────────────────────────────── */
    const $toggle = document.getElementById('hm-theme-toggle');
    if ($toggle) {
      $toggle.addEventListener('click', function () {
        const current = $html.getAttribute('data-theme') || 'dark';
        applyTheme(current === 'dark' ? 'light' : 'dark');
        console.log('[HoopMetrics] Tema cambiato →', $html.getAttribute('data-theme'));
      });
    } else {
      console.warn('[HoopMetrics] #hm-theme-toggle non trovato nel DOM');
    }

    /* ── Navbar scroll shadow ───────────────────────────────────── */
    const $nav = document.getElementById('hm-navbar');
    if ($nav) {
      window.addEventListener('scroll', function () {
        $nav.style.boxShadow = window.scrollY > 10 ? '0 2px 24px rgba(0,0,0,.35)' : 'none';
      }, { passive: true });
    }

    /* ── Filter buttons: nazione, competizione, metrica ────────────
       Ogni bottone con data-hm-nation / data-hm-comp / data-hm-metric
       gestito con event delegation sul documento.
    ─────────────────────────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
      // Cerca il bottone più vicino con un attributo hm-filter
      const btn = e.target.closest('[data-hm-nation],[data-hm-comp],[data-hm-metric],[data-hm-team-metric],[data-hm-split]');
      if (!btn) return;

      // Trova quale attributo usa
      let attr = null;
      ['data-hm-nation','data-hm-comp','data-hm-metric','data-hm-team-metric','data-hm-split'].forEach(a => {
        if (btn.hasAttribute(a)) attr = a;
      });
      if (!attr) return;

      const value = btn.getAttribute(attr);

      // Aggiorna classe active SOLO nei bottoni dello stesso gruppo
      const group = btn.closest('.filter-bar-inner, .tab-row, [data-filter-group]');
      const scope = group || document;
      scope.querySelectorAll(`[${attr}]`).forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      console.log('[HoopMetrics] Filtro →', attr.replace('data-hm-',''), '=', value);

      // Dispatcha evento per api.js
      document.dispatchEvent(new CustomEvent('hm:filter-change', {
        bubbles: true,
        detail: { attr: attr.replace('data-hm-',''), value: value }
      }));
    });

    /* ── Tab rows ───────────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.tab-btn');
      if (!btn) return;
      const row = btn.closest('.tab-row');
      if (!row) return;
      row.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });

    /* ── Season select ──────────────────────────────────────────── */
    const $sel = document.getElementById('hm-season-select');
    if ($sel) {
      if (window.HM_CONFIG && Array.isArray(HM_CONFIG.seasons)) {
        $sel.innerHTML = HM_CONFIG.seasons.map(s =>
          `<option value="${s}"${s==='2024'?' selected':''}>${s}</option>`
        ).join('');
      }
      $sel.addEventListener('change', function () {
        document.dispatchEvent(new CustomEvent('hm:filter-change', {
          bubbles: true,
          detail: { attr: 'season', value: $sel.value }
        }));
      });
    }

    /* ── Sortable tables ────────────────────────────────────────── */
    document.addEventListener('click', function (e) {
      const th = e.target.closest('table.hm-table thead th');
      if (!th) return;
      const table = th.closest('table');
      const tbody = table.querySelector('tbody');
      if (!tbody) return;
      const ths    = Array.from(table.querySelectorAll('thead th'));
      const colIdx = ths.indexOf(th);
      const asc    = th.dataset.sortAsc !== 'true';
      ths.forEach(h => { h.classList.remove('sorted'); delete h.dataset.sortAsc; });
      th.classList.add('sorted');
      th.dataset.sortAsc = String(asc);
      const rows = Array.from(tbody.querySelectorAll('tr'));
      rows.sort((a, b) => {
        const av = parseFloat((a.cells[colIdx]?.textContent||'').replace(/[^0-9.\-]/g,'')) || 0;
        const bv = parseFloat((b.cells[colIdx]?.textContent||'').replace(/[^0-9.\-]/g,'')) || 0;
        return asc ? av - bv : bv - av;
      });
      rows.forEach(r => tbody.appendChild(r));
    });

    /* ── Search ─────────────────────────────────────────────────── */
    const $search  = document.getElementById('hm-global-search');
    const $results = document.getElementById('hm-search-results');
    let _t = null;
    if ($search && $results) {
      $search.addEventListener('input', function () {
        clearTimeout(_t);
        const q = $search.value.trim();
        if (q.length < 2) { $results.hidden = true; $results.innerHTML = ''; return; }
        _t = setTimeout(function () {
          document.dispatchEvent(new CustomEvent('hm:search-query', { detail: { query: q } }));
        }, 300);
      });
      document.addEventListener('click', function (e) {
        if (!$search.contains(e.target) && !$results.contains(e.target)) {
          $results.hidden = true;
        }
      });
      $search.addEventListener('keydown', function (e) {
        const items = $results.querySelectorAll('.search-item');
        if (!items.length) return;
        const focused = $results.querySelector('.search-item:focus');
        if      (e.key === 'ArrowDown')  { e.preventDefault(); (focused?.nextElementSibling || items[0]).focus(); }
        else if (e.key === 'ArrowUp')    { e.preventDefault(); (focused?.previousElementSibling || items[items.length-1]).focus(); }
        else if (e.key === 'Escape')     { $results.hidden = true; $search.blur(); }
      });
    }

  }); // end DOMContentLoaded

  /* ── HmUtil (accessibile globalmente, subito) ──────────────────── */
  window.HmUtil = {
    escHtml: s => String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'),
    fmtStat: (v,d=1) => { const n=parseFloat(v); return isNaN(n)?'—':(n>0?'+':'')+n.toFixed(d); },
    fmtPct:  (v,d=1) => { const n=parseFloat(v); return isNaN(n)?'—':n.toFixed(d)+'%'; },
    fmtNum:  (v,d=0) => { const n=parseFloat(v); return isNaN(n)?'—':n.toFixed(d); },
    hpClass: p => p>=75?'hp-e':p>=50?'hp-g':p>=25?'hp-m':p>=10?'hp-b':'hp-p',
    valClass:v => { const n=parseFloat(v); return isNaN(n)?'c-muted':n>0?'c-pos':n<0?'c-neg':'c-muted'; },
    skeleton:(rows=5,cols=8) => '<tbody>'+Array(rows).fill('<tr>'+Array(cols).fill('<td><div class="hm-skel" style="height:12px;border-radius:4px;margin:2px 0"></div></td>').join('')+'</tr>').join('')+'</tbody>',
    spinner: () => '<div class="hm-spinner"><div class="hm-spinner-ring"></div></div>',
    toast(msg, type='info') {
      const el = document.createElement('div');
      el.className = `hm-toast hm-toast-${type}`;
      el.textContent = msg;
      document.body.appendChild(el);
      setTimeout(() => el.classList.add('hm-toast-show'), 50);
      setTimeout(() => { el.classList.remove('hm-toast-show'); setTimeout(() => el.remove(), 400); }, 3200);
    }
  };

  window.HmSearch = {
    render($results, results) {
      if (!$results) return;
      if (!results?.length) {
        $results.innerHTML = '<div class="search-empty">Nessun risultato</div>';
        $results.hidden = false; return;
      }
      $results.innerHTML = results.map(r => {
        const url = r.type === 'player'
          ? `/giocatore/?id=${encodeURIComponent(r.public_id)}&nation=${r.nation}&season=${r.season||'2024'}`
          : `/squadra/?team=${encodeURIComponent(r.public_id)}&nation=${r.nation}&season=${r.season||'2024'}`;
        return `<a href="${url}" class="search-item" tabindex="0">
          <span class="search-emoji">${r.type==='player'?'🏃':'🏟️'}</span>
          <span class="search-name">${HmUtil.escHtml(r.name)}</span>
          <span class="search-meta">${HmUtil.escHtml(r.team||'')} · ${HmUtil.escHtml(r.nation)}</span>
        </a>`;
      }).join('');
      $results.hidden = false;
    }
  };

})();
