// ============================================================
// CONFIGURACIÓN CENTRAL — editar acá, no en el HTML/JS
// ============================================================

// Nombre de la canción, usado en los payloads de tracking.
const SONG_NAME = 'La Locomotora llega a la estación';

// ------------------------------------------------------------
// REDES SOCIALES — bloque "Cruzá el portal mágico" debajo de los botones del hero
// ------------------------------------------------------------
// Todavía no hay URLs reales: quedan en "#" para no romper el layout.
// Reemplazar cada valor por la URL real y sacar el href="#" del HTML
// (agregar también target="_blank" rel="noopener noreferrer" ahí).
const SOCIAL_LINKS = {
  instagram: '#', // TODO: perfil real de Instagram
  spotifyArtist: '#', // TODO: perfil/artista real en Spotify (distinto del link a la canción)
  youtubeChannel: '#', // TODO: canal real de YouTube (distinto del link al videoclip)
};

// ------------------------------------------------------------
// ANALÍTICAS + META CONVERSIONS API (server-side)
// ------------------------------------------------------------
// tracking.js envía cada page_view/click a este endpoint PHP en Hostinger.
// El endpoint guarda el evento en MySQL y lo reenvía a Meta CAPI.
// El Access Token de Meta NUNCA vive acá: solo en backend/config.php (server-side).
const ANALYTICS_CONFIG = {
  enabled: true,
  // Ruta del endpoint de captura (relativa al dominio de la landing).
  // Ajustar si el backend se sube a otra carpeta.
  endpoint: '/backend/track.php',
};
