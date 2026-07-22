// ============================================================
// CONFIGURACIÓN CENTRAL — editar acá, no en el HTML/JS
// ============================================================

// Nombre de la canción, usado en los payloads de tracking.
const SONG_NAME = 'La Locomotora llega a la estación';

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
