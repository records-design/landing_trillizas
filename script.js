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
