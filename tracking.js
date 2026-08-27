// ============================================================
// TRACKING — Analíticas propias + Meta Conversions API
// ------------------------------------------------------------
// Registra page_view al cargar y click en cada botón trackeable.
// Lee los parámetros de campaña de la URL de entrada y los guarda
// en sessionStorage para asociarlos a los clics posteriores.
// Envía todo al endpoint PHP (ANALYTICS_CONFIG.endpoint) que se
// encarga de guardar en MySQL y reenviar a Meta CAPI server-side.
//
// NO contiene credenciales: el Access Token de Meta vive solo en el backend.
// ============================================================

(function () {
  'use strict';

  // Momento en que arrancó a cargar la página — sirve para calcular
  // cuánto tiempo pasó hasta cada clic (señal de interés real vs.
  // un clic accidental apenas entra alguien).
  var PAGE_LOAD_TS = (window.performance && performance.timeOrigin)
    ? performance.timeOrigin
    : Date.now();

  // ANALYTICS_CONFIG se declara en config.js. Como es `const`, no queda en
  // window (scripts clásicos), así que se accede por nombre con guarda typeof.
  var CFG =
    typeof ANALYTICS_CONFIG !== 'undefined'
      ? ANALYTICS_CONFIG
      : { enabled: false, endpoint: '/backend/track.php' };
  if (!CFG.enabled) return;

  // ----------------------------------------------------------
  // Utilidades
  // ----------------------------------------------------------

  // Genera un ID razonablemente único sin depender de crypto (compat. amplia).
  function uid() {
    if (window.crypto && crypto.randomUUID) {
      try { return crypto.randomUUID(); } catch (e) { /* sigue al fallback */ }
    }
    return (
      Date.now().toString(36) +
      '-' +
      Math.random().toString(36).slice(2, 10) +
      Math.random().toString(36).slice(2, 10)
    );
  }

  function getStore(key) {
    try { return window.sessionStorage.getItem(key); } catch (e) { return null; }
  }

  function setStore(key, value) {
    try { window.sessionStorage.setItem(key, value); } catch (e) { /* modo privado */ }
  }

  // localStorage persiste entre pestañas y visitas (a diferencia de
  // sessionStorage, que se borra al cerrar la pestaña) — es lo que
  // permite reconocer a un visitante que vuelve otro día.
  function getLocal(key) {
    try { return window.localStorage.getItem(key); } catch (e) { return null; }
  }

  function setLocal(key, value) {
    try { window.localStorage.setItem(key, value); } catch (e) { /* modo privado */ }
  }

  // ----------------------------------------------------------
  // Identificador de visitante (persiste entre visitas, en este navegador)
  // ----------------------------------------------------------
  var VISITOR_KEY = 'trk_visitor_id';
  var visitorId = getLocal(VISITOR_KEY);
  if (!visitorId) {
    visitorId = uid();
    setLocal(VISITOR_KEY, visitorId);
  }

  // ----------------------------------------------------------
  // Identificador de sesión (persiste durante la pestaña)
  // ----------------------------------------------------------
  var SESSION_KEY = 'trk_session_id';
  var sessionId = getStore(SESSION_KEY);
  if (!sessionId) {
    sessionId = uid();
    setStore(SESSION_KEY, sessionId);
  }

  // ----------------------------------------------------------
  // Parámetros de campaña de la URL de entrada
  // Se capturan una sola vez (primer ingreso de la sesión) y se
  // reusan para todos los eventos posteriores de la misma sesión.
  // ----------------------------------------------------------
  var CAMPAIGN_KEY = 'trk_campaign';
  var CAMPAIGN_FIELDS = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'campaign_id',
    'adset_id',
    'ad_id',
    'placement',
    'fbclid',
    'gclid',
  ];

  function readCampaignFromUrl() {
    var params;
    try { params = new URLSearchParams(window.location.search); }
    catch (e) { return {}; }

    var out = {};
    for (var i = 0; i < CAMPAIGN_FIELDS.length; i++) {
      var f = CAMPAIGN_FIELDS[i];
      var v = params.get(f);
      if (v) out[f] = v;
    }
    return out;
  }

  function getCampaign() {
    var stored = getStore(CAMPAIGN_KEY);
    var fromUrl = readCampaignFromUrl();

    // Si la URL trae parámetros de campaña, tienen prioridad y se guardan.
    if (Object.keys(fromUrl).length > 0) {
      setStore(CAMPAIGN_KEY, JSON.stringify(fromUrl));
      return fromUrl;
    }

    if (stored) {
      try { return JSON.parse(stored); } catch (e) { return {}; }
    }
    return {};
  }

  var campaign = getCampaign();

  // ----------------------------------------------------------
  // Envío de eventos
  // ----------------------------------------------------------
  function buildPayload(eventName, eventId, extra) {
    var payload = {
      event_name: eventName,          // 'page_view' | 'click'
      event_id: eventId,              // mismo id que recibe el Pixel del navegador (dedup)
      session_id: sessionId,
      visitor_id: visitorId,          // persiste entre visitas: distingue nuevo vs. recurrente
      url: window.location.href,
      referrer: document.referrer || '',
      screen_w: window.screen ? window.screen.width : null,
      screen_h: window.screen ? window.screen.height : null,
      ts: Date.now(),
      campaign: campaign,
    };
    if (extra) {
      for (var k in extra) {
        if (Object.prototype.hasOwnProperty.call(extra, k)) payload[k] = extra[k];
      }
    }
    return payload;
  }

  // Dispara el Pixel del navegador (si está cargado) con el MISMO event_id
  // que se manda al servidor, para que Meta deduplique entre ambas vías.
  function trackPixel(eventName, eventId) {
    if (typeof fbq !== 'function') return;
    try {
      var metaEvent = eventName === 'page_view' ? 'PageView' : 'ClicBoton';
      var isStandard = metaEvent === 'PageView';
      fbq(isStandard ? 'track' : 'trackCustom', metaEvent, {}, { eventID: eventId });
    } catch (e) { /* el Pixel nunca debe romper la navegación */ }
  }

  function send(eventName, extra) {
    var eventId = uid();
    var payload = buildPayload(eventName, eventId, extra);
    var body = JSON.stringify(payload);

    trackPixel(eventName, eventId);

    // sendBeacon es el método más confiable cuando el clic navega a otra página.
    if (navigator.sendBeacon) {
      try {
        var blob = new Blob([body], { type: 'application/json' });
        if (navigator.sendBeacon(CFG.endpoint, blob)) return;
      } catch (e) { /* sigue al fallback */ }
    }

    // Fallback: fetch con keepalive para que sobreviva a la navegación.
    try {
      fetch(CFG.endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: body,
        keepalive: true,
        credentials: 'omit',
      }).catch(function () {});
    } catch (e) { /* el tracking nunca debe romper la navegación */ }
  }

  // ----------------------------------------------------------
  // page_view (una vez por carga)
  // ----------------------------------------------------------
  function trackPageView() {
    send('page_view', {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', trackPageView);
  } else {
    trackPageView();
  }

  // ----------------------------------------------------------
  // click en botones trackeables (data-track-button)
  // ----------------------------------------------------------
  document.addEventListener(
    'click',
    function (e) {
      var el = e.target && e.target.closest
        ? e.target.closest('[data-track-button]')
        : null;
      if (!el) return;

      send('click', {
        button: el.getAttribute('data-track-button'),
        destination: el.getAttribute('data-destination') || '',
        href: el.getAttribute('href') || '',
        dwell_ms: Math.max(0, Math.round(Date.now() - PAGE_LOAD_TS)),
      });
    },
    true // captura: se dispara aunque el <a> navegue enseguida
  );

  // ----------------------------------------------------------
  // Interés general en el contenido: cuánto tiempo pasan en la
  // página y hasta dónde llegan haciendo scroll. Se manda una sola
  // vez por visita, cuando la persona se va (cambia de pestaña,
  // cierra o navega a otro lado) — no al cargar, porque todavía no
  // hay nada que medir.
  // ----------------------------------------------------------
  var maxScrollPct = 0;
  var scrollTicking = false;

  function computeScrollPct() {
    var doc = document.documentElement;
    var scrollable = (doc.scrollHeight || 0) - window.innerHeight;
    if (scrollable <= 0) return 100; // página sin scroll: ya se ve entera
    var pct = ((window.scrollY || doc.scrollTop || 0) / scrollable) * 100;
    return Math.max(0, Math.min(100, Math.round(pct)));
  }

  window.addEventListener('scroll', function () {
    if (scrollTicking) return;
    scrollTicking = true;
    requestAnimationFrame(function () {
      maxScrollPct = Math.max(maxScrollPct, computeScrollPct());
      scrollTicking = false;
    });
  }, { passive: true });

  var engagementSent = false;
  function sendEngagement() {
    if (engagementSent) return;
    engagementSent = true;
    send('engagement', {
      dwell_ms: Math.max(0, Math.round(Date.now() - PAGE_LOAD_TS)),
      scroll_pct: maxScrollPct,
    });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') sendEngagement();
  });
  window.addEventListener('pagehide', sendEngagement);
})();
