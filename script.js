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

  // El salto de línea fijo de acá abajo está pensado (word index 2)
  // para el slogan corto original de una sola frase. Si el título
  // trae sus propias líneas armadas a mano (clase "hero-slogan--static",
  // usada en el borrador con el título nuevo de 2 oraciones), no se
  // reparte en palabras — se deja el HTML tal cual está escrito.
  if (el.classList.contains('hero-slogan--static')) return;

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

    // Salto de línea explícito y fijo: garantiza exactamente 2 líneas
    // en mobile sin depender de que el ancho/fuente calcen justo.
    // Oculto por CSS fuera de mobile (.slogan-break { display: none }).
    if (i === 2) {
      const br = document.createElement('br');
      br.className = 'slogan-break';
      el.appendChild(br);
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

// ============================================================
// Formulario de suscripción — submit controlado, sin recargar.
// Guarda el email en backend/subscribe.php (tabla `subscribers`),
// descargable como CSV desde el panel privado.
// ============================================================

const newsletterForm = document.getElementById('newsletterForm');
if (newsletterForm) {
  const feedbackEl = document.getElementById('newsletterFeedback');
  const emailInput = newsletterForm.querySelector('.newsletter-input');

  // Regex estricta: exige un dominio con al menos un punto y un TLD
  // real (rechaza "a@b", "a@@b.com", espacios, puntos sueltos, etc.)
  // para filtrar mails truchos antes de que lleguen al backend.
  const EMAIL_RE =
    /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/;

  // El tilde/cruz se muestra al salir del campo (blur), no mientras se
  // escribe: ver una cruz a mitad de tipear se siente como que bloquea.
  emailInput.addEventListener('focus', () => {
    newsletterForm.classList.remove('is-valid', 'is-invalid');
  });

  emailInput.addEventListener('input', () => {
    if (feedbackEl.getAttribute('data-state') === 'error') {
      feedbackEl.textContent = '';
      feedbackEl.removeAttribute('data-state');
    }
  });

  emailInput.addEventListener('blur', () => {
    const value = emailInput.value.trim();
    if (!value) {
      newsletterForm.classList.remove('is-valid', 'is-invalid');
      return;
    }
    const valid = EMAIL_RE.test(value);
    newsletterForm.classList.toggle('is-valid', valid);
    newsletterForm.classList.toggle('is-invalid', !valid);
  });

  newsletterForm.addEventListener('submit', (e) => {
    e.preventDefault();

    const email = emailInput.value.trim();
    const isValid = EMAIL_RE.test(email);

    if (!isValid) {
      feedbackEl.textContent = 'Ingresá un correo electrónico válido.';
      feedbackEl.setAttribute('data-state', 'error');
      return;
    }

    feedbackEl.removeAttribute('data-state');
    feedbackEl.textContent = 'Enviando...';

    // Misma campaña que ya guardó tracking.js al entrar (sessionStorage),
    // así se sabe qué anuncio generó esta suscripción puntual.
    let campaign = {};
    try {
      campaign = JSON.parse(window.sessionStorage.getItem('trk_campaign') || '{}');
    } catch (e) { /* sigue sin campaña si algo falla */ }

    // Mismo session_id que usa tracking.js — permite cruzar en el panel
    // "esta persona clickeó el video/la canción Y también se suscribió".
    let sessionId = null;
    try { sessionId = window.sessionStorage.getItem('trk_session_id'); } catch (e) { /* nada */ }

    fetch('backend/subscribe.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, campaign, session_id: sessionId }),
    })
      .then((res) => {
        if (!res.ok) throw new Error('subscribe_failed');
        feedbackEl.textContent = '¡Gracias! Pronto vas a recibir novedades.';
        feedbackEl.setAttribute('data-state', 'success');
        newsletterForm.reset();
        newsletterForm.classList.remove('is-valid', 'is-invalid');
      })
      .catch(() => {
        feedbackEl.textContent = 'No pudimos guardar tu email. Probá de nuevo.';
        feedbackEl.setAttribute('data-state', 'error');
      });
  });
}
