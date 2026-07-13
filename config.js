// ============================================================
// CONFIGURACIÓN CENTRAL — editar acá, no en el HTML/JS
// ============================================================

// Nombre de la canción, usado en los payloads de tracking.
const SONG_NAME = 'La Locomotora llega a la estación';

// ------------------------------------------------------------
// REDES SOCIALES — bloque "Seguinos en" debajo de los botones del hero
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
// META PIXEL + CONVERSIONS API
// ------------------------------------------------------------
// Para activar Meta Pixel:
//   1. Poner enabled: true
//   2. Completar pixelId con el ID real de Meta Events Manager
//   3. Agregar el snippet oficial de Meta Pixel (fbq.js) en el <head> de index.html
// Para Conversions API (server-side, deduplicado con el Pixel):
//   1. Crear un endpoint backend/serverless en apiEndpoint (ej: función de Vercel/Netlify)
//   2. Ese endpoint reenvía el evento a Meta usando el Access Token — el token NUNCA
//      debe vivir en este archivo ni en ningún código que corra en el navegador.
const META_CONFIG = {
  enabled: false,
  pixelId: '', // TODO: Pixel ID de Meta Ads Manager
  apiEndpoint: '/api/meta-conversion', // TODO: endpoint backend/serverless para Conversions API
};
