<?php
require_once __DIR__ . '/auth.php';
require_login();
$panelUser = htmlspecialchars($_SESSION['panel_user'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Panel · Analíticas Trillizas</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    :root {
      --bg: #150c26; --card: #221338; --line: #3a2957;
      --text: #f4ecd8; --muted: #b3a4cc; --gold: #ffd76b; --accent: #a678ff;
    }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text);
      font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; }
    header { display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
      justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--line); }
    header h1 { font-size: 18px; margin: 0; }
    header .right { display: flex; align-items: center; gap: 14px; font-size: 13px; }
    .live { display: inline-flex; align-items: center; gap: 6px; color: var(--muted); }
    .dot { width: 8px; height: 8px; border-radius: 50%; background: #48d17a; box-shadow: 0 0 8px #48d17a; }
    a.logout { color: var(--muted); text-decoration: none; }
    .wrap { padding: 20px 24px 60px; max-width: 1200px; margin: 0 auto; }
    .controls { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 22px; }
    .controls input[type=date], .controls button, .controls select {
      background: var(--card); color: var(--text); border: 1px solid var(--line);
      border-radius: 8px; padding: 8px 12px; font-size: 13px; }
    .controls button { cursor: pointer; }
    .controls button.preset:hover { border-color: var(--accent); }
    .controls .exp { margin-left: auto; background: linear-gradient(135deg,#ffd76b,#ff9f43);
      color: #2b1a00; border: 0; font-weight: 600; }
    .cards { display: grid; grid-template-columns: repeat(auto-fit,minmax(160px,1fr)); gap: 14px; margin-bottom: 24px; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; }
    .card .label { font-size: 12px; color: var(--muted); }
    .card .value { font-size: 28px; font-weight: 700; margin-top: 4px; }
    .card .diff { font-size: 12px; margin-top: 6px; color: var(--muted); }
    .card .diff.up { color: #48d17a; }
    .card .diff.down { color: #ff6b9d; }
    .grid2 { display: grid; grid-template-columns: repeat(auto-fit,minmax(320px,1fr)); gap: 18px; margin-bottom: 18px; }
    .panel { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 16px 18px; }
    .panel h2 { font-size: 14px; margin: 0 0 14px; color: var(--muted); font-weight: 600; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 7px 8px; border-bottom: 1px solid var(--line); }
    th { color: var(--muted); font-weight: 500; }
    td.n, th.n { text-align: right; }
    .funnel-step { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
    .funnel-bar { height: 8px; border-radius: 999px; background: linear-gradient(90deg,var(--accent),var(--gold)); margin-top: 4px; }
    canvas { max-height: 260px; }
    .muted { color: var(--muted); font-size: 12px; }
  </style>
</head>
<body>
  <header>
    <h1><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px; margin-right:6px;"><rect x="3" y="12" width="4" height="8"/><rect x="10" y="7" width="4" height="13"/><rect x="17" y="3" width="4" height="17"/></svg>Analíticas · Las Trillizas de Oro y El Libro Mágico</h1>
    <div class="right">
      <span class="live"><span class="dot"></span><span id="activeNow">0</span> activos ahora</span>
      <span class="muted"><?= $panelUser ?></span>
      <a class="logout" href="logout.php">Salir</a>
    </div>
  </header>

  <div class="wrap">
    <div class="controls">
      <input type="date" id="from" />
      <span class="muted">→</span>
      <input type="date" id="to" />
      <button class="preset" data-days="0">Hoy</button>
      <button class="preset" data-days="6">7 días</button>
      <button class="preset" data-days="29">30 días</button>
      <select id="adFilter"><option value="">Todos los anuncios</option></select>
      <select id="sourceFilter"><option value="">Todas las fuentes</option></select>
      <button class="exp" id="exportBtn">Visitas y clics CSV</button>
      <button class="exp" id="exportSubsBtn">Suscriptores CSV</button>
    </div>

    <p class="muted" id="comparisonNote" style="margin: -10px 0 14px;"></p>
    <div class="cards">
      <div class="card"><div class="label">Visitas (page views)</div><div class="value" id="kpiViews">–</div><div class="diff" id="diffViews"></div></div>
      <div class="card"><div class="label">Visitantes únicos</div><div class="value" id="kpiVisitors">–</div><div class="diff" id="diffVisitors"></div></div>
      <div class="card"><div class="label">Clics en botones</div><div class="value" id="kpiClicks">–</div><div class="diff" id="diffClicks"></div></div>
      <div class="card"><div class="label">Tasa de clic</div><div class="value" id="kpiCTR">–</div></div>
      <div class="card"><div class="label">Suscripciones</div><div class="value" id="kpiSubs">–</div><div class="diff" id="diffSubs"></div></div>
    </div>

    <div class="grid2">
      <div class="panel"><h2>Visitas por día</h2><canvas id="chartTimeline"></canvas></div>
      <div class="panel"><h2>Clics por botón</h2><canvas id="chartButtons"></canvas></div>
    </div>

    <div class="grid2">
      <div class="panel"><h2>Fuentes de tráfico</h2><canvas id="chartSources"></canvas></div>
      <div class="panel"><h2>Dispositivos</h2><canvas id="chartDevices"></canvas></div>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>Nuevos vs. recurrentes</h2>
        <canvas id="chartNewReturning"></canvas>
        <p class="muted">"Nuevo": es la primera vez que esa persona entra a la web. "Recurrente": ya había entrado antes (por ejemplo, volvió por un anuncio de remarketing).</p>
      </div>
      <div class="panel">
        <h2>Embudo de conversión</h2>
        <div id="funnel"></div>
      </div>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>¿Cuánto tiempo se quedan y cuánto ven?</h2>
        <div class="cards" style="grid-template-columns: repeat(2, 1fr); margin-bottom: 0;">
          <div class="card"><div class="label">Tiempo promedio en la página</div><div class="value" id="kpiTimeOnPage">–</div></div>
          <div class="card"><div class="label">Cuánto bajan (scroll)</div><div class="value" id="kpiScrollPct">–</div></div>
        </div>
        <p class="muted">Se mide desde que entran hasta que se van de la página (cierran, cambian de pestaña o navegan a otro lado). "Cuánto bajan" es hasta dónde llegaron scrolleando, en promedio — 100% significa que vieron toda la página.</p>
      </div>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>Placement</h2>
        <table id="tblPlacement"><thead><tr><th>Placement</th><th class="n">Sesiones</th></tr></thead><tbody></tbody></table>
        <p class="muted">"Placement" es en qué parte de Instagram o Facebook apareció el anuncio que trajo a esa persona (el feed, las Historias, los Reels, etc.). Solo se completa si viene de un anuncio de Meta con ese dato configurado — el resto del tráfico (QR, redes, directo) aparece como "(sin dato)".</p>
      </div>
      <div class="panel">
        <h2>Países</h2>
        <table id="tblCountries"><thead><tr><th>País</th><th class="n">Sesiones</th></tr></thead><tbody></tbody></table>
        <p class="muted">"Sesiones" = cantidad de visitas. Si la misma persona entra dos veces en momentos distintos, cuenta como dos sesiones (no es necesariamente gente distinta).</p>
      </div>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>Ciudades</h2>
        <table id="tblCities"><thead><tr><th>Ciudad</th><th class="n">Sesiones</th></tr></thead><tbody></tbody></table>
      </div>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>¿Miran el video y escuchan la canción?</h2>
        <table id="tblButtonInterest">
          <thead><tr><th>Botón</th><th class="n">% que lo tocó</th><th class="n">Cuánto tardaron en tocarlo</th></tr></thead>
          <tbody></tbody>
        </table>
        <p class="muted">"% que lo tocó" = de cada 100 personas que entran a la web, cuántas apretaron ese botón. "Cuánto tardaron" = el tiempo promedio desde que entraron hasta que lo apretaron — más tiempo suele significar que lo pensaron, no que fue un clic apurado sin querer.</p>
      </div>
      <div class="panel">
        <h2>¿El interés en la música se transforma en suscriptores?</h2>
        <table id="tblMusicEngagement">
          <thead><tr><th></th><th class="n">Personas que lo tocaron</th><th class="n">De esas, cuántas dejaron el mail</th></tr></thead>
          <tbody></tbody>
        </table>
        <p class="muted" id="bothClicksNote"></p>
      </div>
    </div>

    <div class="panel">
      <h2>Resultados por anuncio</h2>
      <table id="tblAds">
        <thead><tr><th>Anuncio</th><th>Campaña</th><th class="n">Visitas</th><th class="n">Clics</th><th class="n">Mails dejados</th></tr></thead>
        <tbody></tbody>
      </table>
      <p class="muted">Cuánta gente entró, clickeó algo y dejó su mail, separado por cada anuncio de Meta.</p>
    </div>
  </div>

  <script>
    const $ = (id) => document.getElementById(id);
    const charts = {};

    function fmt(n) { return (n ?? 0).toLocaleString('es-AR'); }

    function setPreset(days) {
      const to = new Date();
      const from = new Date();
      from.setDate(to.getDate() - days);
      $('from').value = from.toISOString().slice(0, 10);
      $('to').value = to.toISOString().slice(0, 10);
    }

    function rows(tblId, data, cols) {
      const tb = $(tblId).querySelector('tbody');
      tb.innerHTML = '';
      if (!data.length) { tb.innerHTML = '<tr><td colspan="9" class="muted">Sin datos</td></tr>'; return; }
      data.forEach((d) => {
        const tr = document.createElement('tr');
        tr.innerHTML = cols(d);
        tb.appendChild(tr);
      });
    }

    function drawChart(id, type, labels, values, opts) {
      if (charts[id]) charts[id].destroy();
      const palette = ['#a678ff','#ffd76b','#ff9f43','#48d17a','#ff6b9d','#4db8ff','#c9a0ff','#ffcf6b'];
      charts[id] = new Chart($(id), {
        type,
        data: {
          labels,
          datasets: [{
            data: values,
            backgroundColor: type === 'line' ? 'rgba(166,120,255,.2)' : palette,
            borderColor: type === 'line' ? '#a678ff' : palette,
            borderWidth: type === 'line' ? 2 : 0,
            fill: type === 'line',
            tension: .3,
          }],
        },
        options: Object.assign({
          responsive: true,
          plugins: { legend: { display: type !== 'bar' && type !== 'line', labels: { color: '#b3a4cc' } } },
          scales: (type === 'bar' || type === 'line') ? {
            x: { ticks: { color: '#b3a4cc' }, grid: { color: '#2c1e45' } },
            y: { ticks: { color: '#b3a4cc' }, grid: { color: '#2c1e45' }, beginAtZero: true },
          } : {},
        }, opts || {}),
      });
    }

    async function load() {
      const from = $('from').value, to = $('to').value;
      const adId = $('adFilter').value;
      const source = $('sourceFilter').value;
      const qs = new URLSearchParams({ from, to });
      if (adId) qs.set('ad_id', adId);
      if (source) qs.set('utm_source', source);
      const res = await fetch(`data.php?${qs}`, { credentials: 'same-origin' });
      if (res.status === 401) { location.href = 'login.php'; return; }
      const d = await res.json();

      // Selector de anuncio: se completa una sola vez con el listado
      // completo (no filtrado), para no perder la selección al recargar.
      const adSel = $('adFilter');
      if (adSel.dataset.loaded !== '1') {
        d.ads_list.forEach((a) => {
          const opt = document.createElement('option');
          opt.value = a.ad_id;
          opt.textContent = a.ad_name;
          adSel.appendChild(opt);
        });
        adSel.dataset.loaded = '1';
      }
      const srcSel = $('sourceFilter');
      if (srcSel.dataset.loaded !== '1') {
        d.sources_list.forEach((s) => {
          const opt = document.createElement('option');
          opt.value = s.src;
          opt.textContent = s.src;
          srcSel.appendChild(opt);
        });
        srcSel.dataset.loaded = '1';
      }

      // KPIs
      $('kpiViews').textContent = fmt(d.totals.page_views);
      $('kpiVisitors').textContent = fmt(d.totals.unique_visitors);
      $('kpiClicks').textContent = fmt(d.totals.clicks);
      $('kpiSubs').textContent = fmt(d.totals.subscriptions);
      $('activeNow').textContent = fmt(d.totals.active_now);
      const ctr = d.totals.unique_visitors ? (d.totals.clicks / d.totals.unique_visitors * 100) : 0;
      $('kpiCTR').textContent = ctr.toFixed(1) + '%';

      // Comparación con el período anterior (misma cantidad de días,
      // justo antes del rango elegido).
      const pr = d.comparison.prev_range;
      $('comparisonNote').textContent =
        `Comparado con el período anterior (${pr.from} a ${pr.to}):`;
      function setDiff(elId, actual, previo) {
        const el = $(elId);
        // previo=0 es un valor real (el período anterior tuvo cero), no
        // "sin datos" — hay que calcularlo aparte porque no se puede
        // sacar un % contra cero.
        if (previo === 0) {
          if (actual === 0) { el.textContent = 'sin cambios (0 en ambos períodos)'; el.className = 'diff'; return; }
          el.textContent = `antes 0, ahora ${fmt(actual)}`;
          el.className = 'diff up';
          return;
        }
        const pct = ((actual - previo) / previo) * 100;
        const sign = pct > 0 ? '+' : '';
        el.textContent = `${sign}${pct.toFixed(0)}% vs. anterior`;
        el.className = 'diff' + (pct > 0 ? ' up' : pct < 0 ? ' down' : '');
      }
      setDiff('diffViews', d.totals.page_views, d.comparison.prev_totals.page_views);
      setDiff('diffVisitors', d.totals.unique_visitors, d.comparison.prev_totals.unique_visitors);
      setDiff('diffClicks', d.totals.clicks, d.comparison.prev_totals.clicks);
      setDiff('diffSubs', d.totals.subscriptions, d.comparison.prev_totals.subscriptions);

      // Charts
      drawChart('chartTimeline', 'line',
        d.timeline.map(r => r.d), d.timeline.map(r => +r.views));
      drawChart('chartButtons', 'bar',
        d.clicks_by_button.map(r => r.button), d.clicks_by_button.map(r => +r.n));
      drawChart('chartSources', 'doughnut',
        d.sources.map(r => r.src), d.sources.map(r => +r.n));
      drawChart('chartDevices', 'doughnut',
        d.devices.map(r => r.device), d.devices.map(r => +r.n));
      drawChart('chartNewReturning', 'doughnut',
        d.new_vs_returning.map(r => r.tipo), d.new_vs_returning.map(r => +r.n));

      // Embudo
      const v = d.funnel.visits || 0, c = d.funnel.sessions_with_click || 0;
      const pct = v ? (c / v * 100) : 0;
      $('funnel').innerHTML =
        `<div class="funnel-step"><span>Visitantes</span><strong>${fmt(v)}</strong></div>
         <div class="funnel-bar" style="width:100%"></div>
         <div class="funnel-step" style="margin-top:14px"><span>Hicieron clic en un botón</span><strong>${fmt(c)} (${pct.toFixed(1)}%)</strong></div>
         <div class="funnel-bar" style="width:${Math.max(pct,2)}%"></div>`;

      // Tablas
      rows('tblPlacement', d.placements, r => `<td>${r.placement}</td><td class="n">${fmt(+r.n)}</td>`);
      rows('tblCountries', d.countries, r => `<td>${r.country}</td><td class="n">${fmt(+r.n)}</td>`);
      rows('tblCities', d.cities, r => `<td>${r.city}${r.cc ? ' · ' + r.cc : ''}</td><td class="n">${fmt(+r.n)}</td>`);
      rows('tblAds', d.ads, r =>
        `<td>${r.ad_name ?? r.ad_id}</td><td>${r.campaign_name ?? ''}</td><td class="n">${fmt(+r.visitas)}</td><td class="n">${fmt(+r.clics)}</td><td class="n">${fmt(+r.suscripciones)}</td>`);

      // Interés real en video/canción
      const buttonLabel = { videoclip: 'Mirá el videoclip', cancion_spotify: 'Escuchá la canción' };
      const fmtDwell = (ms) => {
        if (ms === null || ms === undefined) return '–';
        return ms < 1000 ? `${ms} ms` : `${(ms / 1000).toFixed(1)} s`;
      };
      rows('tblButtonInterest', d.button_interest, r =>
        `<td>${buttonLabel[r.button] ?? r.button}</td><td class="n">${r.pct_visitantes}%</td><td class="n">${fmtDwell(r.avg_dwell_ms)}</td>`);

      // Cruce interés musical <-> suscripción
      const me = d.music_engagement;
      const pctSubs = (subs, clicks) => clicks ? (subs / clicks * 100).toFixed(1) : '0.0';
      rows('tblMusicEngagement', [
        { label: 'Video', clics: me.video.clics, subs: me.video.suscripciones },
        { label: 'Canción', clics: me.cancion.clics, subs: me.cancion.suscripciones },
      ], r => `<td>${r.label}</td><td class="n">${fmt(r.clics)}</td><td class="n">${fmt(r.subs)} (${pctSubs(r.subs, r.clics)}%)</td>`);
      $('bothClicksNote').textContent =
        `${fmt(me.ambos_clics)} persona(s) clickearon el video Y la canción — el segmento más interesado.`;

      // Tiempo en la página y scroll
      const pe = d.page_engagement;
      $('kpiTimeOnPage').textContent = fmtDwell(pe.avg_time_ms);
      $('kpiScrollPct').textContent = pe.avg_scroll_pct !== null ? `${pe.avg_scroll_pct}%` : '–';
    }

    // Eventos UI
    document.querySelectorAll('.preset').forEach(b =>
      b.addEventListener('click', () => { setPreset(+b.dataset.days); load(); }));
    $('from').addEventListener('change', load);
    $('to').addEventListener('change', load);
    $('adFilter').addEventListener('change', load);
    $('sourceFilter').addEventListener('change', load);
    function exportQs() {
      const qs = new URLSearchParams({ from: $('from').value, to: $('to').value });
      if ($('adFilter').value) qs.set('ad_id', $('adFilter').value);
      if ($('sourceFilter').value) qs.set('utm_source', $('sourceFilter').value);
      return qs.toString();
    }
    $('exportBtn').addEventListener('click', () => {
      location.href = `export.php?${exportQs()}`;
    });
    $('exportSubsBtn').addEventListener('click', () => {
      location.href = `export-subscribers.php?${exportQs()}`;
    });

    // Inicio: últimos 7 días + auto-refresh de "activos ahora"
    setPreset(6);
    load();
    setInterval(async () => {
      const res = await fetch(`data.php?from=${$('from').value}&to=${$('to').value}`, { credentials: 'same-origin' });
      if (res.ok) { const d = await res.json(); $('activeNow').textContent = fmt(d.totals.active_now); }
    }, 30000);
  </script>
</body>
</html>
