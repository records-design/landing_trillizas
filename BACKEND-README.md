# Panel de analíticas + Meta CAPI — Guía de instalación (Hostinger)

Esta guía es para la persona que tiene acceso al hosting. Todo el **código ya está
escrito**; acá se explica qué subir, qué crear en la base de datos y qué completar.

---

## 0. Resumen de lo que se agregó a la landing

| Archivo / carpeta | Qué es |
|---|---|
| `index.html` | Metaetiqueta de verificación de Meta, **snippet del Meta Pixel** (base, sin `PageView` automático), IDs de tracking en los botones y la carga de `tracking.js`. |
| `tracking.js` | Registra `page_view` y `click`, captura los parámetros de campaña de la URL, y dispara **tanto** el Pixel del navegador **como** el envío al servidor con el mismo `event_id` (deduplicación). |
| `config.js` | `ANALYTICS_CONFIG` con la ruta del endpoint. |
| `backend/` | Todo el PHP: captura de eventos, geolocalización, envío a Meta CAPI (server-side). |
| `statics-8f2k1/` | El panel privado (login + dashboard + export CSV). |
| `backend/schema.sql` | Las tablas MySQL a importar. |
| `robots.txt` | Bloquea la indexación del panel y del backend. |

**Sobre el Pixel + Conversions API:** el proyecto usa las dos vías de Meta al mismo
tiempo, como recomienda Meta: el Pixel (navegador, vía `index.html`/`tracking.js`) y
la Conversions API (servidor, vía `backend/`). Ambas mandan el **mismo `event_id`**
por cada evento, así Meta las reconoce como un solo evento y no duplica el conteo
en Ads Manager. No hay que configurar nada aparte en Meta para esto — usan el mismo
Pixel/Dataset ID (`1608591474024181`) que ya está en el código.

La landing visible **no cambió** en diseño.

---

## 1. Subir los archivos

Subir por FTP o el Administrador de Archivos de Hostinger, respetando la estructura,
dentro de `public_html/` (o la carpeta pública del dominio):

```
public_html/
├── index.html
├── style.css
├── script.js
├── config.js
├── tracking.js
├── robots.txt
├── imagenes/ …
├── videos/ …
├── backend/
│   ├── track.php
│   ├── db.php
│   ├── config_loader.php
│   ├── config.php          ← NO subir la versión con placeholders sin completar (ver paso 3)
│   ├── capi.php
│   ├── geo.php
│   ├── helpers.php
│   ├── schema.sql
│   └── .htaccess
└── statics-8f2k1/
    ├── index.php
    ├── login.php
    ├── auth.php
    ├── logout.php
    ├── data.php
    ├── export.php
    └── .htaccess
```

---

## 2. Crear la base de datos

1. En hPanel → **Bases de datos MySQL** → crear una base y un usuario.
   Anotar: **host** (normalmente `localhost`), **nombre de la base**, **usuario** y **contraseña**.
2. Entrar a **phpMyAdmin** → seleccionar esa base → pestaña **Importar** →
   subir `backend/schema.sql` → **Continuar**.
   Esto crea las tablas `sessions`, `events`, `ad_reference` y `login_attempts`.

---

## 3. Completar `backend/config.php`

Abrir `backend/config.php` y completar:

- **`db`**: host, nombre, usuario y contraseña del paso 2.
- **`meta`**: ya viene con el Pixel ID y el Access Token. **Recomendado rotar el token**
  en Meta Events Manager y pegar el nuevo (el actual ya circuló por chat).
- **`panel`**:
  - `user`: el usuario para entrar al panel.
  - `password_hash`: generar el hash de tu contraseña. Desde la terminal de Hostinger
    (o cualquier PHP):
    ```
    php -r "echo password_hash('TU_CLAVE_ACA', PASSWORD_DEFAULT);"
    ```
    Copiar el resultado (empieza con `$2y$…`) y pegarlo en `password_hash`.
  - `session_secret`: cualquier string largo y aleatorio.
- **`exclude_ips`**: agregar la(s) IP(s) del equipo para no trackear las visitas propias.

