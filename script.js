// ============================================================
// Enlaces editables desde Sanity Studio (https://trillizas-lasoro.sanity.studio)
// Dataset público de solo lectura: no necesita token en el navegador.
// Si el fetch falla (sin internet, Sanity caído), quedan los links fijos
// que ya están en el HTML como respaldo.
// ============================================================

(function () {
  var PROJECT_ID = 'bms71p7z';
  var DATASET = 'production';
  var query = encodeURIComponent('*[_id == "enlacesSingleton"][0]{videoclipUrl, cancionUrl}');
  var url = 'https://' + PROJECT_ID + '.api.sanity.io/v2021-10-21/data/query/' + DATASET + '?query=' + query;

  fetch(url)
    .then(function (res) { return res.json(); })
    .then(function (data) {
      var enlaces = data && data.result;
      if (!enlaces) return;

      if (enlaces.videoclipUrl) {
        document.querySelectorAll('#ctaVideoclip').forEach(function (el) {
          el.setAttribute('href', enlaces.videoclipUrl);
        });
      }
      if (enlaces.cancionUrl) {
        document.querySelectorAll('#ctaSpotify').forEach(function (el) {
          el.setAttribute('href', enlaces.cancionUrl);
        });
      }
    })
    .catch(function () {
      // Sin conexión a Sanity: quedan los links fijos del HTML.
    });
})();

// ============================================================
// Slogan — develado palabra por palabra (respeta la tipografía/gradiente,
// solo divide el texto existente en spans para animarlos por separado)
// ============================================================

function spawnWordBurst(wordEl, wrapEl) {
  const wordRect = wordEl.getBoundingClientRect();
  const wrapRect = wrapEl.getBoundingClientRect();

  const cx = wordRect.left - wrapRect.left + wordRect.width / 2;
  const cy = wordRect.top - wrapRect.top + wordRect.height / 2;

  for (let n = 0; n < 4; n += 1) {
    const burst = document.createElement('span');
    burst.className = 'word-burst';

    const angle = (Math.PI * 2 * n) / 4 + Math.random() * 0.6;
    const dist = 18 + Math.random() * 14;

    burst.style.left = `${cx}px`;
    burst.style.top = `${cy}px`;
    burst.style.setProperty('--bx', `${Math.cos(angle) * dist}px`);
    burst.style.setProperty('--by', `${Math.sin(angle) * dist}px`);

    wrapEl.appendChild(burst);
    burst.addEventListener('animationend', () => burst.remove());
  }
}

function splitSloganIntoWords() {
  const el = document.querySelector('.hero-slogan');
  const wrapEl = document.querySelector('.hero-slogan-wrap');
  if (!el || !wrapEl) return;

  const text = el.textContent.trim().replace(/\s+/g, ' ');
  el.textContent = '';

  const words = text.split(' ');

  words.forEach((word, i) => {
    const wordSpan = document.createElement('span');
    wordSpan.className = 'word';
    wordSpan.style.setProperty('--i', i);
    wordSpan.textContent = word;
    el.appendChild(wordSpan);

    if (i < words.length - 1) {
      el.appendChild(document.createTextNode(' '));
    }

    const delayMs = (i * 0.24 + 1.3) * 1000 + 250;
    setTimeout(() => spawnWordBurst(wordSpan, wrapEl), delayMs);
  });
}

document.addEventListener('DOMContentLoaded', splitSloganIntoWords);

// ============================================================
// Video de fondo del hero — elige vertical/horizontal por JS
// (más confiable que <source media="..."> en navegadores mobile
// como el webview de Instagram/WhatsApp) y fuerza mute.
// ============================================================

const heroVideo = document.querySelector('.hero-video');
if (heroVideo) {
  heroVideo.muted = true;
  heroVideo.volume = 0;

  const isMobile = window.matchMedia('(max-width: 700px)').matches;
  const src = isMobile
    ? heroVideo.dataset.srcMobile
    : heroVideo.dataset.srcDesktop;

  heroVideo.setAttribute('src', src);
  heroVideo.load();

  const tryPlay = () => heroVideo.play().catch(() => {});
  heroVideo.addEventListener('loadedmetadata', tryPlay);
  tryPlay();
}

// ============================================================
// El tracking de analíticas + Meta CAPI ahora vive en tracking.js
// (se carga antes que este archivo desde index.html).
// ============================================================

// ============================================================
// Íconos sociales sin URL real todavía — evitar que "#" haga
// saltar la página al tope mientras no se cargan los links reales.
// ============================================================

document.querySelectorAll('a[data-placeholder="true"]').forEach((el) => {
  el.addEventListener('click', (e) => e.preventDefault());
});
