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
    .controls input[type=date], .controls button {
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
    <h1>📊 Analíticas · Las Trillizas de Oro y El Libro Mágico</h1>
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
      <button class="exp" id="exportBtn">Exportar CSV</button>
      <a class="exp" href="export-subscribers.php" style="text-decoration:none; display:inline-flex; align-items:center;">Suscriptores CSV</a>
    </div>

    <div class="cards">
      <div class="card"><div class="label">Visitas (page views)</div><div class="value" id="kpiViews">–</div></div>
      <div class="card"><div class="label">Visitantes únicos</div><div class="value" id="kpiVisitors">–</div></div>
      <div class="card"><div class="label">Clics en botones</div><div class="value" id="kpiClicks">–</div></div>
      <div class="card"><div class="label">Tasa de clic</div><div class="value" id="kpiCTR">–</div></div>
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
        <h2>Embudo de conversión</h2>
        <div id="funnel"></div>
      </div>
      <div class="panel">
        <h2>Placement</h2>
        <table id="tblPlacement"><thead><tr><th>Placement</th><th class="n">Sesiones</th></tr></thead><tbody></tbody></table>
      </div>
    </div>

    <div class="grid2">
      <div class="panel">
        <h2>Países</h2>
        <table id="tblCountries"><thead><tr><th>País</th><th class="n">Sesiones</th></tr></thead><tbody></tbody></table>
      </div>
      <div class="panel">
        <h2>Ciudades</h2>
        <table id="tblCities"><thead><tr><th>Ciudad</th><th class="n">Sesiones</th></tr></thead><tbody></tbody></table>
      </div>
    </div>

    <div class="panel">
      <h2>Por anuncio (ad_id)</h2>
      <table id="tblAds">
        <thead><tr><th>Anuncio</th><th>Campaña</th><th class="n">Visitas</th><th class="n">Clics</th></tr></thead>
        <tbody></tbody>
      </table>
      <p class="muted">Se agrupa por <code>ad_id</code>; el nombre es la última <code>ad.name</code> vista.</p>
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
      const res = await fetch(`data.php?from=${from}&to=${to}`, { credentials: 'same-origin' });
      if (res.status === 401) { location.href = 'login.php'; return; }
      const d = await res.json();

      // KPIs
      $('kpiViews').textContent = fmt(d.totals.page_views);
      $('kpiVisitors').textContent = fmt(d.totals.unique_visitors);
      $('kpiClicks').textContent = fmt(d.totals.clicks);
      $('activeNow').textContent = fmt(d.totals.active_now);
      const ctr = d.totals.unique_visitors ? (d.totals.clicks / d.totals.unique_visitors * 100) : 0;
      $('kpiCTR').textContent = ctr.toFixed(1) + '%';

      // Charts
      drawChart('chartTimeline', 'line',
        d.timeline.map(r => r.d), d.timeline.map(r => +r.views));
      drawChart('chartButtons', 'bar',
        d.clicks_by_button.map(r => r.button), d.clicks_by_button.map(r => +r.n));
      drawChart('chartSources', 'doughnut',
        d.sources.map(r => r.src), d.sources.map(r => +r.n));
      drawChart('chartDevices', 'doughnut',
        d.devices.map(r => r.device), d.devices.map(r => +r.n));

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
        `<td>${r.ad_name ?? r.ad_id}</td><td>${r.campaign_name ?? ''}</td><td class="n">${fmt(+r.visitas)}</td><td class="n">${fmt(+r.clics)}</td>`);
    }

    // Eventos UI
    document.querySelectorAll('.preset').forEach(b =>
      b.addEventListener('click', () => { setPreset(+b.dataset.days); load(); }));
    $('from').addEventListener('change', load);
    $('to').addEventListener('change', load);
    $('exportBtn').addEventListener('click', () => {
      location.href = `export.php?from=${$('from').value}&to=${$('to').value}`;
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