> **Más seguro (opcional):** mover `config.php` a una carpeta **fuera** de `public_html`
> (ej. un nivel arriba). El código lo busca automáticamente en varias ubicaciones
> (ver `config_loader.php`). Si se puede, es la mejor práctica para el token.

`config.php` está en `.gitignore`: no se sube a ningún repositorio.

---

## 4. Verificar el dominio en Meta

La metaetiqueta ya está en el `<head>` de `index.html`:

```html
<meta name="facebook-domain-verification" content="wpaf3wqvov0he4v25y9coh9aplzuin" />
```

Una vez publicada la landing, ir a **Meta Business Settings → Dominios**, elegir el
dominio y confirmar la verificación (botón). No requiere nada más en el código.

---

## 5. Configurar los parámetros de URL en Meta Ads

En cada anuncio, en el campo de URL del sitio web, cargar:

```
https://TUDOMINIO.com/?utm_source={{site_source_name}}&utm_medium=paid_social&utm_campaign={{campaign.name}}&utm_content={{ad.name}}&campaign_id={{campaign.id}}&adset_id={{adset.id}}&ad_id={{ad.id}}&placement={{placement}}
```

`tracking.js` ya lee todos esos parámetros automáticamente.

---

## 6. Probar

1. **Tracking:** abrir la landing con parámetros de prueba, por ejemplo:
   `https://TUDOMINIO.com/?utm_source=test&ad_id=123&placement=instagram_reels`
   Después, tocar un botón. En phpMyAdmin, la tabla `events` debería tener las filas.
2. **Panel:** entrar a `https://TUDOMINIO.com/statics-8f2k1/` → loguear → ver los datos.
3. **Meta Pixel (navegador):** instalar la extensión **Meta Pixel Helper** en Chrome,
   abrir la landing y confirmar que detecta el pixel `1608591474024181` y el evento
   `PageView`.
4. **Meta CAPI (servidor) + deduplicación:** en Meta Events Manager → pestaña
   **Test Events**, cargar el `test_event_code` temporal en `config.php`
   (`meta.test_event_code`) y recargar la landing. Deberían aparecer los eventos
   `PageView` / `ClicBoton` marcados con **dos fuentes: "Navegador" y "Servidor"**,
   pero **contados una sola vez** cada uno (no duplicados). Si aparecen duplicados,
   revisar que el `event_id` que llega por ambas vías sea idéntico (se genera una
   vez por evento en `tracking.js`, función `send()`).
   Al terminar la prueba, **vaciar** el `test_event_code` en producción.

---

## 7. Notas de seguridad y mantenimiento

- **HTTPS**: Hostinger lo da con Let's Encrypt; asegurarse de que el sitio fuerce https.
- **Backups**: programar backup periódico de la base de datos en hPanel.
- **Geolocalización**: usa `ip-api.com` gratis (~45 req/min). Si una campaña trae mucho
  tráfico simultáneo, considerar un proveedor con API key (ver `config.php → geo`).
- **Retención de datos**: definir cada cuánto borrar eventos crudos viejos (privacidad).
- **Rotar el Access Token** de Meta cuando esté todo andando.
- Si se rota el Pixel/Dataset ID (no solo el token), actualizarlo en **dos lugares**:
  `backend/config.php` (`meta.pixel_id`, usado por la Conversions API) **y**
  `index.html` (`fbq('init', '...')`, usado por el Pixel del navegador). El Access
  Token, en cambio, solo va en `config.php` — nunca en `index.html`.

---

## 8. Nombres de los botones que se trackean

| `data-track-button` | Botón en la landing |
|---|---|
| `videoclip` | "Mirá el videoclip" (YouTube) |
| `cancion_spotify` | "Escuchá la canción" (Spotify) |
| `social_instagram` | Ícono Instagram |
| `social_spotify` | Ícono Spotify |
| `social_youtube` | Ícono YouTube |

Para agregar otro botón trackeable, solo agregarle el atributo
`data-track-button="nombre_unico"` en el HTML.
