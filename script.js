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

    const delayMs = (i * 0.24 + 0.3) * 1000 + 250;
    setTimeout(() => spawnWordBurst(wordSpan, wrapEl), delayMs);
  });
}

document.addEventListener('DOMContentLoaded', splitSloganIntoWords);

// ============================================================
// Video de fondo del hero — forzar mute (sin audio, es decorativo)
// ============================================================

const heroVideo = document.querySelector('.hero-video');
if (heroVideo) {
  heroVideo.muted = true;
  heroVideo.volume = 0;
}

// ============================================================
// Tracking (Meta Pixel + Conversions API) — ver config.js
// ============================================================

function trackEvent(eventName, payload) {
  if (!META_CONFIG.enabled) return;

  try {
    if (typeof fbq === 'function') {
      fbq('trackCustom', eventName, payload);
    }
    // Conversions API (server-side): reenviar el mismo evento al backend.
    // El Access Token nunca vive en este archivo.
    fetch(META_CONFIG.apiEndpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ event_name: eventName, ...payload }),
    }).catch(() => {});
  } catch (_err) {
    // El tracking nunca debe bloquear la navegación del usuario.
  }
}

document.addEventListener('DOMContentLoaded', () => {
  trackEvent('PageView', {});
  trackEvent('ViewContent', {
    content_name: SONG_NAME,
    content_category: 'Landing',
  });
});

document.querySelectorAll('[data-event]').forEach((el) => {
  el.addEventListener('click', () => {
    trackEvent(el.dataset.event, {
      content_name: SONG_NAME,
      content_category: el.dataset.event === 'WatchVideo' ? 'Videoclip' : 'Music',
      destination: el.dataset.destination,
    });
  });
});

// ============================================================
// Íconos sociales sin URL real todavía — evitar que "#" haga
// saltar la página al tope mientras no se cargan los links reales.
// ============================================================

document.querySelectorAll('a[data-placeholder="true"]').forEach((el) => {
  el.addEventListener('click', (e) => e.preventDefault());
});
