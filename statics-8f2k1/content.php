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
  <title>Panel · Contenido Trillizas</title>
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
    header .right a { color: var(--muted); text-decoration: none; }
    header .right a:hover { color: var(--gold); }
    .wrap { padding: 20px 24px 80px; max-width: 900px; margin: 0 auto; }
    .panel { background: var(--card); border: 1px solid var(--line); border-radius: 12px; padding: 20px 22px; margin-bottom: 22px; }
    .panel h2 { font-size: 15px; margin: 0 0 4px; color: var(--gold); font-weight: 600; }
    .panel .hint { font-size: 12px; color: var(--muted); margin: 0 0 16px; }
    label { display: block; font-size: 12px; color: var(--muted); margin: 14px 0 5px; }
    label:first-of-type { margin-top: 0; }
    input[type=text], input[type=url], input[type=number] {
      width: 100%; background: var(--bg); color: var(--text); border: 1px solid var(--line);
      border-radius: 8px; padding: 9px 11px; font-size: 14px; }
    input:focus { outline: none; border-color: var(--accent); }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .row3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
    .row-check { display: flex; align-items: center; gap: 8px; margin-top: 14px; }
    .row-check input { width: auto; }
    .row-check label { margin: 0; }
    button.save {
      margin-top: 18px; background: linear-gradient(135deg,#ffd76b,#ff9f43);
      color: #2b1a00; border: 0; font-weight: 700; padding: 10px 18px;
      border-radius: 8px; font-size: 13px; cursor: pointer; }
    button.save:hover { filter: brightness(1.05); }
    .status { font-size: 13px; margin-left: 12px; }
    .status.ok { color: #48d17a; }
    .status.err { color: #ff6b9d; }
    .ep-card { border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin-bottom: 14px; background: rgba(0,0,0,0.12); }
    .ep-card .ep-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .ep-card .ep-head strong { font-size: 14px; }
    .ep-actions { display: flex; gap: 8px; margin-top: 14px; }
    button.small {
      background: transparent; border: 1px solid var(--line); color: var(--muted);
      border-radius: 8px; padding: 7px 14px; font-size: 12px; cursor: pointer; }
    button.small:hover { border-color: var(--accent); color: var(--text); }
    button.delete:hover { border-color: #ff6b9d; color: #ff6b9d; }
    .thumb-preview { width: 90px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid var(--line); margin-top: 8px; background: #000; }
    .muted { color: var(--muted); font-size: 12px; }
    .new-episode { border: 1px dashed var(--line); }
  </style>
</head>
<body>
  <header>
    <h1>Contenido · Las Trillizas de Oro y El Libro Mágico</h1>
    <div class="right">
      <a href="index.php">← Volver a Analíticas</a>
      <span class="muted"><?= $panelUser ?></span>
      <a href="logout.php">Salir</a>
    </div>
  </header>

  <div class="wrap">
    <p class="muted" style="margin-top:18px;">
      Acá editás los textos y links principales de la landing, y la lista de
      episodios de la serie. Los cambios se guardan al toque y se ven en la
      web real sin tener que tocar código ni subir nada por FileZilla.
    </p>

    <!-- ============ TEXTOS Y LINKS ============ -->
    <div class="panel">
      <h2>Hero (portada)</h2>
      <p class="hint">El texto y los botones grandes que se ven al entrar a la web.</p>

      <label>Frase corta (arriba del título)</label>
      <input type="text" id="hero_claim" />

      <label>Título — línea 1</label>
      <input type="text" id="hero_title_line1" />
      <label>Título — línea 2</label>
      <input type="text" id="hero_title_line2" />

      <div class="row2">
        <div>
          <label>Botón "Ver la serie" → link</label>
          <input type="url" id="cta_serie_link" />
        </div>
        <div>
          <label>Botón "Escuchar el álbum" → link</label>
          <input type="url" id="cta_album_link" />
        </div>
      </div>
    </div>

    <div class="panel">
      <h2>Álbum</h2>
      <div class="row2">
        <div>
          <label>Nombre — línea 1</label>
          <input type="text" id="album_name_line1" />
        </div>
        <div>
          <label>Nombre — línea 2</label>
          <input type="text" id="album_name_line2" />
        </div>
      </div>
      <label>Volumen</label>
      <input type="text" id="album_volume" />
    </div>

    <div class="panel">
      <h2>Redes y canal</h2>
      <p class="hint">Se usan en varios botones de la web a la vez (footer, sección "Nuevos episodios", etc.)</p>
      <label>Canal de YouTube</label>
      <input type="url" id="youtube_channel_link" />
      <label>Spotify</label>
      <input type="url" id="spotify_link" />
      <label>Instagram</label>
      <input type="url" id="instagram_link" />
      <label>Babidibu Records (sitio)</label>
      <input type="url" id="babidibu_records_link" />
    </div>

    <div class="panel">
      <button class="save" id="saveContentBtn">Guardar textos y links</button>
      <span class="status" id="contentStatus"></span>
    </div>

    <!-- ============ EPISODIOS ============ -->
    <div class="panel">
      <h2>Episodios</h2>
      <p class="hint">
        Se muestran en la web en el orden de "Orden" (de menor a mayor). Si
        no tenés la miniatura todavía, dejá ese campo vacío — se muestra un
        ícono de play en su lugar.
      </p>
      <div id="episodesList"></div>

      <div class="ep-card new-episode">
        <strong>Agregar episodio nuevo</strong>
        <div class="row3">
          <div>
            <label>Temporada</label>
            <input type="number" id="new_season" value="1" min="1" />
          </div>
          <div>
            <label>N° de episodio</label>
            <input type="number" id="new_episode_number" min="1" />
          </div>
          <div>
            <label>Orden en la grilla</label>
            <input type="number" id="new_sort_order" min="0" />
          </div>
        </div>
        <label>Título</label>
        <input type="text" id="new_title" maxlength="255" />
        <label>Miniatura — opcional (si no subís nada, se muestra un ícono de play)</label>
        <div class="thumb-uploader">
          <img class="thumb-preview" id="new_thumbnail_preview" style="display:none" />
          <input type="hidden" id="new_thumbnail" value="" />
          <input type="file" accept="image/jpeg,image/png,image/webp" id="new_thumbnail_file" />
          <span class="status" id="new_thumbnail_status"></span>
        </div>
        <label>Link de YouTube</label>
        <input type="url" id="new_youtube_url" maxlength="500" placeholder="https://..." />
        <div class="ep-actions">
          <button class="small" id="addEpisodeBtn">+ Agregar episodio</button>
          <span class="status" id="addEpisodeStatus"></span>
        </div>
      </div>
    </div>
  </div>

  <script>
    const CONTENT_KEYS = [
      'hero_claim', 'hero_title_line1', 'hero_title_line2',
      'cta_serie_link', 'cta_album_link', 'youtube_channel_link',
      'spotify_link', 'instagram_link', 'babidibu_records_link',
      'album_name_line1', 'album_name_line2', 'album_volume',
    ];

    let episodesCache = [];

    // Sube una imagen elegida en un <input type="file"> a imagenes/,
    // y guarda la ruta resultante en el input escondido correspondiente
    // + muestra la vista previa. Así nadie tiene que saber usar
    // FileZilla ni escribir una ruta de archivo a mano.
    async function uploadImage(fileInput, hiddenInput, previewImg, statusEl) {
      const file = fileInput.files[0];
      if (!file) return;

      statusEl.textContent = 'Subiendo imagen...';
      statusEl.className = 'status';

      const formData = new FormData();
      formData.append('image', file);

      try {
        const res = await fetch('upload-image.php', { method: 'POST', body: formData });
        const d = await res.json();
        if (!d.ok) throw new Error(d.error || 'No se pudo subir la imagen.');
        hiddenInput.value = d.path;
        previewImg.src = '../' + d.path;
        previewImg.style.display = 'block';
        statusEl.textContent = '✓ Imagen subida';
        statusEl.className = 'status ok';
      } catch (e) {
        statusEl.textContent = e.message || 'No se pudo subir la imagen.';
        statusEl.className = 'status err';
      }
    }

    // Límite de caracteres en todos los campos de texto/link (mismo
    // tope que valida el servidor) — así el navegador ya avisa antes
    // de intentar guardar, en vez de que aparezca un error recién al
    // apretar el botón.
    CONTENT_KEYS.forEach((key) => {
      const el = document.getElementById(key);
      if (el) el.maxLength = 500;
    });

    async function loadContent() {
      const res = await fetch('../backend/content.php');
      const d = await res.json();
      CONTENT_KEYS.forEach((key) => {
        const el = document.getElementById(key);
        if (el && d.content[key] !== undefined) el.value = d.content[key];
      });
    }

    // El endpoint público no manda el id (no lo necesita la landing),
    // así que para editar/borrar acá pedimos la lista completa aparte,
    // con id incluido, vía el mismo content-save.php (acción de lectura).
    async function loadEpisodesWithIds() {
      const res = await fetch('content-save.php?action=episodes_list');
      const d = await res.json();
      episodesCache = d.episodes || [];
      renderEpisodes();
    }

    function renderEpisodes() {
      const wrap = document.getElementById('episodesList');
      wrap.innerHTML = '';
      episodesCache.forEach((ep) => {
        const card = document.createElement('div');
        card.className = 'ep-card';
        card.innerHTML = `
          <div class="ep-head"><strong>Episodio ${ep.episode_number} — ${ep.title}</strong></div>
          <div class="row3">
            <div>
              <label>Temporada</label>
              <input type="number" min="1" value="${ep.season}" data-field="season" />
            </div>
            <div>
              <label>N° de episodio</label>
              <input type="number" min="1" value="${ep.episode_number}" data-field="episode_number" />
            </div>
            <div>
              <label>Orden en la grilla</label>
              <input type="number" min="0" value="${ep.sort_order}" data-field="sort_order" />
            </div>
          </div>
          <label>Título</label>
          <input type="text" maxlength="255" value="${escapeAttr(ep.title)}" data-field="title" />
          <label>Miniatura — opcional (si no hay ninguna, se muestra un ícono de play)</label>
          <div class="thumb-uploader">
            <img class="thumb-preview" data-role="thumb-preview" style="display:${ep.thumbnail ? 'block' : 'none'}" src="${ep.thumbnail ? '../' + escapeAttr(ep.thumbnail) : ''}" />
            <input type="hidden" value="${escapeAttr(ep.thumbnail || '')}" data-field="thumbnail" />
            <input type="file" accept="image/jpeg,image/png,image/webp" data-role="thumb-file" />
            <span class="status" data-role="thumb-status"></span>
          </div>
          <label>Link de YouTube</label>
          <input type="url" maxlength="500" value="${escapeAttr(ep.youtube_url)}" data-field="youtube_url" />
          <div class="row-check">
            <input type="checkbox" data-field="is_visible" ${ep.is_visible ? 'checked' : ''} />
            <label>Visible en la web</label>
          </div>
          <div class="ep-actions">
            <button class="small save-ep">Guardar cambios</button>
            <button class="small delete delete-ep">Borrar episodio</button>
            <span class="status ep-status"></span>
          </div>
        `;
        card.querySelector('.save-ep').addEventListener('click', () => saveEpisode(ep.id, card));
        card.querySelector('.delete-ep').addEventListener('click', () => deleteEpisode(ep.id, ep.title));
        card.querySelector('[data-role="thumb-file"]').addEventListener('change', (e) => {
          uploadImage(
            e.target,
            card.querySelector('[data-field="thumbnail"]'),
            card.querySelector('[data-role="thumb-preview"]'),
            card.querySelector('[data-role="thumb-status"]')
          );
        });
        wrap.appendChild(card);
      });
    }

    function escapeAttr(s) {
      return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    function readEpCard(card) {
      const get = (field) => card.querySelector(`[data-field="${field}"]`);
      return {
        season: Number(get('season').value),
        episode_number: Number(get('episode_number').value),
        sort_order: Number(get('sort_order').value),
        title: get('title').value.trim(),
        thumbnail: get('thumbnail').value.trim(),
        youtube_url: get('youtube_url').value.trim(),
        is_visible: get('is_visible').checked,
      };
    }

    async function saveEpisode(id, card) {
      const statusEl = card.querySelector('.ep-status');
      statusEl.textContent = 'Guardando...';
      statusEl.className = 'status ep-status';
      const payload = { action: 'episode_save', id, ...readEpCard(card) };
      try {
        const res = await fetch('content-save.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const d = await res.json();
        if (!d.ok) throw new Error(d.error || 'No se pudo guardar.');
        statusEl.textContent = '✓ Guardado';
        statusEl.className = 'status ep-status ok';
        loadEpisodesWithIds();
      } catch (e) {
        statusEl.textContent = e.message || 'No se pudo guardar.';
        statusEl.className = 'status ep-status err';
      }
    }

    async function deleteEpisode(id, title) {
      if (!confirm(`¿Borrar el episodio "${title}"? No se puede deshacer.`)) return;
      await fetch('content-save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'episode_delete', id }),
      });
      loadEpisodesWithIds();
    }

    document.getElementById('new_thumbnail_file').addEventListener('change', () => {
      uploadImage(
        document.getElementById('new_thumbnail_file'),
        document.getElementById('new_thumbnail'),
        document.getElementById('new_thumbnail_preview'),
        document.getElementById('new_thumbnail_status')
      );
    });

    document.getElementById('addEpisodeBtn').addEventListener('click', async () => {
      const statusEl = document.getElementById('addEpisodeStatus');
      const payload = {
        action: 'episode_save',
        season: Number(document.getElementById('new_season').value || 1),
        episode_number: Number(document.getElementById('new_episode_number').value || 0),
        sort_order: Number(document.getElementById('new_sort_order').value || 0),
        title: document.getElementById('new_title').value.trim(),
        thumbnail: document.getElementById('new_thumbnail').value.trim(),
        youtube_url: document.getElementById('new_youtube_url').value.trim(),
        is_visible: true,
      };
      if (!payload.title || !payload.youtube_url) {
        statusEl.textContent = 'Falta el título o el link de YouTube';
        statusEl.className = 'status err';
        return;
      }
      statusEl.textContent = 'Guardando...';
      statusEl.className = 'status';
      try {
        const res = await fetch('content-save.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const d = await res.json();
        if (!d.ok) throw new Error(d.error || 'No se pudo guardar.');
        statusEl.textContent = '';
        ['new_season', 'new_episode_number', 'new_sort_order', 'new_title', 'new_thumbnail', 'new_youtube_url']
          .forEach((id) => { document.getElementById(id).value = id === 'new_season' ? '1' : ''; });
        document.getElementById('new_thumbnail_preview').style.display = 'none';
        document.getElementById('new_thumbnail_status').textContent = '';
        document.getElementById('new_thumbnail_file').value = '';
        loadEpisodesWithIds();
      } catch (e) {
        statusEl.textContent = e.message || 'No se pudo guardar.';
        statusEl.className = 'status err';
      }
    });

    document.getElementById('saveContentBtn').addEventListener('click', async () => {
      const statusEl = document.getElementById('contentStatus');
      const fields = {};
      CONTENT_KEYS.forEach((key) => {
        fields[key] = document.getElementById(key).value.trim();
      });
      statusEl.textContent = 'Guardando...';
      statusEl.className = 'status';
      try {
        const res = await fetch('content-save.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'save_content', fields }),
        });
        const d = await res.json();
        if (!d.ok) throw new Error(d.error || 'No se pudo guardar.');
        statusEl.textContent = '✓ Guardado — ya está en vivo';
        statusEl.className = 'status ok';
      } catch (e) {
        statusEl.textContent = e.message || 'No se pudo guardar.';
        statusEl.className = 'status err';
      }
    });

    (async function init() {
      await loadContent();
      loadEpisodesWithIds();
    })();
  </script>
</body>
</html>
